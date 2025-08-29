<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Engine model representing a game engine taxonomy (e.g., Unreal Engine, Unity).
 */
class Engine extends Model
{
    protected $table = 'wikipedia_game_engines';

    /**
     * Mass-assignable attributes for extended engine information.
     */
    protected $fillable = [
        'name',
        'title',
        'wikipedia_url',
        'description',
        'wikitext',
        'cover_image_url',
        'release_date',
        'website_url',
    ];

    protected $casts = [
        'release_date' => 'date',
    ];

    /**
     * Games relation (many-to-many via pivot table).
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'wikipedia_game_game_engine');
    }
}
