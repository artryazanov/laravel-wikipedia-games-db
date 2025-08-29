<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Artryazanov\\WikipediaGamesDb\\Models\\Company
 *
 * @property int $id
 * @property string $name Company name
 * @property int|null $wikipage_id Reference to the wikipedia_game_wikipages table
 * @property string|null $cover_image_url Main image URL
 * @property int|null $founded Year the company was founded
 * @property string|null $website_url Official website URL
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 *
 * @property-read Wikipage|null $wikipage
 * @property-read Collection<int, Game> $games
 *
 * @method static Builder|Company newModelQuery()
 * @method static Builder|Company newQuery()
 * @method static Builder|Company query()
 */
class Company extends Model
{
    protected $table = 'wikipedia_game_companies';

    /**
     * Mass-assignable attributes for extended company information.
     */
    protected $fillable = [
        'name',
        'wikipage_id',
        'cover_image_url',
        'founded',
        'website_url',
    ];

    public function wikipage(): BelongsTo
    {
        return $this->belongsTo(Wikipage::class);
    }

    /**
     * Games relation (many-to-many via pivot with 'role').
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'wikipedia_game_game_company')->withPivot('role');
    }
}
