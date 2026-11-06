<?php

namespace App\Integrations\Nba;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Adapter para os JSONs públicos de cdn.nba.com (portado do ShotCall antigo).
 * Atenção: não é uma API oficial; o formato pode mudar sem aviso e o CDN pode recusar
 * (403) dependendo de onde o servidor roda. Os testes simulam as respostas (Http::fake).
 */
final class NbaCdnClient implements GameDataProvider
{
    private const BASE = 'https://cdn.nba.com/static/json';

    public function schedule(): array
    {
        $json = $this->http()->get(self::BASE.'/staticData/scheduleLeagueV2.json')->throw()->json();

        $games = [];
        foreach ($json['leagueSchedule']['gameDates'] ?? [] as $day) {
            foreach ($day['games'] ?? [] as $g) {
                $tv = collect($g['broadcasters']['nationalBroadcasters'] ?? [])->pluck('broadcasterDisplay')->filter()->join(', ');
                $games[] = [
                    'id' => $g['gameId'],
                    'phase' => self::phaseFromGameId($g['gameId']),
                    'start_time' => $g['gameDateTimeUTC'],
                    'status' => self::status((int) ($g['gameStatus'] ?? 1)),
                    'arena' => $g['arenaName'] ?? null ?: null,
                    'national_tv' => $tv !== '' ? $tv : null,
                    'home' => self::team($g['homeTeam'] ?? []),
                    'away' => self::team($g['awayTeam'] ?? []),
                ];
            }
        }

        return $games;
    }

    public function boxScore(string $gameId): array
    {
        $g = $this->http()->get(self::BASE."/liveData/boxscore/boxscore_{$gameId}.json")->throw()->json('game');

        $periods = [];
        foreach ($g['homeTeam']['periods'] ?? [] as $i => $p) {
            $periods[] = [
                'period' => (int) $p['period'],
                'home_points' => (int) $p['score'],
                'away_points' => (int) ($g['awayTeam']['periods'][$i]['score'] ?? 0),
            ];
        }

        $players = [];
        foreach (['homeTeam', 'awayTeam'] as $side) {
            foreach ($g[$side]['players'] ?? [] as $p) {
                $s = $p['statistics'] ?? [];
                // PG/SG -> G, SF/PF -> F, C -> C; "G-F" fica com a primeira.
                $position = substr(explode('-', strtoupper($p['position'] ?? ''))[0], -1);
                $position = in_array($position, ['G', 'F', 'C'], true) ? $position : '';
                $players[] = [
                    'player_id' => (int) $p['personId'],
                    'team_id' => (int) $g[$side]['teamId'],
                    'full_name' => $p['name'] ?? '',
                    'position' => $position !== '' ? $position : null,
                    'jersey' => $p['jerseyNum'] ?? null ?: null,
                    'minutes' => self::minutes($s['minutes'] ?? null),
                    'pts' => (int) ($s['points'] ?? 0),
                    'reb' => (int) ($s['reboundsTotal'] ?? 0),
                    'ast' => (int) ($s['assists'] ?? 0),
                    'stl' => (int) ($s['steals'] ?? 0),
                    'blk' => (int) ($s['blocks'] ?? 0),
                    'tov' => (int) ($s['turnovers'] ?? 0),
                    'pf' => (int) ($s['foulsPersonal'] ?? 0),
                    'tech' => (int) ($s['foulsTechnical'] ?? 0),
                    'flagrant' => (int) ($s['foulsFlagrant'] ?? 0),
                    'fgm' => (int) ($s['fieldGoalsMade'] ?? 0),
                    'fga' => (int) ($s['fieldGoalsAttempted'] ?? 0),
                    'fg3m' => (int) ($s['threePointersMade'] ?? 0),
                    'ftm' => (int) ($s['freeThrowsMade'] ?? 0),
                    'fta' => (int) ($s['freeThrowsAttempted'] ?? 0),
                    'plus_minus' => (int) round((float) ($s['plusMinusPoints'] ?? 0)),
                ];
            }
        }

        return [
            'id' => $g['gameId'],
            'status' => self::status((int) ($g['gameStatus'] ?? 1)),
            'period' => (int) ($g['period'] ?? 0),
            'clock' => self::clock($g['gameClock'] ?? null),
            'home_score' => (int) ($g['homeTeam']['score'] ?? 0),
            'away_score' => (int) ($g['awayTeam']['score'] ?? 0),
            'periods' => $periods,
            'players' => $players,
        ];
    }

    public function playByPlay(string $gameId, int $afterActionNumber = 0): array
    {
        $actions = $this->http()->get(self::BASE."/liveData/playbyplay/playbyplay_{$gameId}.json")->throw()->json('game.actions') ?? [];

        $out = [];
        foreach ($actions as $a) {
            if ((int) $a['actionNumber'] <= $afterActionNumber) {
                continue;
            }
            $isShot = (int) ($a['isFieldGoal'] ?? 0) === 1 || ($a['actionType'] ?? '') === 'freethrow';
            $out[] = [
                'action_number' => (int) $a['actionNumber'],
                'period' => (int) $a['period'],
                'clock' => self::clock($a['clock'] ?? null) ?? '',
                'team_id' => ! empty($a['teamId']) ? (int) $a['teamId'] : null,
                'player_id' => ! empty($a['personId']) ? (int) $a['personId'] : null,
                'action_type' => $a['actionType'] ?? 'unknown',
                'shot_result' => $isShot && isset($a['shotResult']) ? strtoupper($a['shotResult']) : null,
                'x' => isset($a['x']) ? (float) $a['x'] : null, // 0-100, em % do comprimento da quadra
                'y' => isset($a['y']) ? (float) $a['y'] : null, // 0-100, em % da largura
                'points' => ($a['shotResult'] ?? '') === 'Made' ? (int) ($a['shotValue'] ?? 1) : 0,
                'score_home' => isset($a['scoreHome']) && $a['scoreHome'] !== '' ? (int) $a['scoreHome'] : null,
                'score_away' => isset($a['scoreAway']) && $a['scoreAway'] !== '' ? (int) $a['scoreAway'] : null,
                'description' => isset($a['description']) ? mb_substr($a['description'], 0, 255) : null,
            ];
        }

        return $out;
    }

    /** O CDN não tem endpoint de elenco; os jogadores entram pelo box score. */
    public function rosters(): array
    {
        return [];
    }

    private function http(): PendingRequest
    {
        return Http::timeout(8)->retry(2, 500, throw: false)
            ->when(config('services.nba.ca_bundle'), fn (PendingRequest $http, string $ca) => $http->withOptions(['verify' => $ca]))->withHeaders(['Accept' => 'application/json']);
    }

    /** 001 = pré-temporada, 002 = temporada regular, 004 = playoffs, 005 = play-in. */
    public static function phaseFromGameId(string $id): string
    {
        return match (substr($id, 0, 3)) {
            '001' => 'PRESEASON',
            '004' => 'PLAYOFFS',
            '005' => 'PLAYIN',
            default => 'REGULAR',
        };
    }

    private static function status(int $code): string
    {
        return match ($code) {
            2 => 'LIVE', 3 => 'FINAL', default => 'SCHEDULED'
        };
    }

    /** "PT05M12.00S" -> "05:12" */
    private static function clock(?string $iso): ?string
    {
        if (! $iso || ! preg_match('/PT(\d+)M(\d+)/', $iso, $m)) {
            return null;
        }

        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }

    /** "PT34M12.00S" -> "34:12"; já no formato curto, mantém. */
    private static function minutes(?string $value): ?string
    {
        return self::clock($value) ?? ($value ? mb_substr($value, 0, 8) : null);
    }

    /** @return array{id: int, tricode: string, city: string, name: string} */
    private static function team(array $t): array
    {
        return [
            'id' => (int) ($t['teamId'] ?? 0),
            'tricode' => $t['teamTricode'] ?? '',
            'city' => $t['teamCity'] ?? '',
            'name' => $t['teamName'] ?? '',
        ];
    }
}
