<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Artryazanov\\WikipediaGamesDb\\Models\\Mode
 *
 * @property int $id
 * @property string $name Mode name
 * @property int|null $wikipage_id Reference to the wikipedia_game_wikipages table
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Wikipage|null $wikipage
 * @property-read Collection<int, Game> $games
 *
 * @method static Builder|Mode newModelQuery()
 * @method static Builder|Mode newQuery()
 * @method static Builder|Mode query()
 */
class Mode extends Model
{
    protected $table = 'wikipedia_game_modes';

    /**
     * Mass-assignable attributes for extended mode information.
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
        return $this->belongsToMany(Game::class, 'wikipedia_game_game_mode');
    }
}
