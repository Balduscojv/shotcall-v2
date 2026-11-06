<?php

namespace App\Http\Resources;

use App\Models\League;
use App\Policies\LeaguePolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Liga vista por alguém: dados do escudo, meu papel e o que posso fazer nela. O código de convite
 * só vai para quem participa. Espera members_count. @mixin League
 */
class LeagueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $me = $request->user();
        $policy = app(LeaguePolicy::class);
        $role = $policy->roleOf($me, $this->resource);

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'color' => $this->color,
            'description' => $this->description,
            'privacy' => $this->privacy,
            'competitions' => $this->competitions,
            'is_global' => $this->is_global,
            'members_count' => $this->members_count,
            'max_members' => $this->max_members,
            'role' => $role,
            'invite_code' => $this->when($role !== null && ! $this->is_global && $this->finished_at === null, $this->invite_code),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'can' => [
                'update' => $policy->update($me, $this->resource),
                'moderate' => $policy->moderate($me, $this->resource),
                'change_roles' => $policy->changeRoles($me, $this->resource),
                'delete' => $policy->delete($me, $this->resource),
                'join' => $role === null && ! $this->is_global && $this->privacy === 'PUBLIC' && $this->finished_at === null,
                'leave' => $role !== null && ! $this->is_global && $role !== 'OWNER' && $this->finished_at === null,
            ],
        ];
    }
}
