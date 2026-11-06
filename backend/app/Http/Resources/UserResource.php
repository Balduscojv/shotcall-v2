<?php

namespace App\Http\Resources;

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
        ];
    }
}
