<?php

namespace Database\Seeders;

use App\Models\Cosmetic;
use App\Models\Mission;
use Illuminate\Database\Seeder;

/**
 * Missões iniciais. Depois de criadas, são da moderação: rodar de novo não muda o que ela
 * editou (firstOrCreate) e só liga a recompensa se a missão acabou de nascer.
 */
class MissionSeeder extends Seeder
{
    public function run(): void
    {
        $missions = [
            [
                'key' => 'clutch', 'name' => 'Clutch', 'condition' => 'clutch',
                'params' => ['window_seconds' => 120, 'min_period' => 4, 'max_margin' => 5],
                'description' => 'O cestinha fez a última cesta do jogo, e foi ela que decidiu.',
                'hint' => 'Confie no seu cestinha quando o relógio apertar.',
                'rewards' => ['mission-clutch'],
            ],
            [
                'key' => 'splash-brothers', 'name' => 'Splash Brothers', 'condition' => 'friends_leagues',
                'params' => ['min_leagues' => 3],
                'description' => 'Dois amigos que finalizaram 3 ligas juntos.',
                'hint' => 'Forme amizades consistentes.',
                'rewards' => ['mission-splash-brothers'],
            ],
        ];

        foreach ($missions as $data) {
            $rewards = $data['rewards'];
            unset($data['rewards']);

            $mission = Mission::firstOrCreate(['key' => $data['key']], $data + ['secret' => true, 'active' => true]);
            if ($mission->wasRecentlyCreated) {
                $mission->rewards()->sync(Cosmetic::whereIn('key', $rewards)->pluck('id'));
            }
        }
    }
}
