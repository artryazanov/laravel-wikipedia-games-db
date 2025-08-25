<?php

namespace Artryazanov\WikipediaGamesDb\Services;

use Symfony\Component\DomCrawler\Crawler;

/**
 * InfoboxParser extracts structured data from the Wikipedia infobox.
 */
class InfoboxParser
{
    /**
     * Mapping of English infobox labels to internal keys.
     */
    private const FIELD_MAP = [
        'Developer' => 'developers',
        'Developers' => 'developers',
        'Developer(s)' => 'developers',

        'Publisher' => 'publishers',
        'Publishers' => 'publishers',
        'Publisher(s)' => 'publishers',
        'Publication' => 'publishers',

        'Genre' => 'genres',
        'Genres' => 'genres',
        'Genre(s)' => 'genres',

        'Engine' => 'engines',
        'Engines' => 'engines',
        'Engine(s)' => 'engines',

        'Mode' => 'modes',
        'Modes' => 'modes',
        'Mode(s)' => 'modes',

        'Series' => 'series',

        'Platform' => 'platforms',
        'Platforms' => 'platforms',
        'Platform(s)' => 'platforms',

        'Release' => 'release_date',
        'Released' => 'release_date',
        'Release date' => 'release_date',
        'Release dates' => 'release_date',
        'Release date(s)' => 'release_date',
        'First release' => 'release_date',
    ];

    /**
     * Parse HTML of a Wikipedia page and return structured data from infobox.
     *
     * @return array<string, mixed>
     */
    public function parse(string $html): array
    {
        $crawler = new Crawler($html);
        $infobox = $crawler->filter('table.infobox');

        if ($infobox->count() === 0) {
            return [];
        }

        $data = [];

        $infobox->filter('tr')->each(function (Crawler $row) use (&$data) {
            $header = $row->filter('th');
            $cell = $row->filter('td');

            if ($header->count() > 0 && $cell->count() > 0) {
                $key = trim($header->text());
                if (isset(self::FIELD_MAP[$key])) {
                    $internalKey = self::FIELD_MAP[$key];
                    if ($internalKey === 'cover_image_url') {
                        $img = $cell->filter('img');
                        if ($img->count() > 0) {
                            $src = $img->attr('src');
                            if ($src && str_starts_with($src, '//')) {
                                $src = 'https:'.$src;
                            }
                            $data['cover_image_url'] = $src;
                        }
                    } else {
                        $data[$internalKey] = $this->extractDataFromCell($cell, $internalKey);
                    }
                }
            }
        });

        // Some pages place the image in a generic image cell; attempt general image fallback
        if (! isset($data['cover_image_url'])) {
            $imageNode = $infobox->filter('td a.image img')->first();
            if ($imageNode->count() > 0) {
                $src = $imageNode->attr('src');
                if ($src && str_starts_with($src, '//')) {
                    $src = 'https:'.$src;
                }
                $data['cover_image_url'] = $src;
            }
        }

        return $data;
    }

    /**
     * Extract data depending on internal field key.
     */
    private function extractDataFromCell(Crawler $cell, string $key): mixed
    {
        switch ($key) {
            case 'developers':
            case 'publishers':
            case 'genres':
            case 'modes':
            case 'series':
            case 'engines':
            case 'platforms':
                return ($links = $this->extractLinks($cell)) ? $links : $this->extractList($cell);
            case 'release_date':
                return $this->extractDate($cell);
            default:
                return trim($cell->text());
        }
    }

    /**
     * Extract array of items from lists or links in a cell.
     *
     * @return string[]
     */
    private function extractList(Crawler $cell): array
    {
        $items = [];
        $cell->filter('li')->each(function (Crawler $li) use (&$items) {
            $text = $this->cleanText($li->text());
            if ($text !== '') {
                $items[] = $text;
            }
        });

        if (empty($items)) {
            $cell->filter('a')->each(function (Crawler $a) use (&$items) {
                $text = $this->cleanText($a->text());
                if ($text !== '') {
                    $items[] = $text;
                }
            });
        }

        if (empty($items)) {
            $text = $this->cleanText($cell->text());
            if ($text !== '') {
                // Split by commas if present
                foreach (preg_split('/\s*,\s*/u', $text) as $t) {
                    $t = trim($t);
                    if ($t !== '') {
                        $items[] = $t;
                    }
                }
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * Extract array of text from links.
     *
     * @return string[]
     */
    private function extractLinks(Crawler $cell): array
    {
        $items = [];
        $cell->filter('a')->each(function (Crawler $a) use (&$items) {
            $text = $this->cleanText($a->text());
            if ($text !== '') {
                $items[] = $text;
            }
        });

        return array_values(array_unique($items));
    }

    /**
     * Extract a date string in a simple, tolerant way. Returns first found date-like string.
     */
    private function extractDate(Crawler $cell): ?string
    {
        $text = $cell->text();

        // Try common English formats: Month dd, yyyy (e.g., December 19, 2024)
        if (preg_match('/([A-Za-z]+\s+\d{1,2},\s+\d{4})/u', $text, $m)) {
            return $m[1];
        }

        // dd Month yyyy (e.g., 19 December 2024)
        if (preg_match('/(\d{1,2}\s+[A-Za-z]+\s+\d{4})/u', $text, $m)) {
            return $m[1];
        }

        // Month yyyy (e.g., December 2024)
        if (preg_match('/([A-Za-z]+\s+\d{4})/u', $text, $m)) {
            return $m[1];
        }

        // Fallback: year only
        if (preg_match('/(\d{4})/u', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Remove citation markers and trim.
     */
    private function cleanText(string $text): string
    {
        $text = preg_replace('/\[\d+\]/u', '', $text) ?? $text;

        return trim($text);
    }
}
