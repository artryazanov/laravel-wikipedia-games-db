<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessCategoryJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UniqueJobsTest extends TestCase
{
    public function test_process_category_job_is_unique_until_processing(): void
    {
        // Prevent actual execution and capture pushed jobs
        Queue::fake();

        // Dispatch the same category twice
        ProcessCategoryJob::dispatch('Category:1993 video games');
        ProcessCategoryJob::dispatch('Category:1993 video games');

        // Only one should be pushed due to ShouldBeUniqueUntilProcessing + uniqueId()
        Queue::assertPushed(ProcessCategoryJob::class, 1);

        // Dispatch a different category – should increase the count
        ProcessCategoryJob::dispatch('Category:1994 video games');
        Queue::assertPushed(ProcessCategoryJob::class, 2);
    }

    public function test_process_game_page_job_is_unique_until_processing(): void
    {
        Queue::fake();

        ProcessGamePageJob::dispatch('Doom (1993 video game)');
        ProcessGamePageJob::dispatch('Doom (1993 video game)');

        Queue::assertPushed(ProcessGamePageJob::class, 1);

        ProcessGamePageJob::dispatch('Doom II');
        Queue::assertPushed(ProcessGamePageJob::class, 2);
    }
}
