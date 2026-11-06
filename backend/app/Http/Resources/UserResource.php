<?php

namespace App\Http\Resources;

use App\Models\Cosmetic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'handle' => $this->handle,
            'email' => $this->when($request->user()?->is($this->resource), $this->email),
            // O papel só aparece para o próprio usuário e para admins.
            'role' => $this->when(
                $request->user()?->is($this->resource) || $request->user()?->isAdmin(),
                fn () => $this->role->value,
            ),
            'avatar_url' => $this->avatar_url,
            'banner_url' => $this->banner_url,
            // Prêmios em uso (só quando a consulta usou withEquipped, para não gerar N+1).
            'equipped' => $this->when($this->relationLoaded('equippedFrame'), fn () => [
                'banner' => $this->equippedBanner ? self::cosmetic($this->equippedBanner) : null,
                'frame' => $this->equippedFrame ? self::cosmetic($this->equippedFrame) : null,
                'title' => $this->equippedTitle ? self::cosmetic($this->equippedTitle) : null,
            ]),
        ];
    }

    /** @return array{key: string, name: string, asset_key: string|null, rarity: string} */
    private static function cosmetic(Cosmetic $cosmetic): array
    {
        return ['key' => $cosmetic->key, 'name' => $cosmetic->name, 'asset_key' => $cosmetic->asset_key, 'rarity' => $cosmetic->rarity];
    }
}
