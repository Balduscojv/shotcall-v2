<?php

namespace App\Services\Fantasy;

use App\Models\Game;
use App\Models\PlayerGameScore;
use App\Models\PlayerGameStat;
use App\Models\PlayerRoundScore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pontuação fantasy dos jogadores num jogo (config/fantasy.php). No fim do jogo ela é salva
 * (e soma na rodada); durante o jogo dá para calcular sem salvar, para as parciais.
 */
class GameScoring
{
    public function __construct(private FantasyScorer $scorer) {}

    /**
     * Pontos de cada jogador com as estatísticas atuais, sem salvar.
     *
     * @return Collection<int, float> player_id => pontos
     */
    public function current(Game $game): Collection
    {
        return PlayerGameStat::where('game_id', $game->id)->get()
            ->mapWithKeys(fn (PlayerGameStat $s) => [$s->player_id => $this->scorer->score(StatLine::fromArray($s->toArray()))['total']]);
    }

    /** Fim de jogo: salva a pontuação de cada jogador e atualiza o acumulado da rodada. */
    public function save(Game $game): void
    {
        $stats = PlayerGameStat::where('game_id', $game->id)->get();

        DB::transaction(function () use ($game, $stats) {
            foreach ($stats as $stat) {
                $result = $this->scorer->score(StatLine::fromArray($stat->toArray()));
                PlayerGameScore::upsert([[
                    'game_id' => $game->id, 'player_id' => $stat->player_id,
                    'points' => $result['total'], 'breakdown' => json_encode($result['breakdown']),
                ]], ['game_id', 'player_id'], ['points', 'breakdown']);
            }

            if (! $game->round_id) {
                return; // pré-temporada não conta para o fantasy
            }

            $rows = DB::table('player_game_scores as s')
                ->join('games as g', 'g.id', '=', 's.game_id')
                ->where('g.round_id', $game->round_id)
                ->whereIn('s.player_id', $stats->pluck('player_id'))
                ->groupBy('s.player_id')
                ->selectRaw('s.player_id, count(*) as games, sum(s.points) as points')
                ->get();
            foreach ($rows as $r) {
                PlayerRoundScore::upsert([[
                    'player_id' => $r->player_id, 'round_id' => $game->round_id, 'games' => $r->games,
                    'points' => $r->points, 'average' => round($r->points / max(1, $r->games), 2),
                ]], ['player_id', 'round_id'], ['games', 'points', 'average']);
            }
        });
    }
}
