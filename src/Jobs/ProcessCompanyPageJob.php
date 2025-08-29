<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Models\Company;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ProcessCompanyPageJob fetches a Wikipedia company page, parses data, and persists it.
 * It stores extended company fields: title, wikipedia_url, description, wikitext,
 * cover_image_url, founded (year), website_url.
 */
class ProcessCompanyPageJob extends AbstractWikipediaJob implements ShouldBeUnique
{
    /** Number of attempts before failing the job. */
    public int $tries = 3;

    /** Backoff seconds between retries. */
    public int $backoff = 120;

    public function __construct(public string $pageTitle) {}

    /**
     * Unique identifier for this job to prevent duplicate enqueues for the same page.
     */
    public function uniqueId(): string
    {
        return static::class.':'.$this->pageTitle;
    }

    /**
     * Handle the queued job.
     */
    public function handle(MediaWikiClient $client, InfoboxParser $parser): void
    {
        $this->executeWithThrottle(function () use ($client, $parser) {
            $this->doJob($client, $parser);
        });
    }

    private function doJob(MediaWikiClient $client, InfoboxParser $parser): void
    {
        $html = $client->getPageHtml($this->pageTitle);
        if (! $html) {
            $this->fail(new \RuntimeException("Failed to fetch HTML for company page: {$this->pageTitle}"));

            return;
        }

        $data = $parser->parse($html);
        if (empty($data)) {
            Log::warning('No infobox data found for company page', ['title' => $this->pageTitle]);

            return;
        }

        // Fallback for cover image
        if (empty($data['cover_image_url'])) {
            $mainImage = $client->getPageMainImage($this->pageTitle);
            if (is_string($mainImage) && $mainImage !== '') {
                $data['cover_image_url'] = $mainImage;
            }
        }

        $leadDescription = $client->getPageLeadDescription($this->pageTitle);
        $wikitext = $client->getPageWikitext($this->pageTitle);
        $wikipediaUrl = 'https://en.wikipedia.org/wiki/'.str_replace(' ', '_', $this->pageTitle);

        DB::transaction(function () use ($data, $leadDescription, $wikitext, $wikipediaUrl) {
            // Try to find an existing company by name or slug derived from the page title
            $slugFromTitle = $this->makeSlug($this->pageTitle, 255);
            $company = Company::where('name', $this->pageTitle)
                ->orWhere('slug', $slugFromTitle)
                ->first();

            $payload = [
                'title' => $this->pageTitle,
                'wikipedia_url' => $wikipediaUrl,
                'description' => $leadDescription ?? null,
                'wikitext' => $wikitext,
                'cover_image_url' => $data['cover_image_url'] ?? null,
                'founded' => isset($data['founded']) && is_scalar($data['founded']) ? (int) $data['founded'] : null,
                'website_url' => $data['website_url'] ?? null,
            ];

            if ($company) {
                $company->fill($payload);
                $company->save();
            } else {
                $company = Company::create(array_merge([
                    'name' => $this->pageTitle,
                    'slug' => $slugFromTitle,
                ], $payload));
            }
        });
    }

    private function makeSlug(string $name, int $maxLen = 255): string
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            $hash = substr(sha1($name), 0, 12);
            $fallback = 'n-a-'.$hash;

            return substr($fallback, 0, $maxLen);
        }
        if (strlen($slug) <= $maxLen) {
            return $slug;
        }
        $hash = substr(sha1($name), 0, 8);
        $suffix = '-'.$hash;
        $baseLen = $maxLen - strlen($suffix);
        if ($baseLen < 1) {
            return substr($slug, 0, $maxLen);
        }
        $base = substr($slug, 0, $baseLen);
        $base = rtrim($base, '-');

        return $base.$suffix;
    }
}
