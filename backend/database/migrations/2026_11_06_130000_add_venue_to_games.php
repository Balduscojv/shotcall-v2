<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ginásio e TV nacional do jogo, vindos do calendário da NBA, para o card do jogo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $t) {
            $t->string('arena')->nullable()->after('away_score');
            $t->string('national_tv')->nullable()->after('arena');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $t) {
            $t->dropColumn(['arena', 'national_tv']);
        });
    }
};
