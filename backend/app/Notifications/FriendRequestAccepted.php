<?php

namespace App\Notifications;

use App\Models\User;

class FriendRequestAccepted extends AppNotification
{
    public const KIND = 'friend_accepted';

    public function __construct(public User $by) {}

    public function kind(): string
    {
        return self::KIND;
    }

    public function title(): string
    {
        return 'Pedido aceito';
    }

    public function body(): string
    {
        return "{$this->by->name} (@{$this->by->handle}) aceitou seu pedido. Agora vocês são amigos!";
    }

    public function url(User $notifiable): string
    {
        return "/u/{$this->by->handle}";
    }

    public function actor(): ?User
    {
        return $this->by;
    }
}
