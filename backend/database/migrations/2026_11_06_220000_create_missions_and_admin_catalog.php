<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo editável pela moderação:
 * - prêmios ganham `active` (desativado = não é mais liberado), `color` (moldura/título) e
 *   `image_path` (banner enviado);
 * - missões saem do código: cada uma tem uma condição do catálogo com parâmetros, recompensas,
 *   segredo + dica, ativa e período. O segredo e a dica passam do prêmio para a missão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cosmetics', function (Blueprint $t) {
            $t->boolean('active')->default(true);
            $t->string('color', 7)->nullable();
            $t->string('image_path')->nullable();
        });

        Schema::create('missions', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name', 60);
            $t->string('description', 200)->nullable();
            $t->string('condition', 40);      // tipo do catálogo (app/Services/Missions/Conditions)
            $t->json('params');               // números da condição (ex.: {"min_points": 250})
            $t->boolean('secret')->default(true);
            $t->string('hint', 120)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
            $t->index(['condition', 'active']);
        });

        Schema::create('mission_rewards', function (Blueprint $t) {
            $t->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('cosmetic_id')->constrained()->cascadeOnDelete();
            $t->primary(['mission_id', 'cosmetic_id']);
        });

        // Os gatilhos MISSION:* viram missões (MissionSeeder); no prêmio só fica o SIGNUP.
        DB::table('cosmetics')->where('trigger', 'like', 'MISSION:%')->update(['trigger' => null]);

        Schema::table('cosmetics', fn (Blueprint $t) => $t->dropColumn(['secret', 'hint']));
    }

    public function down(): void
    {
        Schema::table('cosmetics', function (Blueprint $t) {
            $t->boolean('secret')->default(false);
            $t->string('hint')->nullable();
        });
        Schema::dropIfExists('mission_rewards');
        Schema::dropIfExists('missions');
        Schema::table('cosmetics', fn (Blueprint $t) => $t->dropColumn(['active', 'color', 'image_path']));
    }
};
