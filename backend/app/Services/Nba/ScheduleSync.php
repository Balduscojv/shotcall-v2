<?php

namespace App\Services\Nba;

use App\Integrations\Nba\GameDataProvider;
use App\Models\Game;
use App\Models\Round;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * Salva o calendário da temporada e liga cada jogo à sua rodada semanal (pré-temporada
 * fica sem rodada). Não mexe em placar nem status de jogo já começado: isso é do GameSync.
 */
class ScheduleSync
{
    public function __construct(private GameDataProvider $nba) {}

    /** @return int quantos jogos foram salvos */
    public function run(): int
    {
        $season = Season::latest('starts_at')->firstOrFail();
        $rounds = Round::where('season_id', $season->id)->get(['id', 'starts_at', 'ends_at']);
        $saved = 0;

        foreach ($this->nba->schedule() as $g) {
            foreach (['home', 'away'] as $side) {
                // Time novo (ex.: expansão) entra com cores neutras até alguém ajustar.
                Team::whereKey($g[$side]['id'])->exists() || Team::create([
                    'id' => $g[$side]['id'], 'tricode' => $g[$side]['tricode'], 'city' => $g[$side]['city'],
                    'name' => $g[$side]['name'], 'conference' => 'EAST', 'primary_color' => '#22252E', 'secondary_color' => '#9AA0B2',
                ]);
            }

            $start = Carbon::parse($g['start_time'])->utc();
            $round = $g['phase'] === 'PRESEASON' ? null
                : $rounds->first(fn (Round $r) => $start->between($r->starts_at, $r->ends_at))?->id;

            $game = Game::firstOrNew(['id' => $g['id']]);
            $game->fill([
                'season_id' => $season->id,
                'round_id' => $round,
                'phase' => $g['phase'],
                'home_team_id' => $g['home']['id'],
                'away_team_id' => $g['away']['id'],
                'start_time' => $start,
                'arena' => $g['arena'] ?? null,
                'national_tv' => $g['national_tv'] ?? null,
            ]);
            // Adiado aparece no calendário; o resto do status vem do acompanhamento ao vivo.
            if (! $game->exists || $game->status === 'SCHEDULED') {
                $game->status = $g['status'] === 'POSTPONED' ? 'POSTPONED' : 'SCHEDULED';
            }
            $game->save();
            $saved++;
        }

        return $saved;
    }
}
