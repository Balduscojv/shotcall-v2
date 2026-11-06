<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'handle', 'email', 'password', 'avatar_url'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** `role` fica fora do Fillable de propósito: ninguém escolhe o próprio papel no cadastro. */
    protected $attributes = [
        'role' => 'player',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool { return $this->role === UserRole::Admin; }

    public function isModerator(): bool { return $this->role === UserRole::Moderator; }

    public function shotCalls(): HasMany { return $this->hasMany(GameShotCall::class); }

    public function fantasyTeams(): HasMany { return $this->hasMany(FantasyTeam::class); }

    public function leagues(): BelongsToMany
    {
        return $this->belongsToMany(League::class, 'league_members')->withPivot('role', 'joined_round_number')->withTimestamps();
    }

    public function cosmetics(): BelongsToMany
    {
        return $this->belongsToMany(Cosmetic::class, 'user_cosmetics')->withPivot('unlocked_at', 'unlock_reason', 'equipped');
    }
}
