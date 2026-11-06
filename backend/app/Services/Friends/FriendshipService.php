<?php

namespace App\Services\Friends;

use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequestAccepted;
use App\Notifications\FriendRequestReceived;
use App\Services\Missions\SplashBrothersMission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\ValidationException;

/** Regras de amizade: pedir, aceitar, desfazer e saber a relação entre duas pessoas. */
class FriendshipService
{
    public const SELF = 'SELF';

    public const FRIENDS = 'FRIENDS';

    public const SENT = 'SENT';         // eu pedi, falta a outra pessoa aceitar

    public const RECEIVED = 'RECEIVED'; // a outra pessoa pediu, falta eu aceitar

    public const NONE = 'NONE';

    public function __construct(private SplashBrothersMission $splashBrothers) {}

    public function status(User $viewer, User $user): string
    {
        if ($viewer->is($user)) {
            return self::SELF;
        }

        $link = Friendship::between($viewer, $user)->first();

        return match (true) {
            $link === null => self::NONE,
            $link->status === Friendship::ACCEPTED => self::FRIENDS,
            $link->requester_id === $viewer->id => self::SENT,
            default => self::RECEIVED,
        };
    }

    /**
     * Relação de quem vê com vários usuários de uma vez (uma consulta só).
     *
     * @param  iterable<User>  $users
     * @return array<int, string> status por id de usuário
     */
    public function statuses(User $viewer, iterable $users): array
    {
        $ids = collect($users)->pluck('id');
        $links = Friendship::involving($viewer)
            ->where(fn ($q) => $q->whereIn('requester_id', $ids)->orWhereIn('addressee_id', $ids))
            ->get()
            ->keyBy(fn (Friendship $f) => $f->requester_id === $viewer->id ? $f->addressee_id : $f->requester_id);

        return $ids->mapWithKeys(function (int $id) use ($viewer, $links) {
            $link = $links->get($id);

            return [$id => match (true) {
                $id === $viewer->id => self::SELF,
                $link === null => self::NONE,
                $link->status === Friendship::ACCEPTED => self::FRIENDS,
                $link->requester_id === $viewer->id => self::SENT,
                default => self::RECEIVED,
            }];
        })->all();
    }

    /** Pedido de amizade. Se a outra pessoa já tinha pedido, vira amizade na hora. */
    public function request(User $from, User $to): Friendship
    {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['handle' => 'Você não pode se adicionar.']);
        }

        $link = Friendship::between($from, $to)->first();

        if ($link === null) {
            $link = Friendship::create(['requester_id' => $from->id, 'addressee_id' => $to->id]);
            $to->notify(new FriendRequestReceived($from));

            return $link;
        }

        if ($link->status === Friendship::PENDING && $link->addressee_id === $from->id) {
            return $this->accept($from, $to);
        }

        return $link;
    }

    /** $user aceita o pedido que $requester mandou. */
    public function accept(User $user, User $requester): Friendship
    {
        $link = Friendship::where('requester_id', $requester->id)
            ->where('addressee_id', $user->id)
            ->where('status', Friendship::PENDING)
            ->firstOrFail();

        $link->update(['status' => Friendship::ACCEPTED, 'accepted_at' => now()]);

        // O aviso do pedido já foi resolvido; quem pediu fica sabendo que deu certo.
        $this->requestNotices($user, $requester)->each->markAsRead();
        $requester->notify(new FriendRequestAccepted($user));

        // O par pode já ter finalizado ligas juntos antes de virar amigo.
        $this->splashBrothers->checkPair($user, $requester);

        return $link;
    }

    /** Desfaz amizade, cancela pedido enviado ou recusa pedido recebido. */
    public function remove(User $user, User $other): void
    {
        Friendship::between($user, $other)->delete();

        // Pedido cancelado ou recusado: o aviso some do sininho de quem recebeu.
        $this->requestNotices($user, $other)->each->delete();
        $this->requestNotices($other, $user)->each->delete();
    }

    /**
     * Avisos de pedido de amizade que $from mandou para $to e $to ainda não leu.
     *
     * @return Collection<int, DatabaseNotification>
     */
    private function requestNotices(User $to, User $from): Collection
    {
        return $to->unreadNotifications()->where('type', FriendRequestReceived::KIND)->get()
            ->filter(fn (DatabaseNotification $n) => ($n->data['actor']['id'] ?? null) === $from->id)
            ->values();
    }

    /** Amigos aceitos de alguém, como consulta de usuários. */
    public function friendsOf(User $user): Builder
    {
        $ids = Friendship::involving($user)->where('status', Friendship::ACCEPTED)
            ->get(['requester_id', 'addressee_id'])
            ->map(fn (Friendship $f) => $f->requester_id === $user->id ? $f->addressee_id : $f->requester_id);

        return User::whereIn('id', $ids);
    }
}
