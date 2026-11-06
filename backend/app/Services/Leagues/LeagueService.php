<?php

namespace App\Services\Leagues;

use App\Models\League;
use App\Models\Round;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Regras das ligas criadas pelos usuários: criar, entrar (por código ou na pública), sair,
 * remover membro, trocar papel, transferir e gerar código novo. A Liga Global fica de fora:
 * todo mundo entra nela no cadastro e ninguém sai.
 */
class LeagueService
{
    public const MAX_MEMBERS = 20;

    /** Ligas por usuário, sem contar a global. */
    public const MAX_LEAGUES = 10;

    /** Sem 0/O e 1/I, que se confundem quando alguém dita o código. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** @param array{name: string, color: string, description?: ?string, privacy: string, competitions: list<string>} $data */
    public function create(User $owner, array $data): League
    {
        $this->assertRoomForAnotherLeague($owner);

        return DB::transaction(function () use ($owner, $data) {
            $league = League::create([
                ...$data,
                'slug' => $this->slugFor($data['name']),
                'owner_id' => $owner->id,
                'invite_code' => $this->newCode(),
                'max_members' => self::MAX_MEMBERS,
            ]);
            $league->members()->attach($owner->id, ['role' => 'OWNER', 'joined_round_number' => $this->joinRoundNumber()]);

            return $league;
        });
    }

    public function join(User $user, League $league): void
    {
        if ($league->is_global) {
            throw ValidationException::withMessages(['code' => 'Todo mundo já está na Liga Global.']);
        }
        if ($league->hasMember($user)) {
            return;
        }
        if ($league->isFull()) {
            throw ValidationException::withMessages(['code' => 'Essa liga está cheia.']);
        }
        $this->assertRoomForAnotherLeague($user);

        $league->members()->attach($user->id, ['role' => 'MEMBER', 'joined_round_number' => $this->joinRoundNumber()]);
    }

    /** Sair por conta própria. O dono só sai depois de passar a liga para alguém (ou excluí-la). */
    public function leave(User $user, League $league): void
    {
        if ($league->is_global) {
            throw ValidationException::withMessages(['league' => 'Não dá para sair da Liga Global.']);
        }
        if ($league->owner_id === $user->id) {
            throw ValidationException::withMessages(['league' => 'Passe a liga para outro membro antes de sair, ou exclua a liga.']);
        }

        $league->members()->detach($user->id);
    }

    public function remove(League $league, User $member): void
    {
        $league->members()->detach($member->id);
    }

    /** Novo papel de um membro. OWNER transfere a liga: o dono antigo vira admin. */
    public function changeRole(League $league, User $member, string $role): void
    {
        DB::transaction(function () use ($league, $member, $role) {
            if ($role === 'OWNER') {
                $league->members()->updateExistingPivot($league->owner_id, ['role' => 'ADMIN']);
                $league->update(['owner_id' => $member->id]);
            }
            $league->members()->updateExistingPivot($member->id, ['role' => $role]);
        });
    }

    /** Código novo: o antigo para de funcionar na hora. */
    public function regenerateCode(League $league): string
    {
        $league->update(['invite_code' => $this->newCode()]);

        return $league->invite_code;
    }

    /**
     * Quem entra pontua a partir da rodada que ainda não começou: a atual, se o mercado dela
     * ainda está aberto; senão, a seguinte.
     */
    public function joinRoundNumber(): ?int
    {
        $current = Round::current();
        if (! $current) {
            return null;
        }

        return in_array($current->market_status, [Round::OPEN, Round::UPCOMING], true) ? $current->number : $current->number + 1;
    }

    public function leaguesCount(User $user): int
    {
        return $user->leagues()->where('is_global', false)->count();
    }

    private function assertRoomForAnotherLeague(User $user): void
    {
        if ($this->leaguesCount($user) >= self::MAX_LEAGUES) {
            throw ValidationException::withMessages([
                'league' => 'Você já está em '.self::MAX_LEAGUES.' ligas, o máximo. Saia de uma para entrar em outra.',
            ]);
        }
    }

    private function slugFor(string $name): string
    {
        $base = Str::limit(Str::slug($name), 30, '') ?: 'liga';
        do {
            $slug = $base.'-'.Str::lower(Str::random(4));
        } while (League::where('slug', $slug)->exists());

        return $slug;
    }

    private function newCode(): string
    {
        do {
            $code = collect(range(1, 6))->map(fn () => self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)])->join('');
        } while (League::where('invite_code', $code)->exists());

        return $code;
    }
}
