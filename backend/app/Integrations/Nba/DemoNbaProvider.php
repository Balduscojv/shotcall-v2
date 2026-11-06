<?php

namespace App\Integrations\Nba;

use App\Models\Player;
use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * Fonte de demonstração, para desenvolver sem acesso aos dados da NBA (NBA_PROVIDER=demo).
 * Gera cinco jogos por noite, de 7 dias atrás a 7 dias à frente, e simula cada um pelo
 * relógio: antes do horário está agendado, durante ~2h20 fica ao vivo, depois termina.
 * Tudo sai de uma semente por jogo, então a mesma consulta sempre dá o mesmo resultado.
 * Jogadores: os elencos reais do banco (php artisan nba:rosters); sem elenco, fictícios.
 */
final class DemoNbaProvider implements GameDataProvider
{
    private const TZ = 'America/New_York';

    private const GAMES_PER_NIGHT = 5;

    private const TIPOFFS = ['19:00', '19:30', '20:00', '22:00', '22:30'];

    private const REAL_MINUTES = 140;     // duração de um jogo de verdade, com intervalos

    private const GAME_SECONDS = 2880;    // 4 quartos de 12 minutos

    private const EPOCH = '2026-10-01';   // base dos ids

    /** @var array<string, array{meta: array, actions: list<array>}> */
    private array $cache = [];

    public function schedule(): array
    {
        $today = Carbon::now(self::TZ)->startOfDay();
        $games = [];
        for ($d = -7; $d <= 7; $d++) {
            $day = $today->copy()->addDays($d);
            foreach ($this->night($day) as $meta) {
                $games[] = [
                    'id' => $meta['id'],
                    'phase' => 'REGULAR',
                    'start_time' => $meta['start']->utc()->toIso8601String(),
                    'status' => $this->status($meta),
                    'arena' => "{$meta['home']['city']} Arena",
                    'national_tv' => $meta['index'] === 3 ? 'ESPN' : null,
                    'home' => $meta['home'],
                    'away' => $meta['away'],
                ];
            }
        }

        return $games;
    }

    public function boxScore(string $gameId): array
    {
        ['meta' => $meta, 'actions' => $actions] = $this->game($gameId);
        $shown = $this->revealed($meta, $actions);
        $last = end($shown) ?: null;
        $status = $this->status($meta);

        $periods = [];
        foreach ($shown as $a) {
            $p = $a['period'];
            $periods[$p] ??= ['period' => $p, 'home_points' => 0, 'away_points' => 0];
            $periods[$p][$a['team_id'] === $meta['home']['id'] ? 'home_points' : 'away_points'] += $a['points'];
        }

        $progress = $this->progress($meta);
        $players = [];
        foreach (['home', 'away'] as $side) {
            $team = $meta[$side];
            for ($i = 1; $i <= 8; $i++) {
                $member = $this->roster($team['id'])[$i - 1];
                $id = $member['id'];
                $mine = array_filter($shown, fn ($a) => $a['player_id'] === $id);
                $count = fn (string $type, ?string $result = null) => count(array_filter($mine,
                    fn ($a) => $a['action_type'] === $type && ($result === null || $a['shot_result'] === $result)));
                $seed = crc32("{$gameId}-{$id}");
                $players[] = [
                    'player_id' => $id,
                    'team_id' => $team['id'],
                    'full_name' => $member['full_name'],
                    'position' => $member['position'],
                    'jersey' => $member['jersey'],
                    'minutes' => $status === 'SCHEDULED' ? null : sprintf('%02d:00', (int) round(($i <= 5 ? 34 : 14) * $progress)),
                    'pts' => array_sum(array_column($mine, 'points')),
                    'reb' => (int) round(($seed % 11) * $progress),
                    'ast' => (int) round(($seed >> 4) % 9 * $progress),
                    'stl' => (int) round(($seed >> 8) % 3 * $progress),
                    'blk' => (int) round(($seed >> 12) % 3 * $progress),
                    'tov' => (int) round(($seed >> 16) % 4 * $progress),
                    'pf' => (int) round(($seed >> 20) % 5 * $progress),
                    'tech' => 0,
                    'flagrant' => 0,
                    'fgm' => $count('2pt', 'MADE') + $count('3pt', 'MADE'),
                    'fga' => $count('2pt') + $count('3pt'),
                    'fg3m' => $count('3pt', 'MADE'),
                    'ftm' => $count('freethrow', 'MADE'),
                    'fta' => $count('freethrow'),
                    'plus_minus' => 0,
                ];
            }
        }

        return [
            'id' => $gameId,
            'status' => $status,
            'period' => $last['period'] ?? 0,
            'clock' => $status === 'LIVE' ? ($last['clock'] ?? '12:00') : null,
            'home_score' => $last['score_home'] ?? 0,
            'away_score' => $last['score_away'] ?? 0,
            'periods' => array_values($periods),
            'players' => $players,
        ];
    }

    public function playByPlay(string $gameId, int $afterActionNumber = 0): array
    {
        ['meta' => $meta, 'actions' => $actions] = $this->game($gameId);

        $new = array_filter($this->revealed($meta, $actions), fn ($a) => $a['action_number'] > $afterActionNumber);

        // game_second é só da simulação; não vai para o banco.
        return array_values(array_map(fn ($a) => array_diff_key($a, ['game_second' => true]), $new));
    }

    /** Os cinco jogos de uma noite: os times são embaralhados com a data como semente. */
    private function night(Carbon $day): array
    {
        $teams = Team::orderBy('id')->get(['id', 'tricode', 'city', 'name'])->map(fn (Team $t) => [
            'id' => (int) $t->id, 'tricode' => $t->tricode, 'city' => $t->city, 'name' => $t->name,
        ])->all();
        if (count($teams) < self::GAMES_PER_NIGHT * 2) {
            return [];
        }

        mt_srand(crc32($day->toDateString()));
        shuffle($teams);
        mt_srand();

        $dayIndex = (int) Carbon::parse(self::EPOCH, self::TZ)->diffInDays($day, false);
        $out = [];
        for ($i = 0; $i < self::GAMES_PER_NIGHT; $i++) {
            $out[] = [
                'id' => '00226'.sprintf('%05d', max(0, $dayIndex) * 10 + $i),
                'index' => $i,
                'start' => Carbon::parse($day->toDateString().' '.self::TIPOFFS[$i], self::TZ),
                'home' => $teams[$i * 2],
                'away' => $teams[$i * 2 + 1],
            ];
        }

        return $out;
    }

    /** @return array{meta: array, actions: list<array>} */
    private function game(string $gameId): array
    {
        if (isset($this->cache[$gameId])) {
            return $this->cache[$gameId];
        }

        $dayIndex = intdiv((int) substr($gameId, 5), 10);
        $day = Carbon::parse(self::EPOCH, self::TZ)->addDays($dayIndex);
        $meta = collect($this->night($day))->firstWhere('id', $gameId) ?? abort(404, 'Jogo de demonstração não encontrado.');

        return $this->cache[$gameId] = ['meta' => $meta, 'actions' => $this->simulate($gameId, $meta)];
    }

    /** O jogo inteiro, posse a posse, com o placar depois de cada jogada. */
    private function simulate(string $gameId, array $meta): array
    {
        mt_srand(crc32($gameId));
        $home = $meta['home']['id'];
        $away = $meta['away']['id'];
        $score = [$home => 0, $away => 0];
        $actions = [];
        $n = 0;
        $second = 0;
        $team = mt_rand(0, 1) ? $home : $away;

        $push = function (int $teamId, string $type, bool $made, int $value) use (&$actions, &$n, &$second, &$score, $home, $away) {
            $period = min(4, intdiv($second, 720) + 1);
            $remaining = max(0, $period * 720 - $second);
            $player = $this->playerId($teamId, $type === '3pt' ? mt_rand(1, 3) : mt_rand(1, 8));
            if ($made) {
                $score[$teamId] += $value;
            }
            [$x, $y] = $this->spot($teamId === $home, $type, $period);
            $n += 2;
            $actions[] = [
                'action_number' => $n,
                'game_second' => $second,
                'period' => $period,
                'clock' => sprintf('%02d:%02d', intdiv($remaining, 60), $remaining % 60),
                'team_id' => $teamId,
                'player_id' => $player,
                'action_type' => $type,
                'shot_result' => $made ? 'MADE' : 'MISSED',
                'x' => $type === 'freethrow' ? null : $x,
                'y' => $type === 'freethrow' ? null : $y,
                'points' => $made ? $value : 0,
                'score_home' => $score[$home],
                'score_away' => $score[$away],
                'description' => null,
            ];
        };

        while ($second < self::GAME_SECONDS - 4) {
            $second += mt_rand(10, 22);
            if ($second >= self::GAME_SECONDS) {
                break;
            }
            $roll = mt_rand(1, 100);
            if ($roll <= 10) {
                $push($team, 'freethrow', mt_rand(1, 100) <= 78, 1);
                $push($team, 'freethrow', mt_rand(1, 100) <= 78, 1);
            } elseif ($roll <= 45) {
                $push($team, '3pt', mt_rand(1, 100) <= 36, 3);
            } else {
                $push($team, '2pt', mt_rand(1, 100) <= 53, 2);
            }
            $team = $team === $home ? $away : $home;
        }

        // Sem empate no fim (a demonstração não tem prorrogação): a casa converte a última.
        if ($score[$home] === $score[$away]) {
            $second = self::GAME_SECONDS - 2;
            $push($home, '2pt', true, 2);
        }
        mt_srand();

        return $actions;
    }

    /** Onde foi o arremesso, em % da quadra. A casa ataca a cesta da direita no 1º tempo. */
    private function spot(bool $isHome, string $type, int $period): array
    {
        $right = $isHome === ($period <= 2);
        // Bola de 3: do canto (rente à lateral) ou do arco (a uns 25 pés da cesta, em qualquer ângulo).
        if ($type === '3pt' && mt_rand(1, 4) === 1) {
            $depth = mt_rand(0, 9);
            $y = mt_rand(0, 1) ? mt_rand(3, 6) : mt_rand(94, 97);
        } elseif ($type === '3pt') {
            $angle = deg2rad(mt_rand(-65, 65));
            $feet = mt_rand(245, 275) / 10;
            $depth = $feet * cos($angle) / 0.94;
            $y = 50 + $feet * sin($angle) / 0.5;
        } else {
            $depth = mt_rand(1, 16);
            $y = mt_rand(30, 70);
        }
        $x = $right ? 94.4 - $depth : 5.6 + $depth;

        return [round($x, 1), (float) $y];
    }

    private function revealed(array $meta, array $actions): array
    {
        $upTo = $this->progress($meta) * self::GAME_SECONDS;

        return array_values(array_filter($actions, fn ($a) => $a['game_second'] <= $upTo));
    }

    /** 0 antes de começar, 1 depois de terminar. */
    private function progress(array $meta): float
    {
        $elapsed = $meta['start']->diffInSeconds(Carbon::now(), false);

        return max(0.0, min(1.0, $elapsed / (self::REAL_MINUTES * 60)));
    }

    private function status(array $meta): string
    {
        $p = $this->progress($meta);

        return match (true) {
            $p <= 0.0 => 'SCHEDULED',
            $p >= 1.0 => 'FINAL',
            default => 'LIVE',
        };
    }

    /** A demonstração não tem elenco próprio: usa o que já estiver no banco (nba:rosters). */
    public function rosters(): array
    {
        return [];
    }

    private function playerId(int $teamId, int $i): int
    {
        return $this->roster($teamId)[$i - 1]['id'];
    }

    /** @var array<int, list<array{id: int, full_name: string, position: ?string, jersey: ?string}>> */
    private array $rosters = [];

    /**
     * Os 8 jogadores do time na simulação: os reais do banco (2 armadores, 2 alas e 1 pivô de
     * titulares, depois o banco); faltando gente, completa com fictícios ("Jogador 3 · BOS").
     */
    private function roster(int $teamId): array
    {
        if (isset($this->rosters[$teamId])) {
            return $this->rosters[$teamId];
        }

        $real = Player::where('team_id', $teamId)->where('active', true)->where('id', '<', 900_000_000)
            ->orderBy('id')->get(['id', 'full_name', 'position', 'jersey']);
        $pick = collect();
        foreach (['G' => 2, 'F' => 2, 'C' => 1] as $pos => $n) {
            $pick = $pick->concat($real->where('position', $pos)->whereNotIn('id', $pick->pluck('id'))->take($n));
        }
        $pick = $pick->concat($real->whereNotIn('id', $pick->pluck('id')))->take(8)->values();

        $tricode = Team::whereKey($teamId)->value('tricode');
        $out = [];
        for ($i = 1; $i <= 8; $i++) {
            $p = $pick[$i - 1] ?? null;
            $out[] = $p
                ? ['id' => (int) $p->id, 'full_name' => $p->full_name, 'position' => $p->position, 'jersey' => $p->jersey]
                // Fora da faixa de ids reais, para não colidir com jogador de verdade.
                : ['id' => 900_000_000 + ($teamId % 1000) * 100 + $i, 'full_name' => "Jogador {$i} · {$tricode}",
                    'position' => ['G', 'G', 'F', 'F', 'C', 'G', 'F', 'C'][$i - 1], 'jersey' => (string) ($i * 3)];
        }

        return $this->rosters[$teamId] = $out;
    }
}
