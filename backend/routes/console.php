<?php

use App\Jobs\SyncLiveGame;
use App\Models\Game;
use App\Services\Nba\RosterSync;
use App\Services\Nba\ScheduleSync;
use Illuminate\Support\Facades\Schedule;

// Precisa do agendador (php artisan schedule:work) e de um worker da fila (queue:work).

// Calendário da temporada: de manhã cedo e de novo à tarde (jogos adiados, horários mudados).
Schedule::call(fn () => app(ScheduleSync::class)->run())
    ->name('nba-schedule')->twiceDaily(10, 18)->timezone('UTC')->withoutOverlapping();

// Jogos ao vivo e os que começam em até 5 min (ou deveriam ter começado nas últimas 4 h):
// uma atualização por jogo a cada 15 s.
Schedule::call(function () {
    Game::where('status', 'LIVE')
        ->orWhere(fn ($q) => $q->where('status', 'SCHEDULED')->whereBetween('start_time', [now()->subHours(4), now()->addMinutes(5)]))
        ->pluck('id')
        ->each(fn (string $id) => SyncLiveGame::dispatch($id));
})->name('nba-live-games')->everyFifteenSeconds()->withoutOverlapping();

// Elencos (trocas, contratações, dispensas): uma vez por dia.
Schedule::call(fn () => app(RosterSync::class)->run())
    ->name('nba-rosters')->dailyAt('09:00')->timezone('UTC')->withoutOverlapping();
