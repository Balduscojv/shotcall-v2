<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prêmios: três tipos (BANNER, FRAME, TITLE), liberados por gatilho ou pela moderação,
 * e um equipado por tipo guardado no próprio usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cosmetics', function (Blueprint $t) {
            // Condição que libera o prêmio: SIGNUP, GAME:GOLD, ... Nulo = só pela moderação.
            $t->renameColumn('requirement', 'trigger');
            $t->string('description')->nullable()->after('name');
        });

        Schema::table('cosmetics', function (Blueprint $t) {
            $t->index('trigger');
        });

        Schema::table('user_cosmetics', function (Blueprint $t) {
            $t->dropColumn('equipped');
            // Quem liberou na mão (admin). Nulo = liberado por gatilho do sistema.
            $t->foreignId('granted_by')->nullable()->after('unlock_reason')->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('equipped_banner_id')->nullable()->after('role')->constrained('cosmetics')->nullOnDelete();
            $t->foreignId('equipped_frame_id')->nullable()->after('equipped_banner_id')->constrained('cosmetics')->nullOnDelete();
            $t->foreignId('equipped_title_id')->nullable()->after('equipped_frame_id')->constrained('cosmetics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('equipped_title_id');
            $t->dropConstrainedForeignId('equipped_frame_id');
            $t->dropConstrainedForeignId('equipped_banner_id');
        });

        Schema::table('user_cosmetics', function (Blueprint $t) {
            $t->dropConstrainedForeignId('granted_by');
            $t->boolean('equipped')->default(false);
        });

        Schema::table('cosmetics', function (Blueprint $t) {
            $t->dropIndex(['trigger']);
            $t->dropColumn('description');
            $t->renameColumn('trigger', 'requirement');
        });
    }
};
