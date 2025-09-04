<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Base an abstract job with shared queue traits and throttling helper.
 */
abstract class AbstractWikipediaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute provided callback with global throttle and release if skipped.
     */
    protected function executeWithThrottle(callable $callback): void
    {
        $callback();

        $delayMs = (int) config('game-scraper.throttle_milliseconds', 1000);
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
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

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
