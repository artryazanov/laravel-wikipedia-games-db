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

    protected $fillable = ['name', 'slug'];

    /**
     * Games relation (many-to-many via pivot table).
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'wikipedia_game_game_engine');
    }
}
