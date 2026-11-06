<?php

namespace App\Policies;

use App\Models\League;
use App\Models\User;

/**
 * Papéis da liga (plano: "Criação e acesso"): o dono edita, exclui, troca papéis e transfere;
 * dono e admins removem membros e geram código novo. A Liga Global ninguém edita.
 */
class LeaguePolicy
{
    /** Privada só para quem participa; pública para todos. */
    public function view(User $user, League $league): bool
    {
        return $league->privacy === 'PUBLIC' || $league->hasMember($user);
    }

    /** Liga encerrada fica congelada: só dá para excluir. */
    public function update(User $user, League $league): bool
    {
        return $this->delete($user, $league) && $league->finished_at === null;
    }

    public function delete(User $user, League $league): bool
    {
        return ! $league->is_global && $league->owner_id === $user->id;
    }

    /** Trocar papel e transferir a liga. */
    public function changeRoles(User $user, League $league): bool
    {
        return $this->update($user, $league);
    }

    /** Remover membros e gerar código novo. */
    public function moderate(User $user, League $league): bool
    {
        return ! $league->is_global && $league->finished_at === null
            && in_array($this->roleOf($user, $league), ['OWNER', 'ADMIN'], true);
    }

    /** O dono remove qualquer um; admin, só membros comuns. Ninguém remove o dono nem a si mesmo. */
    public function removeMember(User $user, League $league, User $member): bool
    {
        if (! $this->moderate($user, $league) || $member->id === $league->owner_id || $member->is($user)) {
            return false;
        }

        return $league->owner_id === $user->id || $this->roleOf($member, $league) === 'MEMBER';
    }

    public function roleOf(User $user, League $league): ?string
    {
        return $league->members()->whereKey($user->id)->first()?->pivot->role;
    }
}
