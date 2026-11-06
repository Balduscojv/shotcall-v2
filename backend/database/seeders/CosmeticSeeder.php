<?php

namespace Database\Seeders;

use App\Enums\CosmeticSlot;
use App\Models\Cosmetic;
use Illuminate\Database\Seeder;

/**
 * Catálogo de prêmios. Três tipos: banner de fundo, moldura da foto e título do @.
 * `trigger` SIGNUP = liberado no cadastro. Os outros saem pelas missões (MissionSeeder e a
 * moderação) ou direto pela moderação.
 * `asset_key` = qual visual o front desenha (web/src/lib/cosmetics.ts).
 *
 * Com mais de um do mesmo tipo, o primeiro da lista é o que já vem em uso no cadastro
 * (o jogador troca na aba Prêmios).
 *
 * Comuns: os de boas-vindas. Raros ou acima: recompensas de missão.
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

        // Recompensas das missões iniciais (MissionSeeder). Quem cumpre é a missão; o prêmio
        // só diz como é.
        $rewards = [
            [
                'key' => 'mission-clutch', 'slot' => CosmeticSlot::Title, 'name' => 'Clutch', 'rarity' => 'RARE',
                'description' => 'Seu cestinha fez a última cesta do jogo, e foi ela que decidiu.',
            ],
            [
                'key' => 'mission-splash-brothers', 'slot' => CosmeticSlot::Title, 'name' => 'Splash Brothers', 'rarity' => 'RARE',
                'description' => 'Amigos que finalizaram 3 ligas juntos. Dupla que não se separa.',
            ],
            // Prêmios de liga (plano: "Prêmios de liga"). O nome da liga vai no motivo do card.
            [
                'key' => 'league-champion', 'slot' => CosmeticSlot::Title, 'name' => 'Campeão da Liga', 'rarity' => 'EPIC',
                'description' => 'Terminou a temporada em 1º numa liga de pelo menos 5 membros.',
            ],
            [
                'key' => 'league-podium', 'slot' => CosmeticSlot::Frame, 'name' => 'Pódio da Temporada', 'rarity' => 'EPIC',
                'description' => 'Ouro, prata e bronze: terminou no top 3 de uma liga com pelo menos 10 membros.',
            ],
        ];

        // firstOrCreate: depois de criado, o prêmio é da moderação (nome, cor, ativo...);
        // rodar o seeder de novo não desfaz o que ela mudou.
        foreach ($items as $item) {
            Cosmetic::firstOrCreate(['key' => $item['key']], $item + [
                'rarity' => 'COMMON',
                'source' => 'SIGNUP',
                'trigger' => Cosmetic::TRIGGER_SIGNUP,
                'asset_key' => $item['key'],
                'season_id' => null,
            ]);
        }

        foreach ($rewards as $item) {
            Cosmetic::firstOrCreate(['key' => $item['key']], $item + [
                'source' => 'MISSION',
                'trigger' => null,
                'asset_key' => $item['key'],
                'season_id' => null,
            ]);
        }

        // Exclusivos com arte própria (web/src/components/cosmetics/FrameArt.tsx). Sem gatilho:
        // saem pela moderação ou por uma missão que os tenha como recompensa.
        $exclusives = [
            [
                'key' => 'frame-reaper', 'slot' => CosmeticSlot::Frame, 'name' => 'Capuz do Ceifador',
                'description' => 'Capuz negro e foice afiada: quem usa ceifa os palpites alheios.',
            ],
        ];

        foreach ($exclusives as $item) {
            Cosmetic::firstOrCreate(['key' => $item['key']], $item + [
                'rarity' => 'LEGENDARY',
                'source' => 'SPECIAL',
                'trigger' => null,
                'asset_key' => $item['key'],
                'season_id' => null,
            ]);
        }
    }
}
