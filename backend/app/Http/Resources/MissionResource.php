<?php

namespace App\Http\Resources;

use App\Models\Mission;
use App\Services\Missions\ConditionCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Mission */
class MissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $condition = app(ConditionCatalog::class)->find($this->condition);

        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'condition' => $this->condition,
            'condition_label' => $condition?->label() ?? $this->condition,
            'params' => (object) ($this->params ?? []),
            // Para a lista: "Liga" => "Liga do Rachão", "Competição" => "Fantasy".
            'params_display' => (object) ($condition ? app(ConditionCatalog::class)->describe($condition, $this->params ?? []) : []),
            'secret' => $this->secret,
            'hint' => $this->hint,
            'active' => $this->active,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'rewards' => $this->rewards->map(fn ($c) => [
                'key' => $c->key, 'name' => $c->name, 'slot' => $c->slot->value, 'rarity' => $c->rarity,
                'asset_key' => $c->asset_key, 'color' => $c->color, 'image_url' => $c->image_path, 'active' => $c->active,
            ])->values(),
        ];
    }
}
