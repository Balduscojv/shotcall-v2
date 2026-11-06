<?php

namespace Database\Seeders;

use App\Enums\CosmeticSlot;
use App\Models\Cosmetic;
use Illuminate\Database\Seeder;

/**
 * Catálogo de prêmios. Três tipos: banner de fundo, moldura da foto e título do @.
 * `trigger` = o que libera (SIGNUP = cadastro); sem trigger, só a moderação libera.
 * `asset_key` = qual visual o front desenha (web/src/lib/cosmetics.ts).
 *
 * Com mais de um do mesmo tipo, o primeiro da lista é o que já vem em uso no cadastro
 * (o jogador troca na aba Prêmios).
 *
 * Por enquanto só os genéricos de boas-vindas. Os exclusivos entram depois; o catálogo
 * antigo (por acerto de palpite, semana, temporada, liga) está no histórico do Git.
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
                'key' => 'welcome-title', 'slot' => CosmeticSlot::Title, 'name' => 'Novato da Quadra',
                'description' => 'Todo mundo começa em algum lugar.',
            ],
        ];

        foreach ($items as $item) {
            Cosmetic::updateOrCreate(['key' => $item['key']], $item + [
                'rarity' => 'COMMON',
                'source' => 'SIGNUP',
                'trigger' => Cosmetic::TRIGGER_SIGNUP,
                'asset_key' => $item['key'],
                'season_id' => null,
            ]);
        }

        // Tira do catálogo o que saiu da lista e ninguém tem (ex.: o catálogo antigo).
        Cosmetic::whereNotIn('key', array_column($items, 'key'))
            ->whereDoesntHave('owners')
            ->delete();
    }
}
