<?php

namespace App\Integrations\Nba;

/**
 * Fonte de dados da NBA. Trocar de fornecedor = escrever outro adapter e apontar
 * NBA_PROVIDER (config/services.php) para ele. Tudo volta já normalizado para o nosso modelo.
 */
interface GameDataProvider
{
    /**
     * Calendário da temporada.
     *
     * @return list<array{id: string, phase: string, start_time: string, status: string, arena: ?string, national_tv: ?string, home: array{id: int, tricode: string, city: string, name: string}, away: array{id: int, tricode: string, city: string, name: string}}>
     */
    public function schedule(): array;

    /**
     * Placar, quartos e estatísticas por jogador.
     *
     * @return array{id: string, status: string, period: int, clock: ?string, home_score: int, away_score: int, periods: list<array{period: int, home_points: int, away_points: int}>, players: list<array<string, mixed>>}
     */
    public function boxScore(string $gameId): array;

    /**
     * Jogadas depois de $afterActionNumber, com o placar logo depois de cada uma.
     *
     * @return list<array{action_number: int, period: int, clock: string, team_id: ?int, player_id: ?int, action_type: string, shot_result: ?string, x: ?float, y: ?float, points: int, score_home: ?int, score_away: ?int, description: ?string}>
     */
    public function playByPlay(string $gameId, int $afterActionNumber = 0): array;

    /**
     * Elencos atuais dos 30 times: nome real, posição (G, F, C), posição detalhada (PG, SG, SF, PF, C) e número. Fonte sem elenco devolve [].
     *
     * @return list<array{id: int, team_id: int, full_name: string, position: ?string, role: ?string, jersey: ?string}>
     */
    public function rosters(): array;
}
