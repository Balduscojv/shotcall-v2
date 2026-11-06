<?php

namespace App\Services\Missions\Conditions;

use App\Models\League;
use App\Models\LeagueTrophy;
use App\Models\Mission;
use App\Services\Missions\MissionRunner;

/**
 * Contagem de vitórias em ligas: somou N títulos (1º lugar) ou N pódios (até a posição escolhida)
 * em ligas encerradas, contando todas as ligas e temporadas da pessoa. Ex.: Tricampeão = 3
 * títulos em ligas de 5+. Cada ranking conta: campeão de fantasy e de shot calls na mesma liga
 * são duas vitórias.
 *
 * Avaliada quando uma liga termina, para os membros dela (só a contagem deles mudou).
 */
class LeagueWinsCondition implements Condition
{
    use LeagueFilters;

    public function key(): string
    {
        return 'league_wins';
    }

    public function label(): string
    {
        return 'Vitórias em ligas (contagem)';
    }

    public function description(): string
    {
        return 'Somou N vitórias em ligas encerradas (1º lugar, ou até a posição escolhida), em todas as temporadas. Campeão de fantasy e de shot calls na mesma liga conta como duas. A Liga Global não conta.';
    }

    public function params(): array
    {
        return [
            ['name' => 'min_wins', 'label' => 'Vitórias (mínimo)', 'help' => 'Ex.: 3 para tricampeão.', 'min' => 1, 'max' => 100, 'default' => 3],
            ['name' => 'max_rank', 'label' => 'Conta até a posição', 'help' => '1 = só títulos; 3 = pódios.', 'min' => 1, 'max' => 3, 'default' => 1],
            $this->minMembersParam(),
            $this->competitionParam(),
        ];
    }

    public function events(): array
    {
        return [MissionRunner::LEAGUE_FINISHED];
    }

    public function achievers(Mission $mission, string $event, mixed $subject): array
    {
        /** @var League $subject */
        $maxRank = (int) $mission->param('max_rank', 1);
        $minWins = (int) $mission->param('min_wins', 3);

        // Só quem acabou de somar uma vitória nesta liga pode ter chegado ao número agora.
        $candidates = $this->filterTrophies(LeagueTrophy::where('league_id', $subject->id), $mission)
            ->where('rank', '<=', $maxRank)
            ->pluck('user_id')->unique();

        $counts = $this->filterTrophies(LeagueTrophy::whereIn('user_id', $candidates), $mission)
            ->where('rank', '<=', $maxRank)
            ->selectRaw('user_id, count(*) as wins')
            ->groupBy('user_id')
            ->pluck('wins', 'user_id');

        $what = $maxRank === 1 ? 'títulos' : 'pódios';

        return $counts->filter(fn ($wins) => (int) $wins >= $minWins)
            ->map(fn ($wins) => "{$wins} {$what} de liga")
            ->all();
    }
}
