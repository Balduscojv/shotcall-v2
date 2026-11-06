<?php

namespace App\Services\Friends;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Regras de amizade: pedir, aceitar, desfazer e saber a relação entre duas pessoas. */
class FriendshipService
{
    public const SELF = 'SELF';
    public const FRIENDS = 'FRIENDS';
    public const SENT = 'SENT';         // eu pedi, falta a outra pessoa aceitar
    public const RECEIVED = 'RECEIVED'; // a outra pessoa pediu, falta eu aceitar
    public const NONE = 'NONE';

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

    /** Pedido de amizade. Se a outra pessoa já tinha pedido, vira amizade na hora. */
    public function request(User $from, User $to): Friendship
    {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['handle' => 'Você não pode se adicionar.']);
        }

        $link = Friendship::between($from, $to)->first();

        if ($link === null) {
            return Friendship::create(['requester_id' => $from->id, 'addressee_id' => $to->id]);
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

        return $link;
    }

    /** Desfaz amizade, cancela pedido enviado ou recusa pedido recebido. */
    public function remove(User $user, User $other): void
    {
        Friendship::between($user, $other)->delete();
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
