<?php

namespace App\Integrations\Nba;

use App\Models\Team;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Adapter para a API pública (não documentada) do site da ESPN.
 * Atenção: não é oficial, não tem garantia e os termos da ESPN restringem uso comercial.
 * Boa para desenvolver e para o beta; antes de monetizar, trocar por um fornecedor licenciado.
 *
 * Ids: jogos e jogadores usam os ids da ESPN; times são traduzidos para os nossos (ids da NBA)
 * pela sigla. Coordenadas de arremesso vêm em pés, em meia quadra; aqui viram % da quadra
 * inteira, com o visitante atacando a cesta da esquerda e o mandante a da direita.
 */
final class EspnClient implements GameDataProvider
{
    private const BASE = 'https://site.api.espn.com/apis/site/v2/sports/basketball/nba';

    /** Siglas da ESPN que diferem das nossas. */
    private const TRICODES = ['GS' => 'GSW', 'NO' => 'NOP', 'NY' => 'NYK', 'SA' => 'SAS', 'UTAH' => 'UTA', 'WSH' => 'WAS'];

    /** @var array<string, int>|null id da ESPN -> id do nosso time */
    private ?array $teamIds = null;

    /** Último resumo de jogo buscado: placar e jogadas saem da mesma resposta. */
    private ?array $summary = null;

    public function schedule(): array
    {
        $games = [];
        foreach ($this->espnTeams() as $espnId => $team) {
            // Temporada regular (padrão) e playoffs (seasontype=3) do calendário de cada time.
            foreach ([null, 3] as $type) {
                $events = $this->get("/teams/{$espnId}/schedule", $type ? ['seasontype' => $type] : [])['events'] ?? [];
                foreach ($events as $e) {
                    $games[$e['id']] ??= $this->scheduleEntry($e);
                }
            }
        }

        return array_values(array_filter($games));
    }

    public function boxScore(string $gameId): array
    {
        $summary = $this->summary($gameId);
        $comp = $summary['header']['competitions'][0] ?? [];
        $sides = collect($comp['competitors'] ?? [])->keyBy('homeAway');
        $status = $comp['status'] ?? [];

        $periods = [];
        foreach ($sides['home']['linescores'] ?? [] as $i => $line) {
            $periods[] = [
                'period' => $i + 1,
                'home_points' => (int) ($line['displayValue'] ?? 0),
                'away_points' => (int) ($sides['away']['linescores'][$i]['displayValue'] ?? 0),
            ];
        }

        $players = [];
        foreach ($summary['boxscore']['players'] ?? [] as $teamBox) {
            $teamId = $this->teamId($teamBox['team']['id'] ?? null);
            foreach ($teamBox['statistics'] ?? [] as $group) {
                $labels = $group['labels'] ?? [];
                foreach ($group['athletes'] ?? [] as $row) {
                    $a = $row['athlete'] ?? [];
                    $s = array_combine($labels, array_pad($row['stats'] ?? [], count($labels), null)) ?: [];
                    [$fgm, $fga] = self::pair($s['FG'] ?? null);
                    [$fg3m] = self::pair($s['3PT'] ?? null);
                    [$ftm, $fta] = self::pair($s['FT'] ?? null);
                    $played = ! ($row['didNotPlay'] ?? false) && ($s['MIN'] ?? '') !== '' && ($s['MIN'] ?? '0') !== '0';
                    $players[] = [
                        'player_id' => (int) $a['id'],
                        'team_id' => $teamId,
                        'full_name' => $a['displayName'] ?? '',
                        'position' => self::position($a['position']['abbreviation'] ?? null),
                        'jersey' => $a['jersey'] ?? null,
                        'minutes' => $played ? sprintf('%02d:00', (int) $s['MIN']) : null,
                        'pts' => (int) ($s['PTS'] ?? 0),
                        'reb' => (int) ($s['REB'] ?? 0),
                        'ast' => (int) ($s['AST'] ?? 0),
                        'stl' => (int) ($s['STL'] ?? 0),
                        'blk' => (int) ($s['BLK'] ?? 0),
                        'tov' => (int) ($s['TO'] ?? 0),
                        'pf' => (int) ($s['PF'] ?? 0),
                        'tech' => 0,
                        'flagrant' => 0,
                        'fgm' => $fgm, 'fga' => $fga, 'fg3m' => $fg3m, 'ftm' => $ftm, 'fta' => $fta,
                        'plus_minus' => (int) ($s['+/-'] ?? 0),
                    ];
                }
            }
        }

        $state = self::status($status['type'] ?? []);

        return [
            'id' => $gameId,
            'status' => $state,
            'period' => (int) ($status['period'] ?? 0),
            'clock' => $state === 'LIVE' ? self::clock($status['displayClock'] ?? null) : null,
            'home_score' => (int) ($sides['home']['score'] ?? 0),
            'away_score' => (int) ($sides['away']['score'] ?? 0),
            'periods' => $periods,
            'players' => array_values(array_filter($players, fn ($p) => $p['team_id'] !== null)),
        ];
    }

    public function playByPlay(string $gameId, int $afterActionNumber = 0): array
    {
        $summary = $this->summary($gameId);
        $comp = $summary['header']['competitions'][0] ?? [];
        $homeEspn = collect($comp['competitors'] ?? [])->firstWhere('homeAway', 'home')['id'] ?? null;

        $out = [];
        foreach ($summary['plays'] ?? [] as $p) {
            $n = (int) ($p['sequenceNumber'] ?? 0);
            if ($n <= $afterActionNumber) {
                continue;
            }
            $typeText = $p['type']['text'] ?? 'unknown';
            $shooting = (bool) ($p['shootingPlay'] ?? false);
            $type = match (true) {
                $shooting && str_contains($typeText, 'Free Throw') => 'freethrow',
                $shooting && (int) ($p['pointsAttempted'] ?? 0) === 3 => '3pt',
                $shooting => '2pt',
                default => mb_substr(str($typeText)->slug('_')->toString(), 0, 24),
            };
            $espnTeam = $p['team']['id'] ?? null;
            [$x, $y] = $type === '2pt' || $type === '3pt' ? self::spot($p['coordinate'] ?? null, $espnTeam !== null && $espnTeam === $homeEspn) : [null, null];

            $out[] = [
                'action_number' => $n,
                'period' => (int) ($p['period']['number'] ?? 0),
                'clock' => self::clock($p['clock']['displayValue'] ?? null) ?? '',
                'team_id' => $this->teamId($espnTeam),
                'player_id' => isset($p['participants'][0]['athlete']['id']) ? (int) $p['participants'][0]['athlete']['id'] : null,
                'action_type' => $type,
                'shot_result' => $shooting ? (($p['scoringPlay'] ?? false) ? 'MADE' : 'MISSED') : null,
                'x' => $x,
                'y' => $y,
                'points' => ($p['scoringPlay'] ?? false) ? (int) ($p['scoreValue'] ?? 0) : 0,
                'score_home' => isset($p['homeScore']) ? (int) $p['homeScore'] : null,
                'score_away' => isset($p['awayScore']) ? (int) $p['awayScore'] : null,
                'description' => isset($p['text']) ? mb_substr($p['text'], 0, 255) : null,
            ];
        }

        return $out;
    }

    public function rosters(): array
    {
        $players = [];
        foreach ($this->espnTeams() as $espnId => $team) {
            foreach ($this->get("/teams/{$espnId}/roster")['athletes'] ?? [] as $a) {
                $players[] = [
                    'id' => (int) $a['id'],
                    'team_id' => $team,
                    'full_name' => $a['fullName'] ?? $a['displayName'] ?? '',
                    'position' => self::position($a['position']['abbreviation'] ?? null),
                    'jersey' => $a['jersey'] ?? null,
                ];
            }
        }

        return $players;
    }

    /** Uma linha do calendário; null se algum time não é nosso (ex.: jogo de exibição). */
    private function scheduleEntry(array $e): ?array
    {
        $comp = $e['competitions'][0] ?? [];
        $sides = collect($comp['competitors'] ?? [])->keyBy('homeAway');
        $team = function (string $side) use ($sides): ?array {
            $t = $sides[$side]['team'] ?? [];
            $id = $this->teamId($t['id'] ?? null);

            return $id === null ? null : [
                'id' => $id,
                'tricode' => self::TRICODES[$t['abbreviation'] ?? ''] ?? ($t['abbreviation'] ?? ''),
                'city' => $t['location'] ?? '',
                'name' => $t['shortDisplayName'] ?? ($t['name'] ?? ''),
            ];
        };
        [$home, $away] = [$team('home'), $team('away')];
        if ($home === null || $away === null) {
            return null;
        }

        $status = $comp['status']['type'] ?? [];
        $tv = collect($comp['broadcasts'] ?? [])->where('market.type', 'National')->pluck('media.shortName')->filter()->unique()->join(', ');

        return [
            'id' => (string) $e['id'],
            'phase' => match ((int) ($e['seasonType']['type'] ?? 2)) {
                1 => 'PRESEASON',
                3 => 'PLAYOFFS',
                5 => 'PLAYIN',
                default => 'REGULAR',
            },
            'start_time' => $e['date'],
            'status' => ($status['name'] ?? '') === 'STATUS_POSTPONED' ? 'POSTPONED' : self::status($status),
            'arena' => $comp['venue']['fullName'] ?? null,
            'national_tv' => $tv !== '' ? $tv : null,
            'home' => $home,
            'away' => $away,
        ];
    }

    private function summary(string $gameId): array
    {
        // Vale por alguns segundos: boxScore e playByPlay da mesma atualização usam uma chamada só.
        if ($this->summary && $this->summary['id'] === $gameId && $this->summary['at'] > microtime(true) - 5) {
            return $this->summary['json'];
        }
        $json = $this->get('/summary', ['event' => $gameId]);
        $this->summary = ['id' => $gameId, 'at' => microtime(true), 'json' => $json];

        return $json;
    }

    /** @return array<string, int> id da ESPN -> id do nosso time */
    private function espnTeams(): array
    {
        if ($this->teamIds !== null) {
            return $this->teamIds;
        }
        $ours = Team::pluck('id', 'tricode');
        $map = [];
        foreach ($this->get('/teams')['sports'][0]['leagues'][0]['teams'] ?? [] as $row) {
            $t = $row['team'];
            $tricode = self::TRICODES[$t['abbreviation']] ?? $t['abbreviation'];
            if (isset($ours[$tricode])) {
                $map[(string) $t['id']] = (int) $ours[$tricode];
            }
        }

        return $this->teamIds = $map;
    }

    private function teamId(int|string|null $espnId): ?int
    {
        return $espnId === null ? null : ($this->espnTeams()[(string) $espnId] ?? null);
    }

    private function get(string $path, array $query = []): array
    {
        return $this->http()->get(self::BASE.$path, $query)->throw()->json() ?? [];
    }

    private function http(): PendingRequest
    {
        return Http::timeout(10)->retry(2, 500, throw: false)
            ->when(config('services.nba.ca_bundle'), fn (PendingRequest $http, string $ca) => $http->withOptions(['verify' => $ca]))->acceptJson();
    }

    /** pre/in/post -> SCHEDULED/LIVE/FINAL */
    private static function status(array $type): string
    {
        return match ($type['state'] ?? 'pre') {
            'in' => 'LIVE',
            'post' => ($type['completed'] ?? true) ? 'FINAL' : 'POSTPONED',
            default => 'SCHEDULED',
        };
    }

    /** "3:29" -> "03:29"; "45.2" (último minuto) -> "00:45" */
    private static function clock(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('/^(\d+):(\d+)/', $value, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return sprintf('00:%02d', (int) $value);
    }

    /** "7-13" -> [7, 13] */
    private static function pair(?string $value): array
    {
        $parts = explode('-', (string) $value);

        return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
    }

    /** PG/SG -> G, SF/PF -> F, C -> C */
    private static function position(?string $abbr): ?string
    {
        $last = substr(strtoupper((string) $abbr), -1);

        return in_array($last, ['G', 'F', 'C'], true) ? $last : null;
    }

    /**
     * Meia quadra da ESPN (x: 0–50 pés de largura; y: pés a partir da linha de fundo) para % da
     * quadra inteira (94 × 50), na cesta da esquerda (visitante) ou da direita (mandante).
     */
    private static function spot(?array $c, bool $home): array
    {
        $x = $c['x'] ?? null;
        $y = $c['y'] ?? null;
        if (! is_numeric($x) || ! is_numeric($y) || $x < -5 || $x > 55 || $y < -5 || $y > 94) {
            return [null, null];
        }
        $depth = max(0.0, min(47.0, (float) $y));
        $across = max(0.0, min(50.0, (float) $x));
        $length = $home ? 94 - $depth : $depth;

        return [round($length / 94 * 100, 2), round($across / 50 * 100, 2)];
    }
}
