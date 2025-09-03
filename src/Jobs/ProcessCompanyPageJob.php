<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Models\Company;
use Artryazanov\WikipediaGamesDb\Models\Wikipage;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Artryazanov\WikipediaGamesDb\Support\Concerns\CleansTitles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcessCompanyPageJob fetches a Wikipedia company page, parses data, and persists it.
 * It stores extended company fields: title, wikipedia_url, description, wikitext,
 * cover_image_url, founded (year), website_url.
 */
class ProcessCompanyPageJob extends AbstractWikipediaJob
{
    use CleansTitles;
    /** Number of attempts before failing the job. */
    public int $tries = 3;

    /** Backoff seconds between retries. */
    public int $backoff = 120;

    public function __construct(public string $pageTitle) {}

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
        $cleanName = $this->makeCleanTitle($this->pageTitle);
        $wikitext = $client->getPageWikitext($this->pageTitle);
        $wikipediaUrl = 'https://en.wikipedia.org/wiki/'.str_replace(' ', '_', $this->pageTitle);

        DB::transaction(function () use ($data, $leadDescription, $wikitext, $wikipediaUrl, $cleanName) {
            // Upsert Wikipage
            $wikipage = Wikipage::where('wikipedia_url', $wikipediaUrl)
                ->orWhere('title', $this->pageTitle)
                ->first();

            $wikipagePayload = [
                'title' => $this->pageTitle,
                'wikipedia_url' => $wikipediaUrl,
                'description' => $leadDescription ?? null,
                'wikitext' => $wikitext,
            ];
            if ($wikipage) {
                $wikipage->fill($wikipagePayload)->save();
            } else {
                $wikipage = Wikipage::create($wikipagePayload);
            }

            // Upsert Company by name or attached wikipage
            $company = Company::where('name', $this->pageTitle)
                ->orWhere('wikipage_id', $wikipage->id)
                ->first();

            $payload = [
                'wikipage_id' => $wikipage->id,
                'clean_name' => $cleanName,
                'cover_image_url' => $data['cover_image_url'] ?? null,
                'founded' => isset($data['founded']) && is_scalar($data['founded']) ? (int) $data['founded'] : null,
                'website_url' => $data['website_url'] ?? null,
            ];

            if ($company) {
                $company->fill($payload)->save();
            } else {
                $company = Company::create(array_merge([
                    'name' => $this->pageTitle,
                ], $payload));
            }
        });
    }
}
