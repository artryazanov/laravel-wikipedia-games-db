<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Series model representing a game series/franchise taxonomy.
 */
class Series extends Model
{
    protected $table = 'wikipedia_game_series';

    /**
     * Mass-assignable attributes for extended series information.
     */
    protected $fillable = [
        'name',
        'slug',
        'title',
        'wikipedia_url',
        'description',
        'wikitext',
    ];

    /**
     * Games relation (many-to-many via pivot table).
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'wikipedia_game_game_series');
    }
}
