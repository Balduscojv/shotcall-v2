<?php

namespace App\Services\Missions\Conditions;

use App\Models\League;
use App\Models\LeagueTrophy;
use App\Models\Mission;
use App\Services\Missions\MissionRunner;

/**
 * Terminou a temporada da liga até a posição N, numa liga com pelo menos M membros (plano:
 * "Prêmios de liga"). Campeão = posição 1 e 5 membros; pódio = até a 3ª e 10 membros.
 * O mínimo de membros evita liga de uma pessoa só para farmar prêmio. Pode valer só para uma
 * liga (ex.: prêmio da liga patrocinada) e só para uma competição.
 *
 * Lê os troféus que LeagueSeasonCloser grava antes de rodar as missões. Liga que disputa
 * fantasy e shot calls tem pódio em cada uma; vale a melhor colocação.
 */
class LeagueRankCondition implements Condition
{
    use LeagueFilters;

    private const LABELS = ['FANTASY' => 'Fantasy', 'SHOTCALL' => 'Shot calls'];

    public function key(): string
    {
        return 'league_rank';
    }

    public function label(): string
    {
        return 'Colocação final na liga';
    }

    public function description(): string
    {
        return 'Terminou a temporada de uma liga até a posição escolhida, numa liga com o mínimo de membros. Dá para limitar a uma liga e a uma competição. A Liga Global não conta.';
    }

    public function params(): array
    {
        return [
            ['name' => 'max_rank', 'label' => 'Até a posição', 'help' => '1 = só o campeão; 3 = pódio.', 'min' => 1, 'max' => 3, 'default' => 1],
            $this->minMembersParam(),
            $this->competitionParam(),
            $this->leagueParam(),
        ];
    }

    public function events(): array
    {
        return [MissionRunner::LEAGUE_FINISHED];
    }

    public function achievers(Mission $mission, string $event, mixed $subject): array
    {
        /** @var League $subject */
        if (! $this->leagueMatches($mission, $subject)) {
            return [];
        }

        $trophies = $this->filterTrophies(LeagueTrophy::with('season')->where('league_id', $subject->id), $mission)
            ->where('rank', '<=', $mission->param('max_rank', 1))
            ->orderBy('rank')
            ->get();

        $winners = [];
        foreach ($trophies as $trophy) {
            $place = $trophy->rank === 1 ? "Campeão da {$trophy->league_name}" : "{$trophy->rank}º na {$trophy->league_name}";
            $winners[$trophy->user_id] ??= "{$place} · ".self::LABELS[$trophy->competition]." {$trophy->season->label}";
        }

        return $winners;
    }
}
