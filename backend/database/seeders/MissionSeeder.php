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
            // Prêmios de liga: abertos (não secretos), saem no fim da temporada da liga.
            [
                'key' => 'league-champion', 'name' => 'Campeão da Liga', 'condition' => 'league_rank',
                'params' => ['max_rank' => 1, 'min_members' => 5], 'secret' => false,
                'description' => 'Terminar a temporada em 1º numa liga com 5 ou mais membros.',
                'hint' => 'Ganhe a sua liga.',
                'rewards' => ['league-champion'],
            ],
            [
                'key' => 'league-podium', 'name' => 'Pódio da Temporada', 'condition' => 'league_rank',
                'params' => ['max_rank' => 3, 'min_members' => 10], 'secret' => false,
                'description' => 'Terminar a temporada no top 3 de uma liga com 10 ou mais membros.',
                'hint' => 'Chegue ao pódio de uma liga cheia.',
                'rewards' => ['league-podium'],
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
