<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Platform model representing a target platform taxonomy.
 */
class Platform extends Model
{
    protected $table = 'wikipedia_game_platforms';

    /**
     * Mass-assignable attributes for extended platform information.
     */
    protected $fillable = [
        'name',
        'wikipage_id',
        'cover_image_url',
        'release_date',
        'website_url',
    ];

    protected $casts = [
        'release_date' => 'date',
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
        return $this->belongsToMany(Game::class, 'wikipedia_game_game_platform');
    }
}
