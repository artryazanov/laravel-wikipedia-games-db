<?php

namespace Artryazanov\WikipediaGamesDb\Console;

use Artryazanov\WikipediaGamesDb\Jobs\FetchTemplateTransclusionsJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessCategoryJob;
use Illuminate\Console\Command;

/**
 * Console command to launch all discovery strategies in one go:
 * - Template transclusions for games and series
 * - High-value category seeds (by platform, by genre)
 * - Optional root category from config if present
 */
class ScanAllCommand extends Command
{
    protected $signature = 'games:scan-all';

    protected $description = 'Dispatch discovery via templates and categories in one command.';

    public function handle(): int
    {
        // Templates
        $templates = [
            'Template:Infobox video game',
            'Template:Infobox video game series',
        ];
        foreach ($templates as $tpl) {
            $this->info("Dispatching embeddedin discovery for: {$tpl}");
            FetchTemplateTransclusionsJob::dispatch($tpl)
                ->onConnection(config('game-scraper.queue_connection'))
                ->onQueue(config('game-scraper.queue_name'));
        }

        // Categories: high-value seeds + optional root from config
        $seeds = [
            'Category:Video games by platform',
            'Category:Video games by genre',
        ];

        $root = (string) (config('game-scraper.root_category') ?: '');
        if ($root !== '') {
            $seeds[] = $root;
        }

        $seeds = array_values(array_unique($seeds));
        foreach ($seeds as $seed) {
            $this->info("Dispatching category traversal for: {$seed}");
            ProcessCategoryJob::dispatch($seed)
                ->onConnection(config('game-scraper.queue_connection'))
                ->onQueue(config('game-scraper.queue_name'));
        }

        $this->info('All discovery jobs dispatched. Run a queue worker to process.');

        return self::SUCCESS;
    }
}

