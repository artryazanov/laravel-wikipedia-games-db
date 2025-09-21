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

    /** @var array<string, array{html: ?string, wikitext: ?string}> */
    protected array $parseBundleCache = [];

    /** @var array<string, array|null> Cached REST summary JSON (null indicates cached miss) */
    protected array $restSummaryCache = [];

    public function __construct(
        protected string $apiEndpoint,
        protected string $userAgent
    ) {
        $this->http = Http::baseUrl($apiEndpoint)
            ->withHeaders(['User-Agent' => $userAgent])
            ->acceptJson();
    }

    /**
     * List pages that embed/include a given template using list=embeddedin.
     * Returns ['members' => array<int, array{title: string, ns: int}>, 'continue' => string|null] or null on failure.
     */
    public function getEmbeddedIn(string $templateTitle, ?string $continueToken = null): ?array
    {
        $params = [
            'action' => 'query',
            'format' => 'json',
            'list' => 'embeddedin',
            'eititle' => $templateTitle,
            'eilimit' => 100,
            'einamespace' => 0, // main/article namespace only
        ];
        if ($continueToken) {
            $params['eicontinue'] = $continueToken;
        }

        $response = $this->http->get('', $params);
        if ($response->failed()) {
            Log::warning('MediaWiki getEmbeddedIn failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        $members = Arr::get($data, 'query.embeddedin', []);
        $cont = Arr::get($data, 'continue.eicontinue');

        return [
            'members' => array_map(function ($m) {
                return [
                    'title' => (string) ($m['title'] ?? ''),
                    'ns' => (int) ($m['ns'] ?? 0),
                ];
            }, $members),
            'continue' => $cont ?? null,
        ];
    }

    /**
     * Check if a page is a disambiguation page via prop=pageprops (ppprop=disambiguation).
     */
    public function isDisambiguation(string $pageTitle): bool
    {
        $params = [
            'action' => 'query',
            'format' => 'json',
            'prop' => 'pageprops',
            'ppprop' => 'disambiguation',
            'titles' => $pageTitle,
            'redirects' => 1,
        ];

        $resp = $this->http->get('', $params);
        if ($resp->failed()) {
            Log::info('MediaWiki pageprops failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);

            return false;
        }

        $data = $resp->json();
        $pages = Arr::get($data, 'query.pages', []);
        if (! is_array($pages)) {
            return false;
        }
        foreach ($pages as $page) {
            if (isset($page['pageprops']) && is_array($page['pageprops']) && array_key_exists('disambiguation', $page['pageprops'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch and cache parse bundle (HTML and wikitext) for a page in a single request.
     * Returns [html => ?string, wikitext => ?string].
     */
    protected function fetchParseBundle(string $pageTitle): array
    {
        if (array_key_exists($pageTitle, $this->parseBundleCache)) {
            return $this->parseBundleCache[$pageTitle];
        }

        $params = [
            'action' => 'parse',
            'format' => 'json',
            'page' => $pageTitle,
            'prop' => 'text|wikitext',
            'redirects' => 1,
        ];

        $response = $this->http->get('', $params);
        if ($response->failed()) {
            Log::warning('MediaWiki parse bundle failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $bundle = ['html' => null, 'wikitext' => null];
            $this->parseBundleCache[$pageTitle] = $bundle;

            return $bundle;
        }

        $json = $response->json();
        $html = null;
        $wt = null;

        $text = $json['parse']['text'] ?? null;
        if (is_array($text)) {
            $html = $text['*'] ?? null;
        } elseif (is_string($text)) {
            $html = $text;
        }

        $wikitext = $json['parse']['wikitext'] ?? null;
        if (is_array($wikitext)) {
            $wt = $wikitext['*'] ?? null;
        } elseif (is_string($wikitext)) {
            $wt = $wikitext;
        }

        $bundle = ['html' => $html, 'wikitext' => $wt];
        $this->parseBundleCache[$pageTitle] = $bundle;

        return $bundle;
    }

    /**
     * Fetch and cache REST summary JSON for a page. Returns JSON array or null on failure.
     * Caches null to avoid duplicate failed requests within the same process.
     */
    protected function fetchRestSummaryJson(string $pageTitle): ?array
    {
        if (array_key_exists($pageTitle, $this->restSummaryCache)) {
            return $this->restSummaryCache[$pageTitle];
        }

        $origin = $this->wikiOrigin();
        $restUrl = rtrim($origin, '/').'/api/rest_v1/page/summary/'.rawurlencode($pageTitle);

        $restResp = Http::withHeaders(['User-Agent' => $this->userAgent])
            ->acceptJson()
            ->get($restUrl);

        if ($restResp->ok()) {
            $json = $restResp->json();
            $this->restSummaryCache[$pageTitle] = $json;

            return $json;
        }

        Log::info('REST summary request failed', ['status' => $restResp->status(), 'url' => $restUrl]);
        $this->restSummaryCache[$pageTitle] = null;

        return null;
    }

    /**
     * List all pages in the main namespace using list=allpages.
     * Returns ['pages' => array<int, array{title: string, ns: int}>, 'continue' => string|null] or null on failure.
     */
    public function getAllPages(int $limit = 100, ?string $continueToken = null): ?array
    {
        $limit = max(1, min(500, $limit));
        $params = [
            'action' => 'query',
            'format' => 'json',
            'list' => 'allpages',
            'aplimit' => $limit,
            'apnamespace' => 0,
        ];
        if ($continueToken) {
            $params['apcontinue'] = $continueToken;
        }

        $response = $this->http->get('', $params);
        if ($response->failed()) {
            Log::warning('MediaWiki getAllPages failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        if (isset($data['error'])) {
            Log::warning('MediaWiki getAllPages API error', $data['error']);

            return null;
        }

        $pages = Arr::get($data, 'query.allpages', []);
        $cont = Arr::get($data, 'continue.apcontinue');

        return [
            'pages' => array_map(function ($p) {
                return [
                    'title' => (string) ($p['title'] ?? ''),
                    'ns' => (int) ($p['ns'] ?? 0),
                ];
            }, $pages),
            'continue' => $cont ?? null,
        ];
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
        $bundle = $this->fetchParseBundle($pageTitle);
        $html = $bundle['html'] ?? null;

        return is_string($html) && $html !== '' ? $html : null;
    }

    /**
     * Get the main/lead image URL for a page title using Wikimedia APIs.
     * Strategy: REST summary (originalimage|thumbnail) -> Action API pageimages fallback.
     */
    public function getPageMainImage(string $pageTitle): ?string
    {
        // Try cached REST summary first (shared with description)
        $json = $this->fetchRestSummaryJson($pageTitle);
        if (is_array($json)) {
            $url = Arr::get($json, 'originalimage.source') ?: Arr::get($json, 'thumbnail.source');
            if (is_string($url) && $url !== '') {
                return $url;
            }
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
        // Prefer cached REST summary extract (shared with image)
        $json = $this->fetchRestSummaryJson($pageTitle);
        if (is_array($json)) {
            $extract = Arr::get($json, 'extract');
            if (is_string($extract) && $extract !== '') {
                return $extract;
            }
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
        $bundle = $this->fetchParseBundle($pageTitle);
        $wt = $bundle['wikitext'] ?? null;

        return is_string($wt) && $wt !== '' ? $wt : null;
    }

    /**
     * Determine if the provided page title is a redirect.
     * Uses action=query with redirects=1 and inspects query.redirects mapping.
     */
    public function isRedirect(string $pageTitle): bool
    {
        $params = [
            'action' => 'query',
            'format' => 'json',
            'titles' => $pageTitle,
            'redirects' => 1,
        ];

        $resp = $this->http->get('', $params);
        if ($resp->failed()) {
            Log::info('MediaWiki redirect check failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);

            return false;
        }

        $data = $resp->json();
        $redirects = Arr::get($data, 'query.redirects', []);
        if (is_array($redirects)) {
            foreach ($redirects as $redir) {
                $from = (string) ($redir['from'] ?? '');
                if ($from !== '' && strcasecmp($from, $pageTitle) === 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
