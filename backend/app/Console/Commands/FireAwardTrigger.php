<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Cosmetics\AwardService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Dispara um gatilho de prêmio para todos os usuários (ou um só). Serve para quem já tinha
 * conta quando um prêmio novo entrou no catálogo. Quem já tem o prêmio não muda nada.
 */
#[Signature('awards:fire {trigger : ex.: SIGNUP} {--user= : @ de um usuário só} {--equip : equipa nos tipos vazios}')]
#[Description('Libera os prêmios de um gatilho para quem ainda não tem')]
class FireAwardTrigger extends Command
{
    public function handle(AwardService $awards): int
    {
        $trigger = strtoupper($this->argument('trigger'));
        $handle = $this->option('user') ? ltrim(strtolower($this->option('user')), '@') : null;

        $users = User::query()->when($handle, fn ($q) => $q->where('handle', $handle));
        if ($handle && ! $users->exists()) {
            $this->error("Usuário @{$handle} não encontrado.");

            return self::FAILURE;
        }

        $total = 0;
        $users->lazyById()->each(function (User $user) use ($awards, $trigger, &$total) {
            $granted = $awards->fire($user, $trigger, (bool) $this->option('equip'));
            $total += $granted->count();
            if ($granted->isNotEmpty()) {
                $this->line("@{$user->handle}: ".$granted->pluck('name')->join(', '));
            }
        });

        $this->info("{$total} prêmio(s) liberado(s) pelo gatilho {$trigger}.");

        return self::SUCCESS;
    }
}
