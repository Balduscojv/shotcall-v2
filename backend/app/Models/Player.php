<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlayerPrice::class);
    }

    /**
     * Posição detalhada para o fantasy (PG, SG, SF, PF, C). Sem quadro de profundidade, sai da
     * genérica: G vira SG, F vira SF.
     */
    public function fantasyRole(): ?string
    {
        return $this->role ?? match ($this->position) {
            'G' => 'SG',
            'F' => 'SF',
            'C' => 'C',
            default => null,
        };
    }
}
