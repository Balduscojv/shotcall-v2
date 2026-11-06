<?php

namespace App\Services\Nba;

use App\Events\GameFinished;
use App\Integrations\Nba\GameDataProvider;
use App\Models\Game;
use App\Models\GameAction;
use App\Models\GamePeriod;
use App\Models\Player;
use App\Models\PlayerGameStat;
use Illuminate\Support\Facades\DB;

/**
 * Atualiza um jogo a partir da fonte: placar, relógio, quartos, estatísticas por jogador
 * e as jogadas novas (só as depois da última que já temos). Quando o jogo vira FINAL,
 * dispara GameFinished uma única vez (missões, e depois pontuação do fantasy e palpites).
 */
class GameSync
{
    public function __construct(private GameDataProvider $nba) {}

    public function sync(Game $game): Game
    {
        $box = $this->nba->boxScore($game->id);
        $actions = $this->nba->playByPlay($game->id, (int) $game->last_action_number);
        $wasFinal = $game->status === 'FINAL';

        DB::transaction(function () use ($game, $box, $actions) {
            $game->update([
                'status' => $box['status'],
                'period' => $box['period'],
                'clock' => $box['clock'],
                'home_score' => $box['home_score'],
                'away_score' => $box['away_score'],
                'last_action_number' => $actions ? max(array_column($actions, 'action_number')) : $game->last_action_number,
            ]);

            foreach ($box['periods'] as $p) {
                GamePeriod::upsert([['game_id' => $game->id] + $p], ['game_id', 'period'], ['home_points', 'away_points']);
            }

            foreach ($box['players'] as $p) {
                // Posição e número vêm do elenco; o box score só preenche quando ainda não temos.
                $player = Player::firstOrNew(['id' => $p['player_id']]);
                $player->fill(['team_id' => $p['team_id'], 'full_name' => $p['full_name'], 'active' => true]);
                $player->position ??= $p['position'] ?? null;
                $player->jersey ??= $p['jersey'] ?? null;
                $player->save();

                $stat = collect($p)->except(['player_id', 'full_name', 'position', 'jersey'])->all();
                PlayerGameStat::upsert(
                    [['game_id' => $game->id, 'player_id' => $p['player_id']] + $stat],
                    ['game_id', 'player_id'],
                    array_keys($stat),
                );
            }

            if ($actions) {
                GameAction::insertOrIgnore(array_map(fn ($a) => ['game_id' => $game->id] + $a, $actions));
            }
        });

        if (! $wasFinal && $game->status === 'FINAL') {
            GameFinished::dispatch($game);
        }

        return $game;
    }
}
