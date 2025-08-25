<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Base an abstract job with shared queue traits and throttling helper.
 */
abstract class AbstractWikipediaJob implements ShouldQueue
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
}
