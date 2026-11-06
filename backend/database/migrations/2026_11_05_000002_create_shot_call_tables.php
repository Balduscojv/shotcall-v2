<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_shot_calls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('game_id', 12);
            $t->foreign('game_id')->references('id')->on('games');
            $t->unsignedBigInteger('winner_team_id');
            $t->string('margin_band', 9); // M1_5, M6_10, M11_15, M16_PLUS
            $t->unsignedBigInteger('mvp_player_id');
            $t->string('status', 8)->default('OPEN'); // OPEN, LOCKED, SETTLED, VOID
            $t->unsignedTinyInteger('correct')->nullable();
            $t->unsignedTinyInteger('points')->nullable();
            $t->timestamp('settled_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'game_id']);
            $t->index(['user_id', 'settled_at']);
        });

        Schema::create('weekly_cards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('round_id')->unique()->constrained();
            $t->timestamps();
        });

        Schema::create('weekly_card_games', function (Blueprint $t) {
            $t->foreignId('weekly_card_id')->constrained()->cascadeOnDelete();
            $t->string('game_id', 12);
            $t->foreign('game_id')->references('id')->on('games');
            $t->primary(['weekly_card_id', 'game_id']);
        });

        Schema::create('weekly_picks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('weekly_card_id')->constrained();
            $t->jsonb('answers'); // {games: {gameId: teamId}, week_mvp: playerId, most_wins_team: teamId}
            $t->string('status', 8)->default('OPEN');
            $t->unsignedTinyInteger('correct')->nullable();
            $t->unsignedTinyInteger('total')->nullable();
            $t->unsignedSmallInteger('points')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'weekly_card_id']);
        });

        Schema::create('season_picks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('season_id')->constrained();
            $t->jsonb('answers'); // champion, east_finalist, west_finalist, mvp, roy, dpoy, scoring_leader
            $t->unsignedTinyInteger('correct')->nullable();
            $t->unsignedSmallInteger('points')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'season_id']);
        });

        Schema::create('playoff_brackets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('season_id')->constrained();
            $t->jsonb('picks'); // {serieKey: teamId} para as 15 séries
            $t->unsignedTinyInteger('correct')->nullable();
            $t->unsignedSmallInteger('points')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'season_id']);
        });

        Schema::create('user_accuracy', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('mode', 8); // GAME, WEEK, SEASON, BRACKET
            $t->decimal('rate', 5, 4)->nullable();
            $t->unsignedSmallInteger('counted')->default(0);
            $t->string('tier', 8)->nullable();
            $t->timestamps();
            $t->primary(['user_id', 'mode']);
        });
    }

    public function down(): void
    {
        foreach (['user_accuracy', 'playoff_brackets', 'season_picks', 'weekly_picks', 'weekly_card_games', 'weekly_cards', 'game_shot_calls'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
