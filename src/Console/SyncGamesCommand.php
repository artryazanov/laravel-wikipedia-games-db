<?php

namespace Artryazanov\WikipediaGamesDb\Console;

use Artryazanov\WikipediaGamesDb\Jobs\FetchGamesBatchJob;
use Illuminate\Console\Command;

class SyncGamesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'wikipedia:sync-games {--limit=} {--apcontinue=}';

    /**
     * The console command description.
     */
    protected $description = 'Sync games and their infobox data from Wikipedia by enumerating all pages.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $defaultLimit = (int) config('game-scraper.limit', 100);
        $limit = (int) ($this->option('limit') ?: $defaultLimit);
        $apcontinue = $this->option('apcontinue') ?: null;

        // Queue the first batch job; it will chain subsequent batches until no results remain
        FetchGamesBatchJob::dispatch($limit, $apcontinue)
            ->onConnection(config('game-scraper.queue_connection'))
            ->onQueue(config('game-scraper.queue_name'));

        $this->info("Queued Wikipedia sync: first batch dispatched (limit={$limit}, apcontinue=".($apcontinue ?? 'null').'). Ensure a queue worker is running.');

        return self::SUCCESS;
    }
}
