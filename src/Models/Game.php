<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Artryazanov\\WikipediaGamesDb\\Models\\Game
 *
 * @property int $id
 * @property int|null $wikipage_id Reference to the wikipedia_game_wikipages table
 * @property string|null $clean_title Normalized game title without disambiguation
 * @property string|null $cover_image_url URL of the cover image
 * @property CarbonInterface|null $release_date First known release date
 * @property int|null $release_year First 4-digit release year parsed
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 *
 * @property-read Wikipage|null $wikipage
 * @property-read Collection<int, Genre> $genres
 * @property-read Collection<int, Platform> $platforms
 * @property-read Collection<int, Mode> $modes
 * @property-read Collection<int, Series> $series
 * @property-read Collection<int, Engine> $engines
 * @property-read Collection<int, Company> $companies
 * @property-read Collection<int, Company> $developers
 * @property-read Collection<int, Company> $publishers
 *
 * @method static Builder|Game newModelQuery()
 * @method static Builder|Game newQuery()
 * @method static Builder|Game query()
 */
class Game extends Model
{
    protected $table = 'wikipedia_games';

    protected $fillable = [
        'wikipage_id',
        'clean_title',
        'cover_image_url',
        'release_date',
        'release_year',
    ];

    protected $appends = [
        // Proxy fields moved to related wikipage for convenient serialization/access
        'title',
        'wikipedia_url',
    ];

    protected $casts = [
        'release_date' => 'date',
        'release_year' => 'integer',
    ];

    /**
     * Related Wikipedia page meta.
     */
    public function wikipage(): BelongsTo
    {
        return $this->belongsTo(Wikipage::class);
    }

    /**
     * Genres relation (many-to-many via game_genre).
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'wikipedia_game_game_genre');
    }

    /**
     * Platforms relation (many-to-many via game_platform).
     */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'wikipedia_game_game_platform');
    }

    /**
     * Modes relation (many-to-many via pivot).
     */
    public function modes(): BelongsToMany
    {
        return $this->belongsToMany(Mode::class, 'wikipedia_game_game_mode');
    }

    /**
     * Series relation (many-to-many via pivot).
     */
    public function series(): BelongsToMany
    {
        return $this->belongsToMany(Series::class, 'wikipedia_game_game_series');
    }

    /**
     * Engines relation (many-to-many via pivot).
     */
    public function engines(): BelongsToMany
    {
        return $this->belongsToMany(Engine::class, 'wikipedia_game_game_engine');
    }

    /**
     * Companies relation (many-to-many via game_company with 'role' on pivot).
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'wikipedia_game_game_company')->withPivot('role');
    }

    /**
     * Companies acting as developers.
     */
    public function developers(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'wikipedia_game_game_company')->wherePivot('role', 'developer');
    }

    /**
     * Companies acting as publishers.
     */
    public function publishers(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'wikipedia_game_game_company')->wherePivot('role', 'publisher');
    }

    /**
     * Accessor: proxy title to related wikipage->title
     */
    public function getTitleAttribute(): ?string
    {
        return $this->wikipage?->title;
    }

    /**
     * Accessor: proxy wikipedia_url to related wikipage->wikipedia_url
     */
    public function getWikipediaUrlAttribute(): ?string
    {
        return $this->wikipage?->wikipedia_url;
    }
}
