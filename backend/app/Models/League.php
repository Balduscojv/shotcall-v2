<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class League extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['competitions' => 'array', 'is_global' => 'boolean', 'finished_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_members')->withPivot('role', 'joined_round_number')->withTimestamps();
    }

    public function isFull(): bool
    {
        return $this->members()->count() >= $this->max_members;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }
}
