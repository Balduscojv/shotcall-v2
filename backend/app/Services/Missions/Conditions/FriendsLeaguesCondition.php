<?php

namespace App\Services\Missions\Conditions;

use App\Models\Friendship;
use App\Models\League;
use App\Models\Mission;
use App\Models\User;
use App\Services\Missions\MissionRunner;
use Illuminate\Support\Facades\DB;

/**
 * Dois amigos que finalizaram juntos pelo menos N ligas (a global não conta). Os dois cumprem.
 * Avaliada quando uma amizade é aceita (o par pode já ter ligas juntos) e quando uma liga termina.
 * É a condição do Splash Brothers.
 */
class FriendsLeaguesCondition implements Condition
{
    public function key(): string
    {
        return 'friends_leagues';
    }

    public function label(): string
    {
        return 'Amigos que finalizaram ligas juntos';
    }

    public function description(): string
    {
        return 'Dois amigos terminaram juntos pelo menos N ligas (a Liga Global não conta). Os dois ganham.';
    }

    public function params(): array
    {
        return [
            ['name' => 'min_leagues', 'label' => 'Ligas finalizadas juntos (mínimo)', 'min' => 1, 'max' => 100, 'default' => 3],
        ];
    }

    public function events(): array
    {
        return [MissionRunner::LEAGUE_FINISHED, MissionRunner::FRIENDSHIP_ACCEPTED];
    }

    public function achievers(Mission $mission, string $event, mixed $subject): array
    {
        $pairs = $event === MissionRunner::FRIENDSHIP_ACCEPTED ? [$subject] : $this->friendPairsIn($subject);

        $winners = [];
        foreach ($pairs as [$a, $b]) {
            if ($this->leaguesFinishedTogether($a, $b) >= $mission->param('min_leagues', 3)) {
                $winners[$a->id] ??= "{$mission->name} com @{$b->handle}";
                $winners[$b->id] ??= "{$mission->name} com @{$a->handle}";
            }
        }

        return $winners;
    }

    /** Ligas não globais já finalizadas em que os dois eram membros. */
    public function leaguesFinishedTogether(User $a, User $b): int
    {
        return League::whereNotNull('finished_at')
            ->where('is_global', false)
            ->whereHas('members', fn ($q) => $q->whereKey($a->id))
            ->whereHas('members', fn ($q) => $q->whereKey($b->id))
            ->count();
    }

    /** @return list<array{0: User, 1: User}> pares de amigos entre os membros da liga */
    private function friendPairsIn(League $league): array
    {
        $memberIds = DB::table('league_members')->where('league_id', $league->id)->pluck('user_id');

        return Friendship::with(['requester', 'addressee'])
            ->where('status', Friendship::ACCEPTED)
            ->whereIn('requester_id', $memberIds)
            ->whereIn('addressee_id', $memberIds)
            ->get()
            ->map(fn (Friendship $f) => [$f->requester, $f->addressee])
            ->all();
    }
}
