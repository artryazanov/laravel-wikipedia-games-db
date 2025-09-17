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
        'Initial release' => 'release_date',

        // Company specific
        'Founded' => 'founded',
        'Website' => 'website_url',
        'Website(s)' => 'website_url',
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
                    if (in_array($internalKey, ['developers', 'publishers', 'platforms', 'engines', 'genres', 'modes', 'series'], true)) {
                        $targets = $this->extractAnchorTargets($cell);
                        $links = $this->extractLinks($cell);
                        if (! empty($targets)) {
                            $data[$internalKey] = ! empty($links) ? $links : $targets;
                            $data[$internalKey.'_link_titles'] = $targets;
                        } else {
                            $data[$internalKey] = ! empty($links) ? $links : $this->extractList($cell);
                        }
                    } elseif ($internalKey === 'cover_image_url') {
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
            case 'founded':
                return $this->extractYear($cell);
            case 'website_url':
                return $this->extractWebsite($cell);
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
     * Handles cases where the month is concatenated with preceding words (e.g., "iOSMarch 7, 2013").
     */
    private function extractDate(Crawler $cell): ?string
    {
        $text = $cell->text();
        // Normalize internal whitespace
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        // Use explicit month-name patterns to avoid capturing preceding letters like "iOSMarch"
        $months = '(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)';

        // Month dd, yyyy (e.g., March 7, 2013)
        if (preg_match("/($months)\s+\d{1,2},\s+\d{4}/iu", $text, $m)) {
            return $m[0];
        }

        // dd Month yyyy (e.g., 7 March 2013)
        if (preg_match("/\b\d{1,2}\s+($months)\s+\d{4}\b/iu", $text, $m)) {
            return $m[0];
        }

        // Month yyyy (e.g., March 2013)
        if (preg_match("/($months)\s+\d{4}/iu", $text, $m)) {
            return $m[0];
        }

        // Fallback: year only (reasonable bounds optional)
        if (preg_match('/\b(19|20)\d{2}\b/u', $text, $m)) {
            return $m[0];
        }

        return null;
    }

    /**
     * Extract target page titles from anchor hrefs or title attributes.
     * Returns cleaned, human-friendly titles (underscores replaced with spaces).
     *
     * @return string[]
     */
    private function extractAnchorTargets(Crawler $cell): array
    {
        $items = [];
        $cell->filter('a')->each(function (Crawler $a) use (&$items) {
            $titleAttr = $a->attr('title');
            $href = $a->attr('href');
            $candidate = null;
            if (is_string($titleAttr) && $titleAttr !== '') {
                $candidate = $titleAttr;
            } elseif (is_string($href) && $href !== '') {
                // Use the last segment after /wiki/
                if (preg_match('~/(?:wiki|w)/([^#?]+)~i', $href, $m)) {
                    $candidate = urldecode(str_replace('_', ' ', $m[1]));
                }
            }
            if ($candidate === null) {
                $candidate = $this->cleanText($a->text());
            }
            if ($candidate !== '') {
                $items[] = trim($candidate);
            }
        });

        return array_values(array_unique($items));
    }

    /**
     * Remove citation markers and trim.
     */
    private function cleanText(string $text): string
    {
        $text = preg_replace('/\[\d+\]/u', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * Extract first 4-digit year from the cell text.
     */
    private function extractYear(Crawler $cell): ?int
    {
        $text = $cell->text();
        if (preg_match('/(\d{4})/u', $text, $m)) {
            $year = (int) $m[1];
            $current = (int) date('Y') + 1;
            if ($year >= 1800 && $year <= $current) {
                return $year;
            }

            return $year; // fallback even if outside range
        }

        return null;
    }

    /**
     * Extract the first website URL from the cell. Prefers external links.
     */
    private function extractWebsite(Crawler $cell): ?string
    {
        // Prefer first anchor href
        $a = $cell->filter('a')->first();
        if ($a->count() > 0) {
            $href = $a->attr('href');
            if (is_string($href) && $href !== '') {
                $href = trim($href);
                if (str_starts_with($href, '//')) {
                    return 'https:'.$href;
                }
                if (preg_match('~^https?://~i', $href)) {
                    return $href;
                }
                // Ignore internal wiki links like /wiki/...
                if (str_starts_with($href, '/')) {
                    // Not an external website URL
                    return null;
                }
            }
        }

        // Fallback to raw text if it looks like a domain
        $text = trim($cell->text());
        if ($text !== '' && preg_match('~([a-z0-9.-]+\.[a-z]{2,})(/\S*)?$~i', $text)) {
            if (! preg_match('~^https?://~i', $text)) {
                return 'https://'.$text;
            }

            return $text;
        }

        return null;
    }
}
