<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Troféus de liga (plano: "Prêmios de liga"): quem terminou a temporada no pódio de cada
 * ranking da liga. Guarda nome e cor da liga na hora, para o troféu continuar no perfil mesmo
 * se a liga for excluída ou renomeada depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_trophies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('league_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('season_id')->constrained();
            $t->string('competition', 8); // FANTASY, SHOTCALL
            $t->unsignedSmallInteger('rank');
            $t->unsignedInteger('members_count');
            $t->string('league_name', 40);
            $t->string('league_color', 7);
            $t->decimal('score', 10, 2);
            $t->timestamps();
            $t->unique(['league_id', 'user_id', 'competition']);
            $t->index(['user_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_trophies');
    }
};
