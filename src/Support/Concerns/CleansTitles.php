<?php

namespace Artryazanov\WikipediaGamesDb\Support\Concerns;

trait CleansTitles
{
    /**
     * Normalize titles/names by removing HTML, inline CSS/script noise, trailing
     * parenthetical disambiguators like "(1999 video game)", and collapsing whitespace.
     * Also ensures the result fits into typical VARCHAR(255).
     */
    protected function makeCleanTitle(string $title): string
    {
        $clean = (string) $title;

        // 1) Strip HTML tags if any leaked in
        $clean = strip_tags($clean);

        // 2) Remove common citation/footnote markers like [1], [a]
        $clean = preg_replace('/\[(?:\d+|[a-z])\]/iu', '', $clean) ?? $clean;

        // 3) Remove inline CSS blocks that sometimes leak from Wikipedia mobile HTML
        //    e.g., ".mw-parser-output .plainlist ... { ... }"
        $clean = preg_replace('/\.mw-parser-output.*?\}\s*/isu', ' ', $clean) ?? $clean;
        //    And any "selector { rules }"-like noise fragments
        $clean = preg_replace('/(?:^|\s)(?:[.#][\w-]+[^\{]*\{[^\}]*\}\s*)+/u', ' ', $clean) ?? $clean;

        // 4) Decode HTML entities
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 5) Trim first to simplify subsequent regex work
        $clean = trim($clean);

        // 6) Remove trailing parenthetical groups iteratively
        while ($clean !== '' && preg_match('/\s*\([^()]*\)\s*$/u', $clean)) {
            $clean = preg_replace('/\s*\([^()]*\)\s*$/u', '', $clean) ?? $clean;
            $clean = trim($clean);
        }

        // 7) Collapse multiple whitespace to a single space
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;
        $clean = trim($clean);

        // 8) Ensure max length 255 (multibyte safe)
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($clean, 'UTF-8') > 255) {
                $clean = mb_substr($clean, 0, 255, 'UTF-8');
            }
        } else {
            if (strlen($clean) > 255) {
                $clean = substr($clean, 0, 255);
            }
        }

        return $clean;
    }
}
