<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Company model representing a game company (developer/publisher).
 */
class Company extends Model
{
    protected $table = 'wikipedia_game_companies';

    protected $fillable = ['name', 'slug'];

    /**
     * Games relation (many-to-many via pivot with 'role').
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'wikipedia_game_game_company')->withPivot('role');
    }
}
