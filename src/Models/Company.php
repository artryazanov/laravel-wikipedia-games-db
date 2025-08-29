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

    /**
     * Mass-assignable attributes for extended company information.
     */
    protected $fillable = [
        'name',
        'slug',
        'title',
        'wikipedia_url',
        'description',
        'wikitext',
        'cover_image_url',
        'founded',
        'website_url',
    ];

    /**
     * Games relation (many-to-many via pivot with 'role').
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'wikipedia_game_game_company')->withPivot('role');
    }
}
