<?php

namespace App\Notifications;

use App\Models\User;

/**
 * O time da próxima rodada foi montado com preços provisórios e, com a valorização da apuração,
 * passou a custar mais que o patrimônio. Continua salvo; se não ajustar, vale com saldo negativo.
 */
class LineupOverBudget extends AppNotification
{
    public const KIND = 'lineup_over_budget';

    public function __construct(public int $roundNumber, public float $cost, public float $patrimony) {}

    public function kind(): string
    {
        return self::KIND;
    }

    public function title(): string
    {
        return "Seu time da rodada {$this->roundNumber} passou do orçamento";
    }

    public function body(): string
    {
        $money = fn (float $v) => 'F$ '.number_format($v, 2, ',', '.');

        return "Com a valorização dos jogadores, a escalação custa {$money($this->cost)} e seu patrimônio é {$money($this->patrimony)}. "
            .'Ajuste antes de o mercado fechar; se não mexer, o time vale assim mesmo, com saldo negativo.';
    }

    public function url(User $notifiable): string
    {
        return '/time';
    }
}
