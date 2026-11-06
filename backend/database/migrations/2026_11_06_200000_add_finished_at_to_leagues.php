<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Liga finalizada: a disputa acabou e o ranking final está fechado. Conta para missões como
// o Splash Brothers (amigos que finalizaram ligas juntos).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', fn (Blueprint $t) => $t->timestamp('finished_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('leagues', fn (Blueprint $t) => $t->dropColumn('finished_at'));
    }
};
