<?php

namespace App\Services\Missions;

use App\Models\Cosmetic;
use App\Models\Friendship;
use App\Models\League;
use App\Models\User;
use App\Services\Cosmetics\AwardService;
use Illuminate\Support\Facades\DB;

/**
 * Splash Brothers: dois amigos que finalizaram juntos pelo menos N ligas
 * (config/missions.php). Os dois ganham o título.
 *
 * Não depende de um jogo, então fica fora do MissionRunner. É avaliada:
 * - quando uma amizade é aceita (o par pode já ter ligas finalizadas juntos);
 * - quando uma liga termina (leagueFinished), para cada par de amigos dela.
 *
 * A liga global não conta: todo mundo está nela.
 */
class SplashBrothersMission
{
    public const TRIGGER = 'MISSION:SPLASH_BROTHERS';

    public function __construct(private AwardService $awards) {}

    /** Ligas não globais já finalizadas em que os dois eram membros. */
    public function leaguesFinishedTogether(User $a, User $b): int
    {
        return League::whereNotNull('finished_at')
            ->where('is_global', false)
            ->whereHas('members', fn ($q) => $q->whereKey($a->id))
            ->whereHas('members', fn ($q) => $q->whereKey($b->id))
            ->count();
    }

    /** Avalia um par. @return int quantos prêmios foram liberados */
    public function checkPair(User $a, User $b): int
    {
        if ($a->is($b) || ! $this->areFriends($a, $b)) {
            return 0;
        }

        $min = (int) config('missions.splash_brothers.min_leagues');
        if ($this->leaguesFinishedTogether($a, $b) < $min) {
            return 0;
        }

        $cosmetics = Cosmetic::triggeredBy(self::TRIGGER)->get();
        $granted = 0;
        foreach ([[$a, $b], [$b, $a]] as [$user, $partner]) {
            foreach ($cosmetics as $cosmetic) {
                $granted += (int) $this->awards->grant($user, $cosmetic, "Splash Brothers com @{$partner->handle}");
            }
        }

        return $granted;
    }

    /** Liga finalizada: avalia todos os pares de amigos entre os membros. */
    public function leagueFinished(League $league): int
    {
        if ($league->is_global || $league->finished_at === null) {
            return 0;
        }

        $memberIds = DB::table('league_members')->where('league_id', $league->id)->pluck('user_id');

        $granted = 0;
        Friendship::with(['requester', 'addressee'])
            ->where('status', Friendship::ACCEPTED)
            ->whereIn('requester_id', $memberIds)
            ->whereIn('addressee_id', $memberIds)
            ->each(function (Friendship $f) use (&$granted) {
                $granted += $this->checkPair($f->requester, $f->addressee);
            });

        return $granted;
    }

    private function areFriends(User $a, User $b): bool
    {
        return Friendship::between($a, $b)->where('status', Friendship::ACCEPTED)->exists();
    }
}
