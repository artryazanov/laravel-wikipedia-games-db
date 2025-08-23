<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Genre model representing a game genre taxonomy.
 */
class Genre extends Model
{
    protected $table = 'wikipedia_game_genres';

    protected $fillable = ['name', 'slug'];

    /**
     * Games relation (many-to-many via pivot table).
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'wikipedia_game_game_genre');
    }
}
