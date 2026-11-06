<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Cabeçalho do perfil: dados do usuário, números das abas e a relação com quem está vendo.
 * O controller preenche $friendship, $counts, $accuracy e $trophies.
 *
 * @mixin User
 */
class ProfileResource extends UserResource
{
    public string $friendship = 'NONE';

    /** @var array<string, int> */
    public array $counts = [];

    /** @var array<string, array{rate: float|null, counted: int, tier: string|null}> */
    public array $accuracy = [];

    /** @var list<array<string, mixed>> troféus de liga (pódios de fim de temporada) */
    public array $trophies = [];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'member_since' => $this->created_at?->toIso8601String(),
            'friendship' => $this->friendship,
            'counts' => $this->counts,
            'accuracy' => (object) $this->accuracy,
            'trophies' => $this->trophies,
        ];
    }
}
