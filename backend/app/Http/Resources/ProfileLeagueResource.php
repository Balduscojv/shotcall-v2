<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Liga na aba do perfil. Espera members_count e o pivot de league_members. @mixin \App\Models\League */
class ProfileLeagueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'color' => $this->color,
            'is_global' => $this->is_global,
            'privacy' => $this->privacy,
            'competitions' => $this->competitions,
            'members_count' => $this->members_count,
            'max_members' => $this->max_members,
            'role' => $this->pivot?->role,
            'rank' => $this->rank,
            'finished' => $this->finished_at !== null,
        ];
    }
}
