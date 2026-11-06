<?php

namespace Database\Seeders;

use App\Models\Cosmetic;
use App\Models\Season;
use Illuminate\Database\Seeder;

/** Coleção inicial da temporada 2026-27. Requirement = "ORIGEM:NÍVEL". */
class CosmeticSeeder extends Seeder
{
    public function run(): void
    {
        $season = Season::where('label', '2026-27')->value('id');

        $items = [
            // Shot call de jogo
            ['game-silver-frame', 'Moldura Prata', 'FRAME', 'COMMON', 'GAME', 'GAME:SILVER'],
            ['game-silver-title', 'Olho Clínico', 'TITLE', 'COMMON', 'GAME', 'GAME:SILVER'],
            ['game-gold-frame', 'Moldura Ouro', 'FRAME', 'RARE', 'GAME', 'GAME:GOLD'],
            ['game-gold-court', 'Piso Azul Pintado', 'COURT', 'RARE', 'GAME', 'GAME:GOLD'],
            ['game-diamond-frame', 'Moldura Diamante', 'FRAME', 'RARE', 'GAME', 'GAME:DIAMOND'],
            ['game-diamond-ball', 'Bola Cromada', 'BALL', 'RARE', 'GAME', 'GAME:DIAMOND'],
            ['game-perfect-title', 'Profeta', 'TITLE', 'RARE', 'GAME', 'GAME:PERFECT'],
            // Cartela semanal
            ['week-silver-badge', 'Selo da Rodada', 'SHOWCASE', 'EPIC', 'WEEK', 'WEEK:SILVER'],
            ['week-gold-banner', 'Muro Grafitado', 'BANNER', 'EPIC', 'WEEK', 'WEEK:GOLD'],
            ['week-diamond-banner', 'Quadra Noturna Animada', 'BANNER', 'EPIC', 'WEEK', 'WEEK:DIAMOND'],
            ['week-diamond-celebration', 'Chuva de Confete', 'CELEBRATION', 'EPIC', 'WEEK', 'WEEK:DIAMOND'],
            // Temporada e chave
            ['season-silver-trophy', 'Troféu Bronze 26-27', 'SHOWCASE', 'LEGENDARY', 'SEASON', 'SEASON:SILVER'],
            ['season-perfect-title', 'Oráculo da Temporada', 'TITLE', 'LEGENDARY', 'SEASON', 'SEASON:PERFECT'],
            ['bracket-gold-trophy', 'Chave de Ouro 26-27', 'SHOWCASE', 'LEGENDARY', 'BRACKET', 'BRACKET:GOLD'],
            // Ligas
            ['league-champion-tag', 'Campeão da Liga 26-27', 'CREST_TAG', 'LEGENDARY', 'LEAGUE', 'LEAGUE:CHAMPION'],
        ];

        foreach ($items as [$key, $name, $slot, $rarity, $source, $req]) {
            Cosmetic::updateOrCreate(['key' => $key], compact('name', 'slot', 'rarity', 'source') + ['requirement' => $req, 'season_id' => $season]);
        }

        // Pacote lendário animado (Semana Perfeita). Nome provisório: trocar antes de publicar nas lojas.
        $anchors = [
            'mobile' => ['artboard' => [390, 240], 'avatar' => ['cx' => 64, 'cy' => 168, 'r' => 48], 'impact' => [102, 130]],
            'web' => ['artboard' => [1200, 340], 'avatar' => ['cx' => 112, 'cy' => 268, 'r' => 72], 'impact' => [169, 211]],
            'states' => ['idle', 'profile_open', 'tap'],
            'events' => ['hit'],
        ];
        foreach ([['BANNER', 'Banner'], ['FRAME', 'Moldura'], ['TITLE', 'Título']] as [$slot, $label]) {
            Cosmetic::updateOrCreate(['key' => 'legendary-week-perfect-01-'.strtolower($slot)], [
                'name' => "Slim Reaper · {$label} (nome provisório)", 'slot' => $slot, 'rarity' => 'LEGENDARY',
                'source' => 'WEEK', 'requirement' => 'WEEK:PERFECT', 'season_id' => $season,
                'package_key' => 'legendary-week-perfect-01', 'animation' => 'legendary/week-perfect-01.riv', 'anchors' => $anchors,
            ]);
        }
    }
}
