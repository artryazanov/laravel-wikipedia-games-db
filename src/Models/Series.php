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

    protected $fillable = ['name', 'slug'];

    /**
     * Games relation (many-to-many via pivot table).
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'wikipedia_game_game_series');
    }
}
