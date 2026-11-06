<?php

namespace App\Services\Leagues;

use App\Models\League;
use App\Models\LeagueTrophy;
use App\Models\Round;
use App\Models\Season;
use App\Notifications\LeagueFinished;
use App\Services\Missions\MissionRunner;
use Illuminate\Support\Facades\DB;

/**
 * Fim de temporada das ligas: congela o ranking final, guarda o pódio de cada competição como
 * troféu, marca a liga como finalizada, avisa os membros e roda as missões de liga (prêmios de
 * campeão e pódio, Splash Brothers).
 *
 * A Liga Global não termina: todo mundo segue nela. Repetir não muda nada, porque só fecha
 * liga que ainda não foi finalizada.
 */
class LeagueSeasonCloser
{
    private const BOARDS = ['FANTASY' => 'fantasy', 'SHOTCALL' => 'shotcall'];

    public function __construct(private LeagueRanking $ranking, private MissionRunner $missions) {}

    /**
     * Fecha as ligas da temporada pela última rodada apurada (no fim da temporada, a última de
     * todas). Fechar antes do fim, à mão, não conta parcial de rodada em andamento.
     *
     * @return int ligas finalizadas
     */
    public function closeSeason(Season $season): int
    {
        $last = Round::where('season_id', $season->id)->where('market_status', Round::SETTLED)->orderByDesc('number')->first();
        if (! $last) {
            return 0;
        }

        $closed = 0;
        League::where('is_global', false)->whereNull('finished_at')
            ->each(function (League $league) use ($season, $last, &$closed) {
                $this->close($league, $season, $last);
                $closed++;
            });

        return $closed;
    }

    public function close(League $league, Season $season, Round $last): void
    {
        $members = DB::table('league_members')->where('league_id', $league->id)->count();
        $podiums = [];

        DB::transaction(function () use ($league, $season, $last, $members, &$podiums) {
            foreach ($league->competitions as $competition) {
                $board = $this->ranking->board($league, self::BOARDS[$competition], 'season', $last);
                // Sem ponto nenhum não é pódio: liga parada não distribui troféu.
                $podium = $board->filter(fn (array $row) => $row['rank'] <= 3 && ($row['score'] ?? 0) > 0);
                $podiums[$competition] = $board;

                foreach ($podium as $row) {
                    LeagueTrophy::updateOrCreate(
                        ['league_id' => $league->id, 'user_id' => $row['user_id'], 'competition' => $competition],
                        [
                            'season_id' => $season->id, 'rank' => $row['rank'], 'members_count' => $members,
                            'league_name' => $league->name, 'league_color' => $league->color, 'score' => $row['score'],
                        ],
                    );
                }
            }

            $league->update(['finished_at' => now()]);
        });

        $this->notifyMembers($league, $season, $podiums, $members);
        $this->missions->leagueFinished($league);
    }

    /** Cada membro recebe a colocação final em cada ranking da liga. */
    private function notifyMembers(League $league, Season $season, array $boards, int $members): void
    {
        $ranks = [];
        foreach ($boards as $competition => $board) {
            foreach ($board as $row) {
                $ranks[$row['user_id']][$competition] = $row['rank'];
            }
        }

        $league->members()->each(fn ($user) => $user->notify(
            new LeagueFinished($league->name, $league->slug, $season->label, $ranks[$user->id] ?? [], $members),
        ));
    }
}
