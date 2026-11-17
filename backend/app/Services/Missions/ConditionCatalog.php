<?php

namespace App\Services\Missions;

use App\Models\League;
use App\Services\Missions\Conditions\CestinhaPointsCondition;
use App\Services\Missions\Conditions\ClutchCondition;
use App\Services\Missions\Conditions\Condition;
use App\Services\Missions\Conditions\EmailVerifiedCondition;
use App\Services\Missions\Conditions\FriendsLeaguesCondition;
use App\Services\Missions\Conditions\LeagueRankCondition;
use App\Services\Missions\Conditions\LeagueSeasonCondition;
use App\Services\Missions\Conditions\LeagueWinsCondition;
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
        LeagueWinsCondition::class,
        LeagueSeasonCondition::class,
        EmailVerifiedCondition::class,
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
     * Texto de cada parâmetro para a lista da moderação: o nome da opção ou da liga, não o número.
     *
     * @param  array<string, int>  $params
     * @return array<string, string> rótulo do parâmetro => valor legível
     */
    public function describe(Condition $condition, array $params): array
    {
        $out = [];
        foreach ($condition->params() as $p) {
            $value = (int) ($params[$p['name']] ?? $p['default']);
            $out[$p['label']] = match ($p['type'] ?? 'int') {
                'choice' => collect($p['options'])->firstWhere('value', $value)['label'] ?? (string) $value,
                'league' => $value === 0 ? 'Qualquer liga' : (League::whereKey($value)->value('name') ?? 'Liga excluída'),
                default => (string) $value,
            };
        }

        return $out;
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
