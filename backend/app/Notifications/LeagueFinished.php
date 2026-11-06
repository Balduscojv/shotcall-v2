<?php

namespace App\Notifications;

use App\Models\User;

/** A temporada da liga acabou: a colocação final em cada ranking que ela disputa. */
class LeagueFinished extends AppNotification
{
    public const KIND = 'league_finished';

    private const LABELS = ['FANTASY' => 'fantasy', 'SHOTCALL' => 'shot calls'];

    /** @param array<string, int> $ranks competição => posição final */
    public function __construct(
        public string $leagueName,
        public string $leagueSlug,
        public string $seasonLabel,
        public array $ranks,
        public int $membersCount,
    ) {}

    public function kind(): string
    {
        return self::KIND;
    }

    public function title(): string
    {
        return in_array(1, $this->ranks, true) ? "Campeão da {$this->leagueName}!" : "Fim de temporada na {$this->leagueName}";
    }

    public function body(): string
    {
        $places = collect($this->ranks)
            ->map(fn (int $rank, string $competition) => "{$rank}º de {$this->membersCount} no ".self::LABELS[$competition])
            ->join(' e ');

        return "Temporada {$this->seasonLabel} encerrada. Você terminou em {$places}.";
    }

    public function url(User $notifiable): string
    {
        return "/ligas/{$this->leagueSlug}";
    }
}
