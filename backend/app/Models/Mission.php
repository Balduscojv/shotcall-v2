<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Missão: uma condição do catálogo (app/Services/Missions/Conditions) com os parâmetros que a
 * moderação escolheu, e os prêmios que ela libera. Secreta = antes de cumprir, o jogador só vê
 * "???" e a dica.
 */
class Mission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'secret' => 'boolean',
            'active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function rewards(): BelongsToMany
    {
        return $this->belongsToMany(Cosmetic::class, 'mission_rewards');
    }

    /** Ativas e dentro do período (sem início/fim = sempre). */
    public function scopeRunning(Builder $query): void
    {
        $query->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** Valor de um parâmetro da condição. */
    public function param(string $name, int|float|null $default = null): int|float|null
    {
        return $this->params[$name] ?? $default;
    }
}
