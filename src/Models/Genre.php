<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Genre model representing a game genre taxonomy.
 */
class Genre extends Model
{
    protected $table = 'wikipedia_game_genres';

    /**
     * Mass-assignable attributes for extended genre information.
     */
    protected $fillable = [
        'name',
        'wikipage_id',
    ];

    public function wikipage(): BelongsTo
    {
        return $this->belongsTo(Wikipage::class);
    }

    /**
     * Games relation (many-to-many via pivot table).
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'wikipedia_game_game_genre');
    }
}
