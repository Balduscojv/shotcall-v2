<?php

namespace App\Services\Missions\Conditions;

use App\Models\Mission;
use App\Models\User;
use App\Services\Missions\MissionRunner;

/**
 * Confirmou o e-mail do cadastro (pelo link ou entrando com o Google). Avaliada quando alguém
 * confirma, e também ao criar/ligar a missão, para quem já tinha confirmado (Retroactive).
 */
class EmailVerifiedCondition implements Condition, Retroactive
{
    public function key(): string
    {
        return 'email_verified';
    }

    public function label(): string
    {
        return 'E-mail confirmado';
    }

    public function description(): string
    {
        return 'Confirmou o e-mail do cadastro (pelo link enviado ou entrando com o Google). Quem já confirmou ganha assim que a missão é criada.';
    }

    public function params(): array
    {
        return [];
    }

    public function events(): array
    {
        return [MissionRunner::EMAIL_VERIFIED];
    }

    public function achievers(Mission $mission, string $event, mixed $subject): array
    {
        /** @var User $subject */
        return $subject->hasVerifiedEmail() ? [$subject->id => 'E-mail confirmado'] : [];
    }

    public function currentAchievers(Mission $mission): array
    {
        return User::whereNotNull('email_verified_at')->pluck('id')
            ->mapWithKeys(fn (int $id) => [$id => 'E-mail confirmado'])
            ->all();
    }
}
