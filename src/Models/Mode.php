<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Mode model representing a game mode taxonomy (e.g., Single-player, Multiplayer).
 */
class Mode extends Model
{
    protected $table = 'wikipedia_game_modes';

    protected $fillable = ['name', 'slug'];

    /**
     * Games relation (many-to-many via pivot table).
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'wikipedia_game_game_mode');
    }
}
