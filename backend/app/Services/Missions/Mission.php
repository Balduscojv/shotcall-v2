<?php

namespace App\Services\Missions;

use App\Models\Game;
use Illuminate\Support\Collection;

/**
 * Missão secreta: uma façanha num jogo que libera os prêmios ligados ao gatilho dela.
 * Cada missão é avaliada quando o jogo termina (MissionRunner).
 */
interface Mission
{
    /** Gatilho dos prêmios que ela libera (ex.: MISSION:CLUTCH). */
    public function trigger(): string;

    /**
     * Quem cumpriu a missão neste jogo.
     *
     * @return Collection<int, int> ids de usuário
     */
    public function achieversOf(Game $game): Collection;

    /** Motivo que aparece no card do prêmio (ex.: "Missão Clutch · LAL @ BOS"). */
    public function reason(Game $game): string;
}
