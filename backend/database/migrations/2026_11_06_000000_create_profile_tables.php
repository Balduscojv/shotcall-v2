<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('banner_url')->nullable()->after('avatar_url');
        });

        // Amizade é um pedido: quem pediu (requester) e quem recebe (addressee). Aceito = amigos.
        Schema::create('friendships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('addressee_id')->constrained('users')->cascadeOnDelete();
            $t->string('status', 8)->default('PENDING'); // PENDING, ACCEPTED
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
            $t->unique(['requester_id', 'addressee_id']);
            $t->index(['addressee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('banner_url'));
    }
};
