<?php

namespace App\Services\Nba;

use App\Integrations\Nba\GameDataProvider;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

/**
 * Elencos atuais: cria e atualiza os jogadores (nome real, time, posição, número) e marca
 * como inativo quem saiu de todos os elencos. Fonte sem elenco (cdn, demo) não muda nada.
 */
class RosterSync
{
    public function __construct(private GameDataProvider $nba) {}

    /** @return int quantos jogadores estão nos elencos */
    public function run(): int
    {
        $players = $this->nba->rosters();
        if ($players === []) {
            return 0;
        }

        DB::transaction(function () use ($players) {
            foreach ($players as $p) {
                Player::updateOrCreate(['id' => $p['id']], [
                    'team_id' => $p['team_id'],
                    'full_name' => $p['full_name'],
                    'position' => $p['position'],
                    'jersey' => $p['jersey'],
                    'active' => true,
                ]);
            }
            Player::whereNotIn('id', array_column($players, 'id'))->update(['active' => false]);
        });

        return count($players);
    }
}
