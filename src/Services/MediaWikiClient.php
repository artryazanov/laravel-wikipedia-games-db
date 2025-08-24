<?php

namespace Artryazanov\WikipediaGamesDb\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MediaWikiClient encapsulates HTTP requests to the MediaWiki API.
 */
class MediaWikiClient
{
    protected PendingRequest $http;

    public function __construct(
        protected string $apiEndpoint,
        protected string $userAgent
    ) {
        $this->http = Http::baseUrl($apiEndpoint)
            ->withHeaders(['User-Agent' => $userAgent])
            ->acceptJson();
    }

    /**
     * Fetch members of a given Wikipedia category.
     * Returns an array with keys: 'members' => [], 'continue' => string|null
     */
    public function getCategoryMembers(string $categoryTitle, ?string $continueToken = null): ?array
    {
        $params = [
            'action' => 'query',
            'format' => 'json',
            'list' => 'categorymembers',
            'cmtitle' => $categoryTitle,
            'cmtype' => 'subcat|page',
            'cmlimit' => 100,
        ];
        if ($continueToken) {
            $params['cmcontinue'] = $continueToken;
        }

        $response = $this->http->get('', $params);

        if ($response->failed()) {
            Log::warning('MediaWiki getCategoryMembers failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        $members = Arr::get($data, 'query.categorymembers', []);
        $cont = Arr::get($data, 'continue.cmcontinue');

        return [
            'members' => array_map(function ($m) {
                return [
                    'title' => $m['title'] ?? '',
                    'type' => $m['type'] ?? ($m['ns'] === 14 ? 'subcat' : 'page'),
                ];
            }, $members),
            'continue' => $cont ?? null,
        ];
    }

    /**
     * Fetch the parsed HTML content of a Wikipedia page.
     * Returns raw HTML string or null.
     */
    public function getPageHtml(string $pageTitle): ?string
    {
        $params = [
            'action' => 'parse',
            'format' => 'json',
            'page' => $pageTitle,
            'prop' => 'text',
            'redirects' => 1,
        ];

        $response = $this->http->get('', $params);

        if ($response->failed()) {
            Log::warning('MediaWiki getPageHtml failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $json = $response->json();
        $text = $json['parse']['text'] ?? null;
        if (is_array($text)) {
            // MediaWiki returns an object with "*" key for HTML string
            return $text['*'] ?? null;
        }

        return is_string($text) ? $text : null;
    }

    /**
     * Get the main/lead image URL for a page title using Wikimedia APIs.
     * Strategy: REST summary (originalimage|thumbnail) -> Action API pageimages fallback.
     */
    public function getPageMainImage(string $pageTitle): ?string
    {
        // Try REST summary first
        $origin = $this->wikiOrigin();
        $restUrl = rtrim($origin, '/').'/api/rest_v1/page/summary/'.rawurlencode($pageTitle);

        $restResp = Http::withHeaders(['User-Agent' => $this->userAgent])
            ->acceptJson()
            ->get($restUrl);
        if ($restResp->ok()) {
            $json = $restResp->json();
            $url = Arr::get($json, 'originalimage.source') ?: Arr::get($json, 'thumbnail.source');
            if (is_string($url) && $url !== '') {
                return $url;
            }
        } else {
            Log::info('REST summary request failed', ['status' => $restResp->status(), 'url' => $restUrl]);
        }

        // Fallback: Action API pageimages
        $params = [
            'action' => 'query',
            'format' => 'json',
            'prop' => 'pageimages',
            'piprop' => 'original|thumbnail',
            'pithumbsize' => 1000,
            'titles' => $pageTitle,
        ];
        $piResp = $this->http->get('', $params);
        if ($piResp->failed()) {
            Log::warning('MediaWiki pageimages failed', [
                'status' => $piResp->status(),
                'body' => $piResp->body(),
            ]);

            return null;
        }
        $data = $piResp->json();
        $pages = Arr::get($data, 'query.pages', []);
        if (is_array($pages) && ! empty($pages)) {
            $first = reset($pages);
            $url = $first['original']['source'] ?? ($first['thumbnail']['source'] ?? null);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * Derive the wiki origin (scheme + host[:port]) from configured API endpoint.
     * Example: https://en.wikipedia.org/w/api.php -> https://en.wikipedia.org
     */
    protected function wikiOrigin(): string
    {
        $parts = parse_url($this->apiEndpoint);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'en.wikipedia.org';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    /**
     * Get the lead description (summary) of the page as plain text.
     * Strategy: REST /page/summary extract -> fallback to Action API extracts (plain, intro).
     */
    public function getPageLeadDescription(string $pageTitle): ?string
    {
        // Prefer REST summary extract (plain text)
        $origin = $this->wikiOrigin();
        $restUrl = rtrim($origin, '/').'/api/rest_v1/page/summary/'.rawurlencode($pageTitle);

        $restResp = Http::withHeaders(['User-Agent' => $this->userAgent])
            ->acceptJson()
            ->get($restUrl);
        if ($restResp->ok()) {
            $json = $restResp->json();
            $extract = Arr::get($json, 'extract');
            if (is_string($extract) && $extract !== '') {
                return $extract;
            }
        } else {
            Log::info('REST summary request (description) failed', ['status' => $restResp->status(), 'url' => $restUrl]);
        }

        // Fallback: Action API TextExtracts (intro, plaintext)
        $params = [
            'action' => 'query',
            'format' => 'json',
            'prop' => 'extracts',
            'exintro' => 1,
            'explaintext' => 1,
            'titles' => $pageTitle,
        ];
        $resp = $this->http->get('', $params);
        if ($resp->failed()) {
            Log::warning('MediaWiki extracts (description) failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);

            return null;
        }
        $data = $resp->json();
        $pages = Arr::get($data, 'query.pages', []);
        if (is_array($pages) && ! empty($pages)) {
            $first = reset($pages);
            $extract = $first['extract'] ?? null;
            if (is_string($extract) && $extract !== '') {
                return $extract;
            }
        }

        return null;
    }

    /**
     * Get the full page content (wikitext) for a page title.
     * Strategy: Action API query with prop=revisions, rvprop=content, rvslots=main, formatversion=2.
     */
    public function getPageWikitext(string $pageTitle): ?string
    {
        $params = [
            'action' => 'query',
            'format' => 'json',
            'prop' => 'revisions',
            'rvprop' => 'content',
            'rvslots' => 'main',
            'titles' => $pageTitle,
            'formatversion' => 2,
            'redirects' => 1,
        ];

        $resp = $this->http->get('', $params);
        if ($resp->failed()) {
            Log::warning('MediaWiki getPageWikitext failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);

            return null;
        }

        $json = $resp->json();
        $pages = Arr::get($json, 'query.pages', []);
        if (is_array($pages) && ! empty($pages)) {
            $first = reset($pages);
            $content = Arr::get($first, 'revisions.0.slots.main.content');
            if (is_string($content) && $content !== '') {
                return $content;
            }
        }

        return null;
    }
}
