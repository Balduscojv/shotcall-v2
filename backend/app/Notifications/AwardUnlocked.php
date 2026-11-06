<?php

namespace App\Notifications;

use App\Models\Cosmetic;
use App\Models\User;

/** Prêmio liberado (missão secreta ou moderação). Os de boas-vindas do cadastro não avisam. */
class AwardUnlocked extends AppNotification
{
    public const KIND = 'award';

    public function __construct(public Cosmetic $cosmetic, public string $reason) {}

    public function kind(): string
    {
        return self::KIND;
    }

    public function title(): string
    {
        return "Prêmio liberado: {$this->cosmetic->name}";
    }

    public function body(): string
    {
        return "{$this->cosmetic->slot->label()} · {$this->rarity()} · {$this->reason}. Equipe na aba Prêmios.";
    }

    public function url(User $notifiable): string
    {
        return "/u/{$notifiable->handle}/premios";
    }

    private function rarity(): string
    {
        return match ($this->cosmetic->rarity) {
            'RARE' => 'Raro',
            'EPIC' => 'Épico',
            'LEGENDARY' => 'Lendário',
            default => 'Comum',
        };
    }
}
