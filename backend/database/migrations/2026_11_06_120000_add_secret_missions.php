<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Missões secretas: prêmios raros ou acima liberados por uma façanha num jogo (MISSION:...).
 * Antes de liberar, o jogador só vê "???" e a dica vaga.
 *
 * O placar em cada jogada vem do jogo-a-jogo da NBA (scoreHome/scoreAway) e serve para
 * julgar se uma cesta foi decisiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cosmetics', function (Blueprint $t) {
            $t->boolean('secret')->default(false)->after('trigger');
            $t->string('hint')->nullable()->after('secret');
        });

        Schema::table('game_actions', function (Blueprint $t) {
            $t->unsignedSmallInteger('score_home')->nullable()->after('points');
            $t->unsignedSmallInteger('score_away')->nullable()->after('score_home');
        });
    }

    public function down(): void
    {
        Schema::table('game_actions', function (Blueprint $t) {
            $t->dropColumn(['score_home', 'score_away']);
        });

        Schema::table('cosmetics', function (Blueprint $t) {
            $t->dropColumn(['secret', 'hint']);
        });
    }
};
