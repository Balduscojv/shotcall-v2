<?php

namespace Database\Seeders;

use App\Enums\CosmeticSlot;
use App\Models\Cosmetic;
use App\Services\Missions\ClutchMission;
use App\Services\Missions\SplashBrothersMission;
use Illuminate\Database\Seeder;

/**
 * Catálogo de prêmios. Três tipos: banner de fundo, moldura da foto e título do @.
 * `trigger` = o que libera (SIGNUP = cadastro); sem trigger, só a moderação libera.
 * `asset_key` = qual visual o front desenha (web/src/lib/cosmetics.ts).
 *
 * Com mais de um do mesmo tipo, o primeiro da lista é o que já vem em uso no cadastro
 * (o jogador troca na aba Prêmios).
 *
 * Comuns: os de boas-vindas, liberados no cadastro. Raros ou acima: missões secretas.
 * O catálogo antigo (por acerto de palpite, semana, temporada, liga) está no histórico do Git.
 */
class CosmeticSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'key' => 'welcome-banner', 'slot' => CosmeticSlot::Banner, 'name' => 'Pôr do Sol na Quadra',
                'description' => 'Grade da quadra contra o céu laranja do fim de tarde.',
            ],
            [
                'key' => 'welcome-banner-arena', 'slot' => CosmeticSlot::Banner, 'name' => 'Ginásio Lotado',
                'description' => 'Refletores acesos e a arquibancada inteira de pé.',
            ],
            [
                'key' => 'welcome-banner-street', 'slot' => CosmeticSlot::Banner, 'name' => 'Quadra do Bairro',
                'description' => 'Grafite no muro, escada de incêndio e jogo valendo.',
            ],
            [
                'key' => 'welcome-frame', 'slot' => CosmeticSlot::Frame, 'name' => 'Aro Laranja',
                'description' => 'A moldura de quem acabou de chegar na quadra.',
            ],
            [
                'key' => 'welcome-frame-parquet', 'slot' => CosmeticSlot::Frame, 'name' => 'Parquet',
                'description' => 'Madeira encerada de ginásio, com as juntas das tábuas.',
            ],
            [
                'key' => 'welcome-frame-paint', 'slot' => CosmeticSlot::Frame, 'name' => 'Garrafão',
                'description' => 'Pintura do garrafão, com a linha e as marcações do lance livre.',
            ],
            [
                'key' => 'welcome-title', 'slot' => CosmeticSlot::Title, 'name' => 'Rookie',
                'description' => 'Primeira temporada na quadra. Todo mundo começa em algum lugar.',
            ],
            [
                'key' => 'welcome-title-ballhog', 'slot' => CosmeticSlot::Title, 'name' => 'Fominha',
                'description' => 'Pegou na bola, é arremesso. Passe é só em último caso.',
            ],
            [
                'key' => 'welcome-title-sixth', 'slot' => CosmeticSlot::Title, 'name' => 'Sexto Homem',
                'description' => 'Sai do banco e muda o jogo.',
            ],
        ];

        // Missões secretas (app/Services/Missions): raras ou acima, liberadas por uma façanha
        // (num jogo, ou com amigos nas ligas). Antes disso o jogador só vê "???" e a dica.
        $missions = [
            [
                'key' => 'mission-clutch', 'slot' => CosmeticSlot::Title, 'name' => 'Clutch', 'rarity' => 'RARE',
                'description' => 'Seu cestinha fez a última cesta do jogo, e foi ela que decidiu.',
                'trigger' => ClutchMission::TRIGGER,
                'hint' => 'Confie no seu cestinha quando o relógio apertar.',
            ],
            [
                'key' => 'mission-splash-brothers', 'slot' => CosmeticSlot::Title, 'name' => 'Splash Brothers', 'rarity' => 'RARE',
                'description' => 'Amigos que finalizaram 3 ligas juntos. Dupla que não se separa.',
                'trigger' => SplashBrothersMission::TRIGGER,
                'hint' => 'Forme amizades consistentes.',
            ],
        ];

        foreach ($items as $item) {
            Cosmetic::updateOrCreate(['key' => $item['key']], $item + [
                'rarity' => 'COMMON',
                'source' => 'SIGNUP',
                'trigger' => Cosmetic::TRIGGER_SIGNUP,
                'secret' => false,
                'hint' => null,
                'asset_key' => $item['key'],
                'season_id' => null,
            ]);
        }

        foreach ($missions as $item) {
            Cosmetic::updateOrCreate(['key' => $item['key']], $item + [
                'source' => 'MISSION',
                'secret' => true,
                'asset_key' => $item['key'],
                'season_id' => null,
            ]);
        }

        $items = [...$items, ...$missions];

        // Tira do catálogo o que saiu da lista e ninguém tem (ex.: o catálogo antigo).
        Cosmetic::whereNotIn('key', array_column($items, 'key'))
            ->whereDoesntHave('owners')
            ->delete();
    }
}
