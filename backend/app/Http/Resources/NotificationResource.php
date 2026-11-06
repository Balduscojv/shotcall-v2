<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $actor = $this->data['actor'] ?? null;

        return [
            'id' => $this->id,
            'kind' => $this->type,
            'title' => $this->data['title'] ?? '',
            'body' => $this->data['body'] ?? '',
            'url' => $this->data['url'] ?? null,
            'actor' => $actor ? [
                'name' => $actor['name'], 'handle' => $actor['handle'], 'avatar_url' => $actor['avatar_url'] ?? null,
            ] : null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
