<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boost de palpites no fantasy: os acertos de shot call nos jogos da rodada multiplicam os
 * pontos do time. Guarda a parte de cada um para mostrar a conta: points = base × (1 + boost).
 * O palpite apurado passa a guardar quais mercados acertou, que é o que pesa no boost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fantasy_teams', function (Blueprint $t) {
            $t->decimal('base_points', 7, 2)->default(0)->after('points');
            $t->decimal('boost', 5, 4)->default(0)->after('base_points');
        });

        Schema::table('game_shot_calls', function (Blueprint $t) {
            $t->json('markets')->nullable()->after('points'); // {winner: bool, margin: bool, mvp: bool}
        });
    }

    public function down(): void
    {
        Schema::table('game_shot_calls', function (Blueprint $t) {
            $t->dropColumn('markets');
        });

        Schema::table('fantasy_teams', function (Blueprint $t) {
            $t->dropColumn(['base_points', 'boost']);
        });
    }
};
