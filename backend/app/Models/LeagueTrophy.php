<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pódio de um ranking da liga no fim da temporada (LeagueSeasonCloser). */
class LeagueTrophy extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['rank' => 'integer', 'members_count' => 'integer', 'score' => 'float'];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
