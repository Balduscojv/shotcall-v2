<?php

namespace App\Services\Missions\Conditions;

use App\Models\Mission;

/**
 * Tipo de condição de missão. A lógica fica aqui; a moderação escolhe o tipo e ajusta os
 * parâmetros (params) ao criar a missão.
 */
interface Condition
{
    /** Chave salva na missão (ex.: clutch). */
    public function key(): string;

    /** Nome no formulário da moderação. */
    public function label(): string;

    /** O que precisa acontecer, explicado para quem cria a missão. */
    public function description(): string;

    /**
     * Parâmetros que a moderação ajusta. Todos inteiros.
     *
     * @return list<array{name: string, label: string, help?: string, min: int, max: int, default: int}>
     */
    public function params(): array;

    /** @return list<string> eventos em que é avaliada (MissionRunner::GAME_FINISHED, ...) */
    public function events(): array;

    /**
     * Quem cumpriu a missão neste evento, com o motivo que aparece no card do prêmio.
     *
     * @return array<int, string> id do usuário => motivo
     */
    public function achievers(Mission $mission, string $event, mixed $subject): array;
}
