<?php

namespace Tests\Services;

use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MediaWikiClientTest extends TestCase
{
    public function test_is_disambiguation_true_and_sends_expected_query(): void
    {
        Http::fake([
            'https://en.wikipedia.org/w/api.php*' => Http::response([
                'query' => [
                    'pages' => [
                        '123' => [
                            'pageprops' => [
                                'disambiguation' => '',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new MediaWikiClient('https://en.wikipedia.org/w/api.php', 'UA/1.2');

        $this->assertTrue($client->isDisambiguation('Test Page'));

        Http::assertSent(function ($request) {
            $url = (string) $request->url();
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

            return ($query['action'] ?? null) === 'query'
                && ($query['prop'] ?? null) === 'pageprops'
                && ($query['ppprop'] ?? null) === 'disambiguation'
                && ($query['titles'] ?? null) === 'Test Page';
        });

        Http::assertSentCount(1);
    }

    public function test_is_disambiguation_false_on_http_failure(): void
    {
        Http::fake([
            'https://en.wikipedia.org/w/api.php*' => Http::response('oops', 500),
        ]);

        $client = new MediaWikiClient('https://en.wikipedia.org/w/api.php', 'UA/1.2');

        $this->assertFalse($client->isDisambiguation('Test Page'));

        Http::assertSentCount(1);
    }
    public function test_get_all_pages_sends_user_agent_and_params_and_parses_response(): void
    {
        Http::fake([
            // Match the configured API endpoint
            'https://en.wikipedia.org/w/api.php*' => Http::response([
                'query' => [
                    'allpages' => [
                        ['title' => 'Page One', 'ns' => 0],
                        ['title' => 'Page Two', 'ns' => 0],
                    ],
                ],
                'continue' => [
                    'apcontinue' => 'NEXT|123',
                ],
            ], 200),
        ]);

        $ua = 'MyTestAgent/1.0 (+https://example.test)';
        $client = new MediaWikiClient('https://en.wikipedia.org/w/api.php', $ua);

        $result = $client->getAllPages(2, 'ABC|1');

        // Parsed result
        $this->assertIsArray($result);
        $this->assertSame('NEXT|123', $result['continue']);
        $this->assertSame([
            ['title' => 'Page One', 'ns' => 0],
            ['title' => 'Page Two', 'ns' => 0],
        ], $result['pages']);

        // Verify request was sent with expected headers and query params
        Http::assertSent(function ($request) use ($ua) {
            $url = (string) $request->url();
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

            return str_starts_with($url, 'https://en.wikipedia.org/w/api.php')
                && $request->hasHeader('User-Agent', $ua)
                && ($query['action'] ?? null) === 'query'
                && ($query['format'] ?? null) === 'json'
                && ($query['list'] ?? null) === 'allpages'
                && ($query['aplimit'] ?? null) == 2
                && ($query['apnamespace'] ?? null) == 0
                && ($query['apcontinue'] ?? null) === 'ABC|1';
        });

        Http::assertSentCount(1);
    }

    public function test_get_all_pages_returns_null_on_failed_response(): void
    {
        Http::fake([
            'https://en.wikipedia.org/w/api.php*' => Http::response('Server error', 500),
        ]);

        $client = new MediaWikiClient('https://en.wikipedia.org/w/api.php', 'UA');

        $this->assertNull($client->getAllPages(10));

        Http::assertSentCount(1);
    }

    public function test_get_all_pages_returns_null_on_api_error_payload(): void
    {
        Http::fake([
            'https://en.wikipedia.org/w/api.php*' => Http::response([
                'error' => ['code' => 'badrequest', 'info' => 'Bad things'],
            ], 200),
        ]);

        $client = new MediaWikiClient('https://en.wikipedia.org/w/api.php', 'UA');

        $this->assertNull($client->getAllPages(10));

        Http::assertSentCount(1);
    }
}
