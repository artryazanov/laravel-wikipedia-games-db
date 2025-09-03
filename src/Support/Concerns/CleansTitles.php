<?php

namespace Artryazanov\WikipediaGamesDb\Support\Concerns;

trait CleansTitles
{
    /**
     * Normalize titles/names by removing trailing parenthetical disambiguators
     * like "(1999 video game)", "(SNES)", etc., and collapsing whitespace.
     */
    protected function makeCleanTitle(string $title): string
    {
        $clean = trim($title);

        // Remove trailing parenthetical groups iteratively
        while (preg_match('/\s*\([^()]*\)\s*$/u', $clean)) {
            $clean = preg_replace('/\s*\([^()]*\)\s*$/u', '', $clean) ?? $clean;
        }

        // Collapse multiple whitespace to a single space
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;

        return trim($clean);
    }
}
