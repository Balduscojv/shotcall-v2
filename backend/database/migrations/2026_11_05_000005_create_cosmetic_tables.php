<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cosmetics', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name');
            // FRAME, BANNER, TITLE, SHOWCASE, BALL, COURT, CELEBRATION, CARD_FRAME, CESTINHA_FX, CREST_TAG
            $t->string('slot', 12);
            $t->string('rarity', 9); // COMMON, RARE, EPIC, LEGENDARY
            $t->string('source', 8); // GAME, WEEK, SEASON, BRACKET, LEAGUE
            $t->string('requirement')->nullable(); // ex.: GAME:GOLD, WEEK:PERFECT
            $t->foreignId('season_id')->nullable()->constrained();
            $t->string('package_key')->nullable()->index(); // pacotes lendários
            $t->string('asset_key')->nullable();
            $t->string('animation')->nullable(); // arquivo Rive
            $t->jsonb('anchors')->nullable(); // gabarito do perfil
            $t->timestamps();
            $t->index(['season_id', 'source']);
        });

        Schema::create('user_cosmetics', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('cosmetic_id')->constrained();
            $t->timestamp('unlocked_at');
            $t->string('unlock_reason'); // ex.: "Semana Perfeita · Rodada 12"
            $t->boolean('equipped')->default(false);
            $t->primary(['user_id', 'cosmetic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_cosmetics');
        Schema::dropIfExists('cosmetics');
    }
};
