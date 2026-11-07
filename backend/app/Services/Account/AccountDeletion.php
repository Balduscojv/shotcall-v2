<?php

namespace App\Services\Account;

use App\Models\League;
use App\Models\User;
use App\Services\Profile\ProfileImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Exclusão de conta (LGPD): apaga o usuário e tudo que é dele (palpites, times, prêmios, amizades,
 * notificações, fotos). As ligas que ele criou passam para outro membro; liga sem mais ninguém
 * é excluída. Os rankings são calculados na hora, então ele some deles junto.
 */
class AccountDeletion
{
    public function __construct(private ProfileImage $images) {}

    public function delete(User $user): void
    {
        if (League::where('is_global', true)->where('owner_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['account' => 'A conta de sistema, dona da Liga Global, não pode ser excluída.']);
        }

        DB::transaction(function () use ($user) {
            League::where('owner_id', $user->id)->get()->each(fn (League $league) => $this->handOver($league, $user));

            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
            $user->delete();
        });

        // Arquivos só depois do commit: se algo falhar antes, a foto continua lá.
        foreach ([ProfileImage::AVATAR, ProfileImage::BANNER] as $kind) {
            $this->images->forgetFile($user, $kind);
        }
    }

    /** Passa a liga para o admin mais antigo, senão para o membro mais antigo; sozinho, exclui. */
    private function handOver(League $league, User $owner): void
    {
        $heir = $league->members()
            ->where('users.id', '!=', $owner->id)
            ->orderByRaw("case when league_members.role = 'ADMIN' then 0 else 1 end")
            ->orderBy('league_members.created_at')
            ->first();

        if (! $heir) {
            $league->delete();

            return;
        }

        $league->members()->updateExistingPivot($heir->id, ['role' => 'OWNER']);
        $league->update(['owner_id' => $heir->id]);
    }
}
