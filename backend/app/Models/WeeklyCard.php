<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WeeklyCard extends Model
{
    protected $guarded = [];

    public function round(): BelongsTo { return $this->belongsTo(Round::class); }
    public function games(): BelongsToMany { return $this->belongsToMany(Game::class, 'weekly_card_games'); }
}
