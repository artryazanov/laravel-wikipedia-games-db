<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Artryazanov\\WikipediaGamesDb\\Models\\Platform
 *
 * @property int $id
 * @property string $name Platform name
 * @property int|null $wikipage_id Reference to the wikipedia_game_wikipages table
 * @property string|null $cover_image_url Main image URL
 * @property CarbonInterface|null $release_date Platform release/launch date
 * @property string|null $website_url Official website URL
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 *
 * @property-read Wikipage|null $wikipage
 * @property-read Collection<int, Game> $games
 *
 * @method static Builder|Platform newModelQuery()
 * @method static Builder|Platform newQuery()
 * @method static Builder|Platform query()
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
