<?php

namespace App\Services\Missions;

use App\Models\Game;
use App\Models\League;
use App\Models\Mission;
use App\Models\Round;
use App\Models\User;
use App\Services\Cosmetics\AwardService;
use App\Services\Missions\Conditions\Retroactive;

/**
 * Avalia as missões (cadastradas pela moderação) quando algo acontece no app e libera as
 * recompensas de quem cumpriu. Só entram missões ativas, dentro do período, e prêmios ativos.
 * Pode rodar de novo sem problema: quem já tem o prêmio não muda nada.
 */
class MissionRunner
{
    public const GAME_FINISHED = 'game_finished';

    public const ROUND_SETTLED = 'round_settled';

    public const LEAGUE_FINISHED = 'league_finished';

    public const FRIENDSHIP_ACCEPTED = 'friendship_accepted';

    public const EMAIL_VERIFIED = 'email_verified';

    public function __construct(private AwardService $awards, private ConditionCatalog $catalog) {}

    /** @return int quantos prêmios foram liberados */
    public function gameFinished(Game $game): int
    {
        return $game->status === 'FINAL' ? $this->run(self::GAME_FINISHED, $game) : 0;
    }

    public function roundSettled(Round $round): int
    {
        return $this->run(self::ROUND_SETTLED, $round);
    }

    public function leagueFinished(League $league): int
    {
        return ! $league->is_global && $league->finished_at !== null ? $this->run(self::LEAGUE_FINISHED, $league) : 0;
    }

    public function friendshipAccepted(User $a, User $b): int
    {
        return $this->run(self::FRIENDSHIP_ACCEPTED, [$a, $b]);
    }

    public function emailVerified(User $user): int
    {
        return $user->hasVerifiedEmail() ? $this->run(self::EMAIL_VERIFIED, $user) : 0;
    }

    /**
     * Missão recém-criada, editada ou ligada: se a condição dá para conferir a qualquer momento
     * (ex.: contador de títulos), quem já cumpre ganha agora. Quem já tem o prêmio fica como está.
     *
     * @return int quantos prêmios foram liberados
     */
    public function backfill(Mission $mission): int
    {
        $condition = $this->catalog->find($mission->condition);
        if (! $condition instanceof Retroactive || ! Mission::running()->whereKey($mission->id)->exists()) {
            return 0;
        }

        $rewards = $mission->rewards()->active()->get();
        $winners = $rewards->isEmpty() ? [] : $condition->currentAchievers($mission);

        $granted = 0;
        foreach (User::whereIn('id', array_keys($winners))->get() as $user) {
            $owned = $user->cosmetics()->pluck('cosmetics.id');
            foreach ($rewards->whereNotIn('id', $owned) as $cosmetic) {
                $granted += (int) $this->awards->grant($user, $cosmetic, $winners[$user->id]);
            }
        }

        return $granted;
    }

    private function run(string $event, mixed $subject): int
    {
        $missions = Mission::running()
            ->whereIn('condition', $this->catalog->keysFor($event))
            ->with(['rewards' => fn ($q) => $q->active()])
            ->get();

        $granted = 0;
        foreach ($missions as $mission) {
            if ($mission->rewards->isEmpty()) {
                continue;
            }

            $winners = $this->catalog->find($mission->condition)->achievers($mission, $event, $subject);
            if ($winners === []) {
                continue;
            }

            foreach (User::whereIn('id', array_keys($winners))->get() as $user) {
                foreach ($mission->rewards as $cosmetic) {
                    $granted += (int) $this->awards->grant($user, $cosmetic, $winners[$user->id]);
                }
            }
        }

        return $granted;
    }
}
