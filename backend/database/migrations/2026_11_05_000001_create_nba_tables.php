<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $t) {
            $t->id();
            $t->string('label', 7)->unique(); // 2026-27
            $t->timestamp('starts_at');
            $t->timestamp('season_picks_lock_at'); // trava dos palpites da temporada
            $t->timestamp('playoffs_start_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
        });

        Schema::create('teams', function (Blueprint $t) {
            $t->unsignedBigInteger('id')->primary(); // id da NBA
            $t->string('tricode', 3)->unique();
            $t->string('city');
            $t->string('name');
            $t->string('conference', 4); // EAST, WEST
            $t->string('primary_color', 7);
            $t->string('secondary_color', 7);
            $t->timestamps();
        });

        Schema::create('players', function (Blueprint $t) {
            $t->unsignedBigInteger('id')->primary(); // id da NBA
            $t->unsignedBigInteger('team_id')->nullable()->index();
            $t->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            $t->string('full_name');
            $t->string('position', 1)->nullable()->index(); // G, F, C: vem do elenco, não do box score
            $t->string('jersey', 3)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('rounds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('season_id')->constrained();
            $t->unsignedSmallInteger('number');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->string('market_status', 8)->default('UPCOMING'); // UPCOMING, OPEN, LOCKED, SETTLING, SETTLED
            $t->timestamps();
            $t->unique(['season_id', 'number']);
        });

        Schema::create('games', function (Blueprint $t) {
            $t->string('id', 12)->primary(); // gameId da NBA
            $t->foreignId('season_id')->constrained();
            $t->foreignId('round_id')->nullable()->constrained();
            $t->string('phase', 10); // PRESEASON, REGULAR, PLAYIN, PLAYOFFS
            $t->unsignedBigInteger('home_team_id');
            $t->unsignedBigInteger('away_team_id');
            $t->foreign('home_team_id')->references('id')->on('teams');
            $t->foreign('away_team_id')->references('id')->on('teams');
            $t->timestamp('start_time')->index();
            $t->string('status', 10)->default('SCHEDULED')->index(); // SCHEDULED, LIVE, FINAL, POSTPONED
            $t->unsignedTinyInteger('period')->default(0);
            $t->string('clock', 8)->nullable();
            $t->unsignedSmallInteger('home_score')->default(0);
            $t->unsignedSmallInteger('away_score')->default(0);
            $t->unsignedInteger('last_action_number')->default(0);
            $t->timestamps();
        });

        Schema::create('game_periods', function (Blueprint $t) {
            $t->string('game_id', 12);
            $t->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $t->unsignedTinyInteger('period');
            $t->unsignedSmallInteger('home_points')->default(0);
            $t->unsignedSmallInteger('away_points')->default(0);
            $t->primary(['game_id', 'period']);
        });

        Schema::create('game_actions', function (Blueprint $t) {
            $t->string('game_id', 12);
            $t->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $t->unsignedInteger('action_number');
            $t->unsignedTinyInteger('period');
            $t->string('clock', 8);
            $t->unsignedBigInteger('team_id')->nullable();
            $t->unsignedBigInteger('player_id')->nullable();
            $t->string('action_type', 24);
            $t->string('shot_result', 6)->nullable(); // MADE, MISSED
            $t->decimal('x', 6, 2)->nullable();
            $t->decimal('y', 6, 2)->nullable();
            $t->unsignedTinyInteger('points')->default(0);
            $t->string('description')->nullable();
            $t->primary(['game_id', 'action_number']);
            $t->index(['game_id', 'period']);
        });

        Schema::create('player_game_stats', function (Blueprint $t) {
            $t->string('game_id', 12);
            $t->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $t->unsignedBigInteger('player_id');
            $t->foreign('player_id')->references('id')->on('players');
            $t->unsignedBigInteger('team_id');
            $t->string('minutes', 8)->nullable();
            foreach (['pts', 'reb', 'ast', 'stl', 'blk', 'tov', 'pf', 'tech', 'flagrant', 'fgm', 'fga', 'fg3m', 'ftm', 'fta'] as $col) {
                $t->unsignedSmallInteger($col)->default(0);
            }
            $t->smallInteger('plus_minus')->default(0);
            $t->primary(['game_id', 'player_id']);
        });
    }

    public function down(): void
    {
        foreach (['player_game_stats', 'game_actions', 'game_periods', 'games', 'rounds', 'players', 'teams', 'seasons'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
