<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Base an abstract job with shared queue traits and throttling helper.
 */
abstract class AbstractWikipediaJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute provided callback with global throttle and release if skipped.
     */
    protected function executeWithThrottle(callable $callback): void
    {
        $delayMs = (int) config('game-scraper.throttle_milliseconds', 1000);
        if ($delayMs <= 0) {
            $callback();

            return;
        }

        $decaySeconds = (int) ceil($delayMs / 1000);
        $key = $this->getThrottleKey();

        $executed = RateLimiter::attempt($key, 1, function () use ($callback) {
            $callback();
        }, $decaySeconds);

        if (! $executed) {
            // Release the job back to the queue to try again after the decay
            $this->release($decaySeconds);
        }
    }

    /**
     * Allow overriding the throttle key in child jobs if needed.
     */
    protected function getThrottleKey(): string
    {
        return 'job:laravel-wikipedia-games-db-jobs:lock';
    }

    /**
     * Provide a stable unique identifier so duplicate jobs aren't queued.
     */
    public function uniqueId(): string
    {
        $payload = [];
        foreach (['pageTitle', 'templateTitle', 'categoryTitle', 'continueToken'] as $key) {
            if (property_exists($this, $key)) {
                /** @phpstan-ignore-next-line */
                $payload[$key] = $this->{$key};
            }
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return static::class.':'.md5($json ?: static::class);
    }
}
