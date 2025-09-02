<?php

namespace Artryazanov\WikipediaGamesDb\Console;

use Artryazanov\WikipediaGamesDb\Jobs\FetchTemplateTransclusionsJob;
use Illuminate\Console\Command;

/**
 * Console command to enqueue discovery jobs via template transclusions (embeddedin).
 */
class DiscoverByTemplateCommand extends Command
{
    protected $signature = 'games:discover-by-template
                            {template=Template:Infobox video game : Template title to query (default: Infobox video game)}
                            {--series : Also enqueue for Template:Infobox video game series}';

    protected $description = 'Discover pages by listing transclusions of target templates (embeddedin).';

    public function handle(): int
    {
        $template = (string) $this->argument('template');
        if ($template === '') {
            $this->error('Template title must not be empty.');

            return self::FAILURE;
        }

        $this->info("Dispatching embeddedin discovery for: {$template}");
        FetchTemplateTransclusionsJob::dispatch($template)
            ->onConnection(config('game-scraper.queue_connection'))
            ->onQueue(config('game-scraper.queue_name'));

        if ((bool) $this->option('series')) {
            $seriesTpl = 'Template:Infobox video game series';
            $this->info("Dispatching embeddedin discovery for: {$seriesTpl}");
            FetchTemplateTransclusionsJob::dispatch($seriesTpl)
                ->onConnection(config('game-scraper.queue_connection'))
                ->onQueue(config('game-scraper.queue_name'));
        }

        $this->info('Discovery enqueued. Run a queue worker to process jobs.');

        return self::SUCCESS;
    }
}
