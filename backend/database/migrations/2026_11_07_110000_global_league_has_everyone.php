<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Liga Global sem limite de membros (max_members nulo) e com todo mundo dentro: quem foi criado
 * fora do cadastro (seeder, moderação, testes manuais) entra agora, pontuando desde o começo.
 * Só a conta de sistema, dona da liga, fica de fora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', fn (Blueprint $t) => $t->unsignedInteger('max_members')->nullable()->default(20)->change());

        $global = DB::table('leagues')->where('is_global', true)->first();
        if (! $global) {
            return;
        }
        DB::table('leagues')->where('id', $global->id)->update(['max_members' => null]);

        $now = now();
        DB::table('users')
            ->where('id', '!=', $global->owner_id)
            ->whereNotExists(fn ($q) => $q->from('league_members')->where('league_id', $global->id)->whereColumn('league_members.user_id', 'users.id'))
            ->orderBy('id')
            ->select('id')
            ->chunk(1000, fn ($users) => DB::table('league_members')->insert($users->map(fn ($u) => [
                'league_id' => $global->id, 'user_id' => $u->id, 'role' => 'MEMBER',
                'joined_round_number' => null, 'created_at' => $now, 'updated_at' => $now,
            ])->all()));
    }

    public function down(): void
    {
        DB::table('leagues')->whereNull('max_members')->update(['max_members' => 65000]);
        Schema::table('leagues', fn (Blueprint $t) => $t->unsignedInteger('max_members')->default(20)->nullable(false)->change());
    }
};
