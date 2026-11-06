<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Define o papel de um usuário pelo terminal. É o jeito de criar o primeiro admin. */
#[Signature('user:role {handle : @ do usuário} {role : admin, moderator ou player}')]
#[Description('Define o papel de um usuário (admin, moderator, player)')]
class SetUserRole extends Command
{
    public function handle(): int
    {
        $role = UserRole::tryFrom(strtolower($this->argument('role')));

        if ($role === null) {
            $this->error('Papel inválido. Use: '.implode(', ', array_column(UserRole::cases(), 'value')).'.');

            return self::INVALID;
        }

        $handle = ltrim(strtolower($this->argument('handle')), '@');
        $user = User::where('handle', $handle)->first();

        if ($user === null) {
            $this->error("Usuário @{$handle} não encontrado.");

            return self::FAILURE;
        }

        $user->role = $role;
        $user->save();

        $this->info("@{$user->handle} agora é {$role->label()}.");

        return self::SUCCESS;
    }
}
