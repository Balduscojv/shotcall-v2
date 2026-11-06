<?php

namespace App\Http\Resources;

use App\Models\Cosmetic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Prêmio do catálogo. Quando vem de uma coleção de usuário, traz também se ele tem
 * (owned), quando e por que liberou, e se está em uso.
 *
 * @mixin Cosmetic
 */
class CosmeticResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $pivot = $this->relationLoaded('pivot') ? $this->pivot : null;

        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'slot' => $this->slot->value,
            'rarity' => $this->rarity,
            'source' => $this->source,
            'trigger' => $this->when($request->user()?->isAdmin(), $this->trigger),
            // Recompensa de missão secreta. Quem chega a ver o item é porque já liberou (ou é admin).
            'secret' => $this->isSecret(),
            'asset_key' => $this->asset_key,
            'color' => $this->color,
            'image_url' => $this->image_path,
            'active' => $this->when($request->user()?->isAdmin(), fn () => (bool) $this->active),
            'package_key' => $this->package_key,
            'owned' => $this->when($this->hasAttribute('owned'), fn () => (bool) $this->owned),
            'equipped' => $this->when($this->hasAttribute('is_equipped'), fn () => (bool) $this->is_equipped),
            // O pivot não converte datas: sai como "Y-m-d H:i:s" sem fuso, que nem todo navegador entende.
            'unlocked_at' => $pivot?->unlocked_at ? Carbon::parse($pivot->unlocked_at)->toIso8601String() : null,
            'reason' => $pivot?->unlock_reason,
        ];
    }
}
