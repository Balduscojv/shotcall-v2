<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FantasyTeam extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function round(): BelongsTo { return $this->belongsTo(Round::class); }
    public function players(): HasMany { return $this->hasMany(FantasyTeamPlayer::class); }
}
