<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\FetchTemplateTransclusionsJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessCategoryJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueUniquenessTest extends TestCase
{
    public function test_it_does_not_queue_duplicate_game_jobs(): void
    {
        Queue::fake();

        // Dispatch the same job twice
        ProcessGamePageJob::dispatch('The Legend of Zelda');
        ProcessGamePageJob::dispatch('The Legend of Zelda');

        // Only one should be queued due to ShouldBeUniqueUntilProcessing
        Queue::assertPushed(ProcessGamePageJob::class, 1);
    }

    public function test_template_jobs_are_unique_by_title_and_token(): void
    {
        Queue::fake();

        // Same template + same token => only one queued
        FetchTemplateTransclusionsJob::dispatch('Template:Infobox video game', null);
        FetchTemplateTransclusionsJob::dispatch('Template:Infobox video game', null);
        Queue::assertPushed(FetchTemplateTransclusionsJob::class, 1);

        // Same template + different token => both queued
        FetchTemplateTransclusionsJob::dispatch('Template:Infobox video game', 'abc|123');
        FetchTemplateTransclusionsJob::dispatch('Template:Infobox video game', 'def|456');
        Queue::assertPushed(FetchTemplateTransclusionsJob::class, 3); // 1 + 2 new
    }

    public function test_category_jobs_are_unique_by_category_and_token(): void
    {
        Queue::fake();

        // Duplicate page of the same category
        ProcessCategoryJob::dispatch('Category:Video games by year', 'cmcontinue|1');
        ProcessCategoryJob::dispatch('Category:Video games by year', 'cmcontinue|1');
        Queue::assertPushed(ProcessCategoryJob::class, 1);

        // Next page token is different, so both should enqueue
        ProcessCategoryJob::dispatch('Category:Video games by year', 'cmcontinue|2');
        Queue::assertPushed(ProcessCategoryJob::class, 2);
    }
}
