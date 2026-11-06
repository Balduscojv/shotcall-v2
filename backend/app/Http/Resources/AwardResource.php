<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Cosmético conquistado (prêmio), com a data e o motivo do desbloqueio. @mixin \App\Models\Cosmetic */
class AwardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'slot' => $this->slot,
            'rarity' => $this->rarity,
            'source' => $this->source,
            'package_key' => $this->package_key,
            'unlocked_at' => $this->pivot?->unlocked_at,
            'reason' => $this->pivot?->unlock_reason,
            'equipped' => (bool) $this->pivot?->equipped,
        ];
    }
}
