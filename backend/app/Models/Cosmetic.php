<?php

namespace App\Models;

use App\Enums\CosmeticSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Prêmio (banner, moldura ou título). Quem libera:
 * - `trigger` SIGNUP: o cadastro (os de boas-vindas);
 * - as missões que o têm como recompensa (mission_rewards);
 * - a moderação, sempre.
 *
 * Visual: `asset_key` aponta para uma arte do front (web/src/lib/cosmetics.ts); prêmio criado
 * pela moderação usa `image_path` (banner enviado) ou `color` (moldura/título).
 * Desativado (`active` = false): ninguém mais libera; quem já tem continua usando.
 */
class Cosmetic extends Model
{
    public const TRIGGER_SIGNUP = 'SIGNUP';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['anchors' => 'array', 'slot' => CosmeticSlot::class, 'active' => 'boolean'];
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_cosmetics');
    }

    public function missions(): BelongsToMany
    {
        return $this->belongsToMany(Mission::class, 'mission_rewards');
    }

    /** @param Builder<Cosmetic> $query */
    public function scopeTriggeredBy(Builder $query, string $trigger): void
    {
        $query->where('trigger', $trigger);
    }

    /** @param Builder<Cosmetic> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /** Ordem da vitrine: tipo, depois raridade, depois nome. */
    public function scopeShowcaseOrder(Builder $query): void
    {
        $query->orderByRaw("case slot when 'BANNER' then 1 when 'FRAME' then 2 when 'TITLE' then 3 else 4 end")
            ->orderByRaw("case rarity when 'COMMON' then 1 when 'RARE' then 2 when 'EPIC' then 3 when 'LEGENDARY' then 4 else 5 end")
            ->orderBy('name');
    }

    /** É recompensa de alguma missão secreta (antes de liberar, aparece como "???"). */
    public function isSecret(): bool
    {
        return $this->relationLoaded('missions')
            ? $this->missions->contains('secret', true)
            : $this->missions()->where('secret', true)->exists();
    }
}
