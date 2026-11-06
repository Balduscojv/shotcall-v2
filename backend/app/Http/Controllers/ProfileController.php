<?php

namespace App\Http\Controllers;

use App\Http\Resources\CosmeticResource;
use App\Http\Resources\ProfileLeagueResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\ShotCallHistoryResource;
use App\Http\Resources\UserResource;
use App\Models\Cosmetic;
use App\Models\Game;
use App\Models\GameShotCall;
use App\Models\LeagueStanding;
use App\Models\User;
use App\Models\UserAccuracy;
use App\Services\Friends\FriendshipService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Perfil público de um usuário (por @) e as abas: palpites, prêmios, ligas e amigos. */
class ProfileController extends Controller
{
    public function __construct(private FriendshipService $friends) {}

    public function show(Request $request, User $user): ProfileResource
    {
        $viewer = $request->user();

        $resource = new ProfileResource($user->load(['equippedBanner', 'equippedFrame', 'equippedTitle']));
        $resource->friendship = $this->friends->status($viewer, $user);
        $resource->counts = [
            'shot_calls' => $this->shotCallsQuery($viewer, $user)->count(),
            // No próprio perfil a aba mostra o que dá para usar: para admin, o catálogo inteiro.
            'awards' => $viewer->is($user) && $user->isAdmin() ? Cosmetic::count() : $user->cosmetics()->count(),
            'leagues' => $this->leaguesQuery($viewer, $user)->count(),
            'friends' => $this->friends->friendsOf($user)->count(),
        ];
        $resource->accuracy = UserAccuracy::where('user_id', $user->id)->get()
            ->mapWithKeys(fn (UserAccuracy $a) => [$a->mode => [
                'rate' => $a->rate === null ? null : (float) $a->rate,
                'counted' => (int) $a->counted,
                'tier' => $a->tier,
            ]])->all();

        return $resource;
    }

    public function shotCalls(Request $request, User $user): AnonymousResourceCollection
    {
        $calls = $this->shotCallsQuery($request->user(), $user)
            ->with(['game.home', 'game.away', 'winner', 'mvp'])
            ->orderByDesc(Game::select('start_time')->whereColumn('games.id', 'game_shot_calls.game_id'))
            ->paginate(20);

        return ShotCallHistoryResource::collection($calls);
    }

    public function awards(User $user): AnonymousResourceCollection
    {
        $equipped = $user->equippedIds();
        $awards = $user->cosmetics()->orderByPivot('unlocked_at', 'desc')->get()
            ->each(fn (Cosmetic $c) => $c->setAttribute('is_equipped', in_array($c->id, $equipped, true)));

        return CosmeticResource::collection($awards)
            ->additional(['meta' => ['collection_total' => Cosmetic::count()]]);
    }

    public function leagues(Request $request, User $user): AnonymousResourceCollection
    {
        $leagues = $this->leaguesQuery($request->user(), $user)
            ->withCount('members')
            ->addSelect(['rank' => LeagueStanding::select('rank')
                ->whereColumn('league_standings.league_id', 'leagues.id')
                ->where('league_standings.user_id', $user->id)
                ->orderByDesc('round_id')->limit(1)])
            ->orderByDesc('is_global')->orderBy('name')
            ->get();

        return ProfileLeagueResource::collection($leagues);
    }

    public function friends(User $user): AnonymousResourceCollection
    {
        return UserResource::collection($this->friends->friendsOf($user)->withEquipped()->orderBy('name')->get());
    }

    /** Palpite de outra pessoa só aparece depois que o jogo começou; antes disso é segredo. */
    private function shotCallsQuery(User $viewer, User $user): Builder
    {
        return GameShotCall::where('user_id', $user->id)
            ->when(! $viewer->is($user), fn (Builder $q) => $q->whereHas(
                'game',
                fn (Builder $g) => $g->where('status', '!=', 'SCHEDULED')->orWhere('start_time', '<=', now()),
            ));
    }

    /** Ligas privadas de outra pessoa só aparecem se quem vê também participa. */
    private function leaguesQuery(User $viewer, User $user): BelongsToMany
    {
        return $user->leagues()->when(! $viewer->is($user), fn ($q) => $q->where(
            fn ($l) => $l->where('privacy', 'PUBLIC')
                ->orWhereHas('members', fn ($m) => $m->whereKey($viewer->id)),
        ));
    }
}
