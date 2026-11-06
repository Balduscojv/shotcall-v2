<?php

namespace App\Services\Missions;

use App\Services\Missions\Conditions\CestinhaPointsCondition;
use App\Services\Missions\Conditions\ClutchCondition;
use App\Services\Missions\Conditions\Condition;
use App\Services\Missions\Conditions\FriendsLeaguesCondition;
use App\Services\Missions\Conditions\LeagueRankCondition;
use App\Services\Missions\Conditions\PerfectShotCallsCondition;
use App\Services\Missions\Conditions\RoundPointsCondition;

/**
 * Tipos de condição que a moderação pode usar ao criar missões. Tipo novo: uma classe em
 * Conditions/ e uma linha aqui.
 */
class ConditionCatalog
{
    /** @var list<class-string<Condition>> */
    private const CONDITIONS = [
        ClutchCondition::class,
        CestinhaPointsCondition::class,
        RoundPointsCondition::class,
        PerfectShotCallsCondition::class,
        FriendsLeaguesCondition::class,
        LeagueRankCondition::class,
    ];

    /** @return array<string, Condition> por chave */
    public function all(): array
    {
        $all = [];
        foreach (self::CONDITIONS as $class) {
            $condition = app($class);
            $all[$condition->key()] = $condition;
        }

        return $all;
    }

    public function find(string $key): ?Condition
    {
        return $this->all()[$key] ?? null;
    }

    /** @return list<string> chaves das condições avaliadas neste evento */
    public function keysFor(string $event): array
    {
        return array_keys(array_filter($this->all(), fn (Condition $c) => in_array($event, $c->events(), true)));
    }

    /**
     * Parâmetros completos para salvar: o que veio, com o padrão no que faltou.
     *
     * @param  array<string, mixed>  $given
     * @return array<string, int>
     */
    public function normalize(Condition $condition, array $given): array
    {
        $params = [];
        foreach ($condition->params() as $p) {
            $params[$p['name']] = (int) ($given[$p['name']] ?? $p['default']);
        }

        return $params;
    }
}
