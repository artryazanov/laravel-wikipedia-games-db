<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\FetchGamesBatchJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchGamesBatchJobTest extends TestCase
{
    public function test_dispatches_process_jobs_and_chains_next_batch(): void
    {
        Bus::fake();

        // Fake MediaWiki allpages response with 2 pages and continuation
        Http::fake([
            '*' => Http::response([
                'query' => [
                    'allpages' => [
                        ['title' => 'Alpha'],
                        ['title' => 'Bravo'],
                    ],
                ],
                'continue' => [
                    'apcontinue' => 'NEXT|123',
                ],
            ], 200),
        ]);

        // Execute the job directly
        (new FetchGamesBatchJob(2))->handle();

        // Two page processing jobs dispatched
        Bus::assertDispatched(ProcessGamePageJob::class, function (ProcessGamePageJob $job) {
            return $job->pageTitle === 'Alpha';
        });
        Bus::assertDispatched(ProcessGamePageJob::class, function (ProcessGamePageJob $job) {
            return $job->pageTitle === 'Bravo';
        });
        Bus::assertDispatchedTimes(ProcessGamePageJob::class, 2);

        // And the next batch is queued with the continuation token
        Bus::assertDispatched(FetchGamesBatchJob::class, function (FetchGamesBatchJob $job) {
            return $job->apcontinue === 'NEXT|123' && $job->limit === 2;
        });
    }

    public function test_no_dispatch_when_empty_results(): void
    {
        Bus::fake();
        Http::fake([
            '*' => Http::response([
                'query' => [
                    'allpages' => [],
                ],
            ], 200),
        ]);

        (new FetchGamesBatchJob(50))->handle();

        Bus::assertNotDispatched(ProcessGamePageJob::class);
        // No chaining
        Bus::assertNotDispatched(FetchGamesBatchJob::class);
    }

    public function test_no_dispatch_on_http_failure(): void
    {
        Bus::fake();
        Http::fake([
            '*' => Http::response('Server error', 500),
        ]);

        (new FetchGamesBatchJob(10))->handle();

        Bus::assertNotDispatched(ProcessGamePageJob::class);
        Bus::assertNotDispatched(FetchGamesBatchJob::class);
    }

    public function test_no_dispatch_on_api_error_payload(): void
    {
        Bus::fake();
        Http::fake([
            '*' => Http::response([
                'error' => ['code' => 'badrequest', 'info' => 'Bad things'],
            ], 200),
        ]);

        (new FetchGamesBatchJob(10))->handle();

        Bus::assertNotDispatched(ProcessGamePageJob::class);
        Bus::assertNotDispatched(FetchGamesBatchJob::class);
    }

    public function test_unique_id_uses_continue_token(): void
    {
        $job = new FetchGamesBatchJob(100, 'abc|123');
        $id = json_decode($job->uniqueId(), true);
        $this->assertSame('abc|123', $id['continueToken'] ?? null);

        $job2 = new FetchGamesBatchJob(50, null);
        $id2 = json_decode($job2->uniqueId(), true);
        $this->assertArrayHasKey('continueToken', $id2);
        $this->assertNull($id2['continueToken']);
    }
}
