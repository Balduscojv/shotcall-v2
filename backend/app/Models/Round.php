<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    public const OPEN = 'OPEN';
    public const LOCKED = 'LOCKED';
    public const SETTLING = 'SETTLING';
    public const SETTLED = 'SETTLED';
    public const UPCOMING = 'UPCOMING';

    protected $guarded = [];

    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime']; }

    public function season(): BelongsTo { return $this->belongsTo(Season::class); }
    public function games(): HasMany { return $this->hasMany(Game::class); }

    public function isMarketOpen(): bool { return $this->market_status === self::OPEN; }

    /** Rodada em andamento: a primeira que ainda não foi apurada. */
    public static function current(): ?self
    {
        return static::whereNot('market_status', self::SETTLED)->orderBy('starts_at')->first();
    }

    public function next(): ?self
    {
        return static::where('season_id', $this->season_id)->where('number', $this->number + 1)->first();
    }

    public function previous(): ?self
    {
        return static::where('season_id', $this->season_id)->where('number', $this->number - 1)->first();
    }
}
