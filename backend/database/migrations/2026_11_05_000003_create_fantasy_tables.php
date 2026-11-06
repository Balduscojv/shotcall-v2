<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_prices', function (Blueprint $t) {
            $t->unsignedBigInteger('player_id');
            $t->foreign('player_id')->references('id')->on('players');
            $t->foreignId('round_id')->constrained();
            $t->decimal('price', 6, 2);
            $t->decimal('variation', 6, 2)->default(0);
            $t->decimal('min_to_gain', 6, 2);
            $t->primary(['player_id', 'round_id']);
        });

        Schema::create('player_game_scores', function (Blueprint $t) {
            $t->string('game_id', 12);
            $t->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $t->unsignedBigInteger('player_id');
            $t->decimal('points', 6, 2);
            $t->jsonb('breakdown');
            $t->primary(['game_id', 'player_id']);
        });

        Schema::create('player_round_scores', function (Blueprint $t) {
            $t->unsignedBigInteger('player_id');
            $t->foreignId('round_id')->constrained();
            $t->unsignedTinyInteger('games')->default(0);
            $t->decimal('points', 7, 2)->default(0);
            $t->decimal('average', 6, 2)->default(0);
            $t->primary(['player_id', 'round_id']);
        });

        Schema::create('fantasy_teams', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('round_id')->constrained();
            $t->unsignedBigInteger('cestinha_id')->nullable();
            $t->decimal('balance', 7, 2);
            $t->decimal('patrimony', 7, 2);
            $t->decimal('points', 7, 2)->default(0);
            $t->timestamps();
            $t->unique(['user_id', 'round_id']);
        });

        Schema::create('fantasy_team_players', function (Blueprint $t) {
            $t->foreignId('fantasy_team_id')->constrained()->cascadeOnDelete();
            $t->string('slot', 2); // G1, G2, F1, F2, C
            $t->unsignedBigInteger('player_id');
            $t->foreign('player_id')->references('id')->on('players');
            $t->decimal('bought_at_price', 6, 2);
            $t->primary(['fantasy_team_id', 'slot']);
        });
    }

    public function down(): void
    {
        foreach (['fantasy_team_players', 'fantasy_teams', 'player_round_scores', 'player_game_scores', 'player_prices'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
