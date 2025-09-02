<?php

namespace Artryazanov\WikipediaGamesDb\Console;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessCategoryJob;
use Illuminate\Console\Command;

/**
 * Console command to initiate scraping of English Wikipedia for video games.
 */
class ScrapeWikipediaCommand extends Command
{
    protected $signature = 'games:scrape-wikipedia
                            {--category= : Start scraping from a specific category instead of the root.}
                            {--seed-high-value : Seed multiple high-value roots (by platform, by genre).}';

    protected $description = 'Initiates the process of scraping video game data from Wikipedia.';

    public function handle(): int
    {
        if ($this->option('seed-high-value')) {
            $seeds = [
                'Category:Video games by platform',
                'Category:Video games by genre',
            ];
            foreach ($seeds as $seed) {
                $this->info("Dispatching initial job for category: {$seed}");
                ProcessCategoryJob::dispatch($seed)
                    ->onConnection(config('game-scraper.queue_connection'))
                    ->onQueue(config('game-scraper.queue_name'));
            }
        } else {
            $startCategory = $this->option('category') ?: config('game-scraper.root_category');
            if (! $startCategory) {
                $this->error('No start category provided and no root_category configured.');

                return self::FAILURE;
            }

            $this->info("Dispatching initial job for category: {$startCategory}");

            ProcessCategoryJob::dispatch($startCategory)
                ->onConnection(config('game-scraper.queue_connection'))
                ->onQueue(config('game-scraper.queue_name'));
        }

        $this->info('Scraping process initiated. Monitor your queue worker for progress.');

        return self::SUCCESS;
    }
}
