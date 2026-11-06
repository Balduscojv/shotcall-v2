<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected function casts(): array { return ['start_time' => 'datetime']; }

    public function home(): BelongsTo { return $this->belongsTo(Team::class, 'home_team_id'); }
    public function away(): BelongsTo { return $this->belongsTo(Team::class, 'away_team_id'); }
    public function round(): BelongsTo { return $this->belongsTo(Round::class); }
    public function periods(): HasMany { return $this->hasMany(GamePeriod::class); }
    public function actions(): HasMany { return $this->hasMany(GameAction::class); }
    public function stats(): HasMany { return $this->hasMany(PlayerGameStat::class); }

    /** Palpites só são aceitos antes do horário oficial de início. */
    public function isLocked(): bool
    {
        return $this->status !== 'SCHEDULED' || now()->greaterThanOrEqualTo($this->start_time);
    }
}
