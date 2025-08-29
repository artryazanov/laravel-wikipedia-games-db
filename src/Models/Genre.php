<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Artryazanov\\WikipediaGamesDb\\Models\\Genre
 *
 * @property int $id
 * @property string $name Genre name
 * @property int|null $wikipage_id Reference to the wikipedia_game_wikipages table
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Wikipage|null $wikipage
 * @property-read Collection<int, Game> $games
 *
 * @method static Builder|Genre newModelQuery()
 * @method static Builder|Genre newQuery()
 * @method static Builder|Genre query()
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
