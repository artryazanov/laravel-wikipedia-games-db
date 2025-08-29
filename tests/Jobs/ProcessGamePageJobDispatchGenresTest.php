<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessGenrePageJob;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProcessGamePageJobDispatchGenresTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_genre_jobs_for_linked_genres(): void
    {
        config()->set('game-scraper.throttle_milliseconds', 0);

        $title = 'Game With Genre Links';
        $html = '<html></html>';

        Bus::fake();

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageMainImage')->andReturn(null);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Lead');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('WT');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'genres' => ['Shooter', 'Role-playing'],
                'genres_link_titles' => ['Shooter (video games)', 'Role-playing video game'],
            ]);
        });

        (new ProcessGamePageJob($title))->handle($client, $parser);

        Bus::assertDispatched(ProcessGenrePageJob::class, fn ($job) => $job->pageTitle === 'Shooter (video games)');
        Bus::assertDispatched(ProcessGenrePageJob::class, fn ($job) => $job->pageTitle === 'Role-playing video game');
        Bus::assertDispatchedTimes(ProcessGenrePageJob::class, 2);
    }
}
