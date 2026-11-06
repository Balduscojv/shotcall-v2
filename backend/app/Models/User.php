<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Services\Leagues\JoinGlobalLeague;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected static function booted(): void
    {
        // Todo usuário está na Liga Global, venha de onde vier (cadastro, login social, moderação).
        static::created(fn (User $user) => app(JoinGlobalLeague::class)($user));

        // As notificações são polimórficas (sem chave estrangeira): saem junto com o usuário.
        static::deleting(fn (User $user) => $user->notifications()->delete());
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isModerator(): bool
    {
        return $this->role === UserRole::Moderator;
    }

    public function shotCalls(): HasMany
    {
        return $this->hasMany(GameShotCall::class);
    }

    public function fantasyTeams(): HasMany
    {
        return $this->hasMany(FantasyTeam::class);
    }

    public function leagues(): BelongsToMany
    {
        return $this->belongsToMany(League::class, 'league_members')->withPivot('role', 'joined_round_number')->withTimestamps();
    }

    /** Prêmios liberados para o usuário (por gatilho ou pela moderação). */
    public function cosmetics(): BelongsToMany
    {
        return $this->belongsToMany(Cosmetic::class, 'user_cosmetics')->withPivot('unlocked_at', 'unlock_reason', 'granted_by');
    }

    public function equippedBanner(): BelongsTo
    {
        return $this->belongsTo(Cosmetic::class, 'equipped_banner_id');
    }

    public function equippedFrame(): BelongsTo
    {
        return $this->belongsTo(Cosmetic::class, 'equipped_frame_id');
    }

    public function equippedTitle(): BelongsTo
    {
        return $this->belongsTo(Cosmetic::class, 'equipped_title_id');
    }

    /** Carrega o que o usuário está usando, para mostrar moldura, título e banner sem N+1. */
    public function scopeWithEquipped(Builder $query): void
    {
        $query->with(['equippedBanner', 'equippedFrame', 'equippedTitle']);
    }

    /** IDs dos prêmios em uso, para marcar "em uso" nas listas. @return list<int> */
    public function equippedIds(): array
    {
        return array_values(array_filter([$this->equipped_banner_id, $this->equipped_frame_id, $this->equipped_title_id]));
    }
}
