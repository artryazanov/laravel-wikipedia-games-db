<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Platform model representing a target platform taxonomy.
 */
class Platform extends Model
{
    protected $table = 'wikipedia_game_platforms';

    protected $fillable = ['name', 'slug'];

    /**
     * Games relation (many-to-many via pivot table).
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'wikipedia_game_game_platform');
    }
}
