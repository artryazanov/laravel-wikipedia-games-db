<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Core Game model representing a video game scraped from Wikipedia.
 */
class Game extends Model
{
    protected $table = 'wikipedia_games';

    protected $fillable = [
        'title',
        'clean_title',
        'wikipedia_url',
        'description',
        'wikitext',
        'cover_image_url',
        'release_date',
        'release_year',
    ];

    protected $casts = [
        'release_date' => 'date',
        'release_year' => 'integer',
    ];

    /**
     * Genres relation (many-to-many via game_genre).
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'wikipedia_game_game_genre');
    }

    /**
     * Platforms relation (many-to-many via game_platform).
     */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'wikipedia_game_game_platform');
    }

    /**
     * Modes relation (many-to-many via pivot).
     */
    public function modes(): BelongsToMany
    {
        return $this->belongsToMany(Mode::class, 'wikipedia_game_game_mode');
    }

    /**
     * Series relation (many-to-many via pivot).
     */
    public function series(): BelongsToMany
    {
        return $this->belongsToMany(Series::class, 'wikipedia_game_game_series');
    }

    /**
     * Engines relation (many-to-many via pivot).
     */
    public function engines(): BelongsToMany
    {
        return $this->belongsToMany(Engine::class, 'wikipedia_game_game_engine');
    }

    /**
     * Companies relation (many-to-many via game_company with 'role' on pivot).
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'wikipedia_game_game_company')->withPivot('role');
    }

    /**
     * Companies acting as developers.
     */
    public function developers(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'wikipedia_game_game_company')->wherePivot('role', 'developer');
    }

    /**
     * Companies acting as publishers.
     */
    public function publishers(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'wikipedia_game_game_company')->wherePivot('role', 'publisher');
    }
}
