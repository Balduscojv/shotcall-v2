<?php

namespace Database\Seeders;

use App\Models\Round;
use App\Models\Season;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Temporada 2026-27 e rodadas semanais (segunda a domingo, horário de Nova York).
 * Confira as datas oficiais antes de publicar.
 * Só a rodada da semana atual abre o mercado; as passadas ficam apuradas e as futuras aguardando.
 */
class SeasonSeeder extends Seeder
{
    public function run(): void
    {
        $tz = 'America/New_York';

        $season = Season::updateOrCreate(['label' => '2026-27'], [
            'starts_at' => Carbon::parse('2026-10-20 00:00', $tz)->utc(),
            // Trava excepcional dos palpites da temporada: fim de novembro.
            'season_picks_lock_at' => Carbon::parse('2026-11-30 23:59', $tz)->utc(),
            'playoffs_start_at' => null,
            'ends_at' => Carbon::parse('2027-06-30 23:59', $tz)->utc(),
        ]);

        $monday = Carbon::parse('2026-10-19 00:00', $tz);
        $number = 1;
        while ($monday->lt(Carbon::parse('2027-06-28', $tz))) {
            $starts = $monday->copy()->utc();
            $ends = $monday->copy()->addDays(6)->endOfDay()->utc();
            // O estado do mercado só é definido na criação: depois quem cuida é o fantasy
            // (abre, trava e apura). Rodar o seeder de novo não pode desfazer isso.
            $round = Round::firstOrNew(['season_id' => $season->id, 'number' => $number]);
            $round->fill(['starts_at' => $starts, 'ends_at' => $ends]);
            $round->market_status ??= match (true) {
                $ends->isPast() => Round::SETTLED,
                $starts->isPast() => Round::LOCKED,
                default => Round::UPCOMING,
            };
            $round->save();
            $monday->addWeek();
            $number++;
        }
    }
}
