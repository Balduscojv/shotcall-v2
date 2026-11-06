<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name', 40);
            $t->string('color', 7);
            $t->string('description', 200)->nullable();
            $t->foreignId('owner_id')->constrained('users');
            $t->string('privacy', 7)->default('PRIVATE'); // PRIVATE, PUBLIC
            $t->string('invite_code', 6)->unique();
            $t->jsonb('competitions'); // ["FANTASY", "SHOTCALL"]
            $t->unsignedSmallInteger('max_members')->default(20);
            $t->boolean('is_global')->default(false);
            $t->timestamps();
        });

        Schema::create('league_members', function (Blueprint $t) {
            $t->foreignId('league_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 6)->default('MEMBER'); // OWNER, ADMIN, MEMBER
            $t->unsignedSmallInteger('joined_round_number')->nullable(); // pontua a partir desta rodada
            $t->timestamps();
            $t->primary(['league_id', 'user_id']);
        });

        Schema::create('league_standings', function (Blueprint $t) {
            $t->foreignId('league_id')->constrained()->cascadeOnDelete();
            $t->foreignId('round_id')->constrained();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->decimal('fantasy_points', 8, 2)->default(0);
            $t->unsignedInteger('shotcall_points')->default(0);
            $t->decimal('patrimony', 7, 2)->default(0);
            $t->unsignedSmallInteger('rank')->nullable();
            $t->primary(['league_id', 'round_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['league_standings', 'league_members', 'leagues'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
