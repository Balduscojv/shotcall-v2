<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posição detalhada do jogador: PG (armador), SG (ala-armador), SF (ala), PF (ala-pivô) e C
 * (pivô), do quadro de profundidade dos times. `position` (G, F, C) continua valendo para a
 * regra de escalação do fantasy: PG/SG contam como G, SF/PF como F.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $t) {
            $t->string('role', 2)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $t) {
            $t->dropColumn('role');
        });
    }
};
