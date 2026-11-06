<?php

namespace App\Services\Missions\Conditions;

use App\Models\League;
use App\Models\LeagueTrophy;
use App\Models\Mission;
use App\Services\Missions\MissionRunner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Contador de títulos: foi o 1º colocado N vezes (ou somou N pódios, até a posição escolhida) em
 * qualquer liga encerrada, contando todas as ligas e temporadas da pessoa. Ex.: "seja o primeiro
 * colocado 3x" = 3 títulos. Cada ranking conta: campeão de fantasy e de shot calls na mesma liga
 * são dois títulos.
 *
 * Avaliada quando uma liga termina, para os membros dela (só a contagem deles mudou), e também
 * ao criar/ligar a missão, para quem já tinha os títulos (Retroactive).
 */
class LeagueWinsCondition implements Condition, Retroactive
{
    use LeagueFilters;

    public function key(): string
    {
        return 'league_wins';
    }

    public function label(): string
    {
        return 'Títulos de liga (contador de 1º lugar)';
    }

    public function description(): string
    {
        return 'Foi o primeiro colocado N vezes em qualquer liga encerrada (ex.: 3 para "seja o primeiro colocado 3x"), somando todas as temporadas. Dá para contar pódios em vez de títulos. Campeão de fantasy e de shot calls na mesma liga conta como dois. Quem já tem os títulos ganha assim que a missão é criada. A Liga Global não conta.';
    }

    public function params(): array
    {
        return [
            ['name' => 'min_wins', 'label' => 'Títulos (mínimo)', 'help' => 'Ex.: 3 para "primeiro colocado 3x".', 'min' => 1, 'max' => 100, 'default' => 3],
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
        // Só quem acabou de somar um título nesta liga pode ter chegado ao número agora.
        $candidates = $this->trophies($mission)->where('league_id', $subject->id)->pluck('user_id')->unique()->values();

        return $candidates->isEmpty() ? [] : $this->reached($mission, $candidates);
    }

    public function currentAchievers(Mission $mission): array
    {
        return $this->reached($mission);
    }

    /**
     * @param  Collection<int, int>|null  $candidates  null = todo mundo
     * @return array<int, string> id do usuário => motivo
     */
    private function reached(Mission $mission, ?Collection $candidates = null): array
    {
        $minWins = (int) $mission->param('min_wins', 3);
        $what = (int) $mission->param('max_rank', 1) === 1 ? 'títulos' : 'pódios';

        return $this->trophies($mission)
            ->when($candidates, fn ($q) => $q->whereIn('user_id', $candidates))
            ->selectRaw('user_id, count(*) as wins')
            ->groupBy('user_id')
            ->havingRaw('count(*) >= ?', [$minWins])
            ->pluck('wins', 'user_id')
            ->map(fn ($wins) => "{$wins} {$what} de liga")
            ->all();
    }

    /** Troféus que contam para a missão: competição, mínimo de membros e até a posição. */
    private function trophies(Mission $mission): Builder
    {
        return $this->filterTrophies(LeagueTrophy::query(), $mission)
            ->where('rank', '<=', (int) $mission->param('max_rank', 1));
    }
}
