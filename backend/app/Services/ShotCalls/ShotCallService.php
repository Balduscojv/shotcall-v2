<?php

namespace App\Services\ShotCalls;

use App\Models\Game;
use App\Models\GameShotCall;
use App\Models\Player;
use App\Models\PlayerGameScore;
use App\Models\User;
use App\Models\UserAccuracy;
use App\Services\Fantasy\GameScoring;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shot call de jogo: vencedor (+1), margem (+2) e MVP do jogo (+3, quem fizer mais pontos
 * fantasy na partida). Cria e edita até a bola subir; depois trava. No fim do jogo, apura
 * e atualiza a taxa de acerto (últimos 20 jogos, mínimo 10) e o nível.
 */
class ShotCallService
{
    public const BANDS = ['M1_5', 'M6_10', 'M11_15', 'M16_PLUS'];

    public function __construct(
        private GameShotCallSettler $settler,
        private AccuracyCalculator $accuracy,
        private TierPolicy $tiers,
        private GameScoring $scoring,
    ) {}

    /** Cria ou edita o palpite. A trava é checada com o jogo bloqueado na transação. */
    public function submit(User $user, Game $game, array $data): GameShotCall
    {
        return DB::transaction(function () use ($user, $game, $data) {
            $game = Game::lockForUpdate()->findOrFail($game->id);
            if ($game->isLocked()) {
                throw ValidationException::withMessages(['game' => 'O jogo já começou: palpite travado.']);
            }

            $teams = [(int) $game->home_team_id, (int) $game->away_team_id];
            if (! in_array((int) $data['winner_team_id'], $teams, true)) {
                throw ValidationException::withMessages(['winner_team_id' => 'Escolha um dos dois times do jogo.']);
            }
            if (! Player::whereKey($data['mvp_player_id'])->whereIn('team_id', $teams)->where('active', true)->exists()) {
                throw ValidationException::withMessages(['mvp_player_id' => 'O MVP precisa ser do elenco de um dos dois times.']);
            }

            return GameShotCall::updateOrCreate(
                ['user_id' => $user->id, 'game_id' => $game->id],
                [
                    'winner_team_id' => (int) $data['winner_team_id'],
                    'margin_band' => $data['margin_band'],
                    'mvp_player_id' => (int) $data['mvp_player_id'],
                    'status' => 'OPEN',
                ],
            );
        });
    }

    /**
     * Quem pode ser MVP: os elencos dos dois times, com a média de pontos fantasy na temporada
     * (dos jogos já apurados), do maior para o menor.
     *
     * @return Collection<int, array{id: int, name: string, team_id: int, position: ?string, jersey: ?string, average: ?float, games: int}>
     */
    public function candidates(Game $game): Collection
    {
        $players = Player::whereIn('team_id', [$game->home_team_id, $game->away_team_id])->where('active', true)
            ->get(['id', 'full_name', 'team_id', 'position', 'jersey']);
        $averages = PlayerGameScore::query()
            ->join('games', 'games.id', '=', 'player_game_scores.game_id')
            ->where('games.season_id', $game->season_id)
            ->whereIn('player_id', $players->pluck('id'))
            ->groupBy('player_id')
            ->selectRaw('player_id, avg(points) as average, count(*) as games')
            ->get()->keyBy('player_id');

        return $players->map(fn (Player $p) => [
            'id' => (int) $p->id,
            'name' => $p->full_name,
            'team_id' => (int) $p->team_id,
            'position' => $p->position,
            'jersey' => $p->jersey,
            'average' => isset($averages[$p->id]) ? round((float) $averages[$p->id]->average, 1) : null,
            'games' => (int) ($averages[$p->id]->games ?? 0),
        ])->sortByDesc(fn ($p) => $p['average'] ?? -1)->values();
    }

    /**
     * Resultado do jogo para a apuração. Com o jogo em andamento, é o "se acabasse agora":
     * quem está na frente, a diferença atual e quem lidera em pontos fantasy.
     *
     * @return array{winner_team_id: ?int, margin: int, mvp_player_ids: list<int>, mvp_points: ?float}
     */
    public function result(Game $game): array
    {
        $points = $game->status === 'FINAL' && PlayerGameScore::where('game_id', $game->id)->exists()
            ? PlayerGameScore::where('game_id', $game->id)->pluck('points', 'player_id')->map(fn ($p) => (float) $p)
            : $this->scoring->current($game);
        $top = $points->max();

        return [
            'winner_team_id' => match (true) {
                $game->home_score > $game->away_score => (int) $game->home_team_id,
                $game->away_score > $game->home_score => (int) $game->away_team_id,
                default => null,
            },
            'margin' => $game->home_score - $game->away_score,
            'mvp_player_ids' => $top === null ? [] : $points->filter(fn ($p) => $p === $top)->keys()->map(fn ($id) => (int) $id)->values()->all(),
            'mvp_points' => $top,
        ];
    }

    /**
     * Mercados do palpite contra o resultado (parcial ou final).
     *
     * @return array{correct: int, total: int, points: int, markets: array<string, bool>}
     */
    public function check(GameShotCall $call, array $result): array
    {
        return $this->settler->settle(
            ['winner_team_id' => (int) $call->winner_team_id, 'margin_band' => $call->margin_band, 'mvp_player_id' => (int) $call->mvp_player_id],
            ['winner_team_id' => $result['winner_team_id'] ?? 0, 'margin' => $result['margin'], 'mvp_player_ids' => $result['mvp_player_ids']],
        );
    }

    /** O jogo começou: os palpites abertos travam. */
    public function lock(Game $game): int
    {
        return GameShotCall::where('game_id', $game->id)->where('status', 'OPEN')->update(['status' => 'LOCKED']);
    }

    /** Fim de jogo: apura os palpites ainda não apurados e atualiza a taxa de quem palpitou. */
    public function settle(Game $game): int
    {
        if ($game->status !== 'FINAL') {
            return 0;
        }
        $result = $this->result($game);
        $settled = 0;

        GameShotCall::where('game_id', $game->id)->whereIn('status', ['OPEN', 'LOCKED'])->get()
            ->each(function (GameShotCall $call) use ($result, &$settled) {
                DB::transaction(function () use ($call, $result) {
                    $r = $this->check($call, $result);
                    $call->update(['status' => 'SETTLED', 'correct' => $r['correct'], 'points' => $r['points'], 'settled_at' => now()]);
                    $this->refreshAccuracy($call->user_id);
                });
                $settled++;
            });

        return $settled;
    }

    /** Taxa do modo jogo: últimos 20 palpites apurados (3 mercados cada), mínimo 10 para contar. */
    public function refreshAccuracy(int $userId): void
    {
        $history = GameShotCall::where('user_id', $userId)->where('status', 'SETTLED')
            ->latest('settled_at')->limit(20)->pluck('correct')
            ->map(fn ($correct) => ['correct' => (int) $correct, 'total' => 3])->all();
        $rate = $this->accuracy->rate($history);

        UserAccuracy::updateOrInsert(['user_id' => $userId, 'mode' => 'GAME'], [
            'rate' => $rate['rate'],
            'counted' => $rate['counted'],
            'tier' => $this->tiers->tier($rate['rate'], $this->accuracy->windowIsFull($rate['counted'])),
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }
}
