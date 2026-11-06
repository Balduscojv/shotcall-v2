<?php

namespace App\Services\Missions\Conditions;

use App\Models\League;
use App\Models\Mission;
use App\Services\Missions\MissionRunner;
use Illuminate\Support\Facades\DB;

/**
 * Terminou a temporada na liga: todo membro de uma liga encerrada (com o mínimo de membros),
 * qualquer que seja a colocação. Serve para prêmio de participação, como o da liga patrocinada.
 */
class LeagueSeasonCondition implements Condition
{
    use LeagueFilters;

    public function key(): string
    {
        return 'league_season';
    }

    public function label(): string
    {
        return 'Terminou a temporada na liga';
    }

    public function description(): string
    {
        return 'Era membro da liga quando ela terminou a temporada, qualquer que seja a colocação. Bom para prêmio de participação de uma liga específica. A Liga Global não conta.';
    }

    public function params(): array
    {
        return [$this->leagueParam(), $this->minMembersParam(2)];
    }

    public function events(): array
    {
        return [MissionRunner::LEAGUE_FINISHED];
    }

    public function achievers(Mission $mission, string $event, mixed $subject): array
    {
        /** @var League $subject */
        $members = DB::table('league_members')->where('league_id', $subject->id)->pluck('user_id');
        if (! $this->leagueMatches($mission, $subject) || $members->count() < $mission->param('min_members', 2)) {
            return [];
        }

        return $members->mapWithKeys(fn (int $id) => [$id => "Temporada completa na {$subject->name}"])->all();
    }
}
