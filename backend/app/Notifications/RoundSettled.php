<?php

namespace App\Notifications;

use App\Models\User;

/** A rodada do fantasy foi apurada: quantos pontos o time fez. */
class RoundSettled extends AppNotification
{
    public const KIND = 'round_settled';

    public function __construct(public int $roundNumber, public float $points, public float $boost) {}

    public function kind(): string
    {
        return self::KIND;
    }

    public function title(): string
    {
        return "Rodada {$this->roundNumber} apurada";
    }

    public function body(): string
    {
        $points = number_format($this->points, 2, ',', '.');
        $boost = $this->boost > 0 ? ' (com +'.round($this->boost * 100).'% de boost dos palpites)' : '';

        return "Seu time fez {$points} pontos{$boost}. O mercado da próxima rodada já está aberto.";
    }

    public function url(User $notifiable): string
    {
        return '/time';
    }
}
