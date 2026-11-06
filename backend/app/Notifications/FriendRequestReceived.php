<?php

namespace App\Notifications;

use App\Models\User;

class FriendRequestReceived extends AppNotification
{
    public const KIND = 'friend_request';

    public function __construct(public User $from) {}

    public function kind(): string
    {
        return self::KIND;
    }

    public function title(): string
    {
        return 'Pedido de amizade';
    }

    public function body(): string
    {
        return "{$this->from->name} (@{$this->from->handle}) quer ser seu amigo.";
    }

    public function url(User $notifiable): string
    {
        return "/u/{$notifiable->handle}/amigos";
    }

    public function actor(): ?User
    {
        return $this->from;
    }
}
