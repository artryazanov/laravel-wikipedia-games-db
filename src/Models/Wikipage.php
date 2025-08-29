<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Artryazanov\\WikipediaGamesDb\\Models\\Wikipage
 *
 * @property int $id
 * @property string|null $title Original Wikipedia page title
 * @property string|null $wikipedia_url Full URL to the page
 * @property string|null $description Lead/summary text
 * @property string|null $wikitext Full page content in wikitext
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 *
 * @method static Builder|Wikipage newModelQuery()
 * @method static Builder|Wikipage newQuery()
 * @method static Builder|Wikipage query()
 */
class Wikipage extends Model
{
    protected $table = 'wikipedia_game_wikipages';

    protected $fillable = [
        'title',
        'wikipedia_url',
        'description',
        'wikitext',
    ];
}
