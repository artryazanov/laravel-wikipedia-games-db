<?php

namespace Artryazanov\WikipediaGamesDb\Models;

use Illuminate\Database\Eloquent\Model;

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

