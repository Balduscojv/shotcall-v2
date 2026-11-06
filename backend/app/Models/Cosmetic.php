<?php

namespace App\Models;

use App\Enums\CosmeticSlot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Prêmio (banner, moldura ou título). `trigger` diz o que libera: SIGNUP, MISSION:CLUTCH, ...;
 * sem trigger, só a moderação libera. `secret` = missão secreta: antes de liberar, o jogador
 * só vê "???" e a dica (`hint`).
 */
class Cosmetic extends Model
{
    public const TRIGGER_SIGNUP = 'SIGNUP';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['anchors' => 'array', 'slot' => CosmeticSlot::class, 'secret' => 'boolean'];
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_cosmetics');
    }

    /** @param Builder<Cosmetic> $query */
    public function scopeTriggeredBy(Builder $query, string $trigger): void
    {
        $query->where('trigger', $trigger);
    }

    /** Ordem da vitrine: tipo, depois raridade, depois nome. */
    public function scopeShowcaseOrder(Builder $query): void
    {
        $query->orderByRaw("case slot when 'BANNER' then 1 when 'FRAME' then 2 when 'TITLE' then 3 else 4 end")
            ->orderByRaw("case rarity when 'COMMON' then 1 when 'RARE' then 2 when 'EPIC' then 3 when 'LEGENDARY' then 4 else 5 end")
            ->orderBy('name');
    }
}
