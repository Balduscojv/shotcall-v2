<?php

namespace App\Services\Missions\Conditions;

use App\Models\League;
use App\Models\Mission;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtros de liga comuns às condições de liga: uma liga específica (ou qualquer uma), a
 * competição (fantasy, shot calls ou qualquer) e o mínimo de membros. Os valores ficam inteiros
 * nos params da missão, como os outros parâmetros.
 */
trait LeagueFilters
{
    /** Valores do parâmetro competition. */
    private const COMPETITION_CODES = [1 => 'FANTASY', 2 => 'SHOTCALL'];

    /** @return array<string, mixed> parâmetro "liga específica" (0 = qualquer) */
    protected function leagueParam(): array
    {
        return [
            'name' => 'league_id', 'label' => 'Liga', 'type' => 'league', 'min' => 0, 'max' => PHP_INT_MAX, 'default' => 0,
            'help' => 'Qualquer liga ou uma específica (ex.: liga patrocinada).',
        ];
    }

    /** @return array<string, mixed> parâmetro "competição" (0 = qualquer) */
    protected function competitionParam(): array
    {
        return [
            'name' => 'competition', 'label' => 'Competição', 'type' => 'choice', 'min' => 0, 'max' => 2, 'default' => 0,
            'options' => [['value' => 0, 'label' => 'Qualquer'], ['value' => 1, 'label' => 'Fantasy'], ['value' => 2, 'label' => 'Shot calls']],
        ];
    }

    /** @return array<string, mixed> */
    protected function minMembersParam(int $default = 5): array
    {
        return ['name' => 'min_members', 'label' => 'Membros na liga (mínimo)', 'min' => 2, 'max' => 20, 'default' => $default];
    }

    /** A liga que terminou é a que a missão pede (ou a missão vale para qualquer uma)? */
    protected function leagueMatches(Mission $mission, League $league): bool
    {
        $wanted = (int) $mission->param('league_id', 0);

        return $wanted === 0 || $wanted === $league->id;
    }

    /** Aplica competição e mínimo de membros numa consulta de troféus. */
    protected function filterTrophies(Builder $query, Mission $mission, int $defaultMinMembers = 5): Builder
    {
        $competition = self::COMPETITION_CODES[(int) $mission->param('competition', 0)] ?? null;

        return $query
            ->where('members_count', '>=', $mission->param('min_members', $defaultMinMembers))
            ->when($competition, fn ($q) => $q->where('competition', $competition));
    }
}
