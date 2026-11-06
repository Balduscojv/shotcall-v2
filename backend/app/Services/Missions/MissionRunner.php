<?php

namespace App\Services\Missions;

use App\Models\Cosmetic;
use App\Models\Game;
use App\Models\User;
use App\Services\Cosmetics\AwardService;

/**
 * Avalia as missões secretas de um jogo que terminou e libera os prêmios de quem cumpriu.
 * Pode rodar de novo no mesmo jogo sem problema: quem já tem o prêmio não muda nada.
 *
 * Quem chama: a leitura dos jogos da NBA, quando o jogo vira FINAL (ainda por portar do
 * repositório antigo), e o comando missions:run para rodar na mão.
 */
class MissionRunner
{
    /** @var list<class-string<Mission>> */
    private const MISSIONS = [
        ClutchMission::class,
    ];

    public function __construct(private AwardService $awards) {}

    /** @return int quantos prêmios foram liberados */
    public function gameFinished(Game $game): int
    {
        if ($game->status !== 'FINAL') {
            return 0;
        }

        $granted = 0;
        foreach (self::MISSIONS as $class) {
            $mission = app($class);
            $cosmetics = Cosmetic::triggeredBy($mission->trigger())->get();
            if ($cosmetics->isEmpty()) {
                continue;
            }

            $userIds = $mission->achieversOf($game);
            if ($userIds->isEmpty()) {
                continue;
            }

            $reason = $mission->reason($game);
            User::whereIn('id', $userIds)->each(function (User $user) use ($cosmetics, $reason, &$granted) {
                foreach ($cosmetics as $cosmetic) {
                    $granted += (int) $this->awards->grant($user, $cosmetic, $reason);
                }
            });
        }

        return $granted;
    }
}
