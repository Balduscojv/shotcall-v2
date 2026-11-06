<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Base das notificações do app. Cada uma guarda o texto pronto para mostrar (título, corpo,
 * link e, se houver, quem fez a ação), então a lista não precisa saber de cada tipo.
 *
 * Canais em config/notifications.php: hoje só o banco (sininho no app). O push (Firebase)
 * entra como mais um canal, sem mexer em quem dispara a notificação.
 */
abstract class AppNotification extends Notification
{
    /** Tipo curto salvo no banco (friend_request, award, ...). */
    abstract public function kind(): string;

    abstract public function title(): string;

    abstract public function body(): string;

    /** Para onde o clique leva, no web. */
    abstract public function url(User $notifiable): string;

    /** Quem fez a ação (ex.: quem mandou o pedido), se houver. */
    public function actor(): ?User
    {
        return null;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return config('notifications.channels');
    }

    public function databaseType(object $notifiable): string
    {
        return $this->kind();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $actor = $this->actor();

        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url($notifiable),
            'actor' => $actor ? [
                'id' => $actor->id, 'name' => $actor->name, 'handle' => $actor->handle, 'avatar_url' => $actor->avatar_url,
            ] : null,
        ];
    }
}
