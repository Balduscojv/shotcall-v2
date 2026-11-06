<?php

namespace App\Services\Missions;

use App\Models\Game;
use App\Models\League;
use App\Models\Mission;
use App\Models\Round;
use App\Models\User;
use App\Services\Cosmetics\AwardService;

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
