<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFriendRequest;
use App\Http\Resources\UserResource;
use App\Models\Friendship;
use App\Models\User;
use App\Services\Friends\FriendshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Pedidos de amizade de quem está logado. */
class FriendshipController extends Controller
{
    public function __construct(private FriendshipService $friends) {}

    /** Pedidos pendentes: recebidos (para aceitar) e enviados (aguardando). */
    public function requests(Request $request): JsonResponse
    {
        $me = $request->user();
        $pending = Friendship::involving($me)->where('status', Friendship::PENDING)
            ->with(['requester', 'addressee'])->latest()->get();

        return response()->json(['data' => [
            'received' => UserResource::collection($pending->where('addressee_id', $me->id)->map->requester->values()),
            'sent' => UserResource::collection($pending->where('requester_id', $me->id)->map->addressee->values()),
        ]]);
    }

    /**
     * Busca pelo @ (começo do handle), para adicionar amigos. Cada resultado vem com a
     * relação comigo (`friendship`). Eu mesmo fico de fora.
     */
    public function search(Request $request): JsonResponse
    {
        $me = $request->user();
        $term = strtolower(trim(ltrim((string) $request->query('q'), '@ ')));
        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        // "_" é comum no @ e é curinga no LIKE: escapa.
        $pattern = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
        $users = User::whereRaw('handle LIKE ? ESCAPE ?', [$pattern, '!'])
            ->whereKeyNot($me->id)
            ->withEquipped()
            ->orderByRaw('length(handle)')
            ->orderBy('handle')
            ->limit(8)
            ->get();

        $statuses = $this->friends->statuses($me, $users);

        return response()->json(['data' => $users->map(fn (User $user) => [
            ...(new UserResource($user))->resolve($request),
            'friendship' => $statuses[$user->id],
        ])]);
    }

    public function store(StoreFriendRequest $request): JsonResponse
    {
        $me = $request->user();
        $other = User::where('handle', $request->validated('handle'))->firstOrFail();
        $this->friends->request($me, $other);

        return response()->json(['data' => ['status' => $this->friends->status($me, $other)]], 201);
    }

    public function accept(Request $request, User $user): JsonResponse
    {
        $this->friends->accept($request->user(), $user);

        return response()->json(['data' => ['status' => FriendshipService::FRIENDS]]);
    }

    public function destroy(Request $request, User $user): Response
    {
        $this->friends->remove($request->user(), $user);

        return response()->noContent();
    }
}
