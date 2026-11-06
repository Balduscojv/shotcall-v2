<?php

namespace App\Services\Cosmetics;

use App\Enums\CosmeticSlot;
use App\Models\Cosmetic;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Regras dos prêmios: liberar (por gatilho ou pela moderação), remover e equipar.
 * Admin tem acesso a todos: pode equipar qualquer prêmio sem tê-lo liberado.
 */
class AwardService
{
    /** Motivo que aparece no card quando o prêmio veio de um gatilho do sistema. */
    private const TRIGGER_REASONS = [
        Cosmetic::TRIGGER_SIGNUP => 'Boas-vindas à quadra',
    ];

    /** Libera um prêmio. Devolve false se o usuário já tinha. */
    public function grant(User $user, Cosmetic $cosmetic, string $reason, ?User $grantedBy = null): bool
    {
        $result = $user->cosmetics()->syncWithoutDetaching([
            $cosmetic->id => ['unlocked_at' => now(), 'unlock_reason' => $reason, 'granted_by' => $grantedBy?->id],
        ]);

        return $result['attached'] !== [];
    }

    /**
     * Dispara um gatilho: libera todos os prêmios ligados a ele que o usuário ainda não tem.
     * Com $equipIfEmpty, já equipa nos tipos em que o usuário não usa nada.
     *
     * @return Collection<int, Cosmetic> prêmios liberados agora
     */
    public function fire(User $user, string $trigger, bool $equipIfEmpty = false): Collection
    {
        $reason = self::TRIGGER_REASONS[$trigger] ?? 'Conquista';

        // Por id: com vários do mesmo tipo, o primeiro cadastrado é o que vai para uso.
        $granted = Cosmetic::triggeredBy($trigger)->orderBy('id')->get()
            ->filter(fn (Cosmetic $cosmetic) => $this->grant($user, $cosmetic, $reason))
            ->values();

        if ($equipIfEmpty) {
            foreach ($granted as $cosmetic) {
                $column = $cosmetic->slot->equippedColumn();
                $user->{$column} ??= $cosmetic->id;
            }
            $user->save();
        }

        return $granted;
    }

    /** Tira o prêmio do usuário e, se estava em uso, desequipa. */
    public function revoke(User $user, Cosmetic $cosmetic): bool
    {
        $column = $cosmetic->slot->equippedColumn();
        if ($user->{$column} === $cosmetic->id) {
            $user->{$column} = null;
            $user->save();
        }

        return $user->cosmetics()->detach($cosmetic->id) > 0;
    }

    /** Equipa um prêmio no tipo indicado, ou tira o que está em uso (null). */
    public function equip(User $user, CosmeticSlot $slot, ?Cosmetic $cosmetic): void
    {
        if ($cosmetic !== null) {
            if ($cosmetic->slot !== $slot) {
                throw ValidationException::withMessages(['key' => "Esse prêmio não é do tipo {$slot->label()}."]);
            }
            if (! $this->canUse($user, $cosmetic)) {
                throw ValidationException::withMessages(['key' => 'Você ainda não liberou esse prêmio.']);
            }
        }

        $user->{$slot->equippedColumn()} = $cosmetic?->id;
        $user->save();
    }

    public function canUse(User $user, Cosmetic $cosmetic): bool
    {
        return $user->isAdmin() || $user->cosmetics()->whereKey($cosmetic->id)->exists();
    }

    /**
     * Prêmios que o usuário pode equipar: os liberados, ou o catálogo inteiro para admin.
     * Cada item vem com `owned` e `is_equipped` preenchidos.
     *
     * @return Collection<int, Cosmetic>
     */
    public function usableBy(User $user): Collection
    {
        $catalog = $this->catalogFor($user);

        return $user->isAdmin() ? $catalog : $catalog->filter->owned->values();
    }

    /**
     * Catálogo inteiro do ponto de vista de um usuário: cada item com `owned`, `is_equipped`
     * e, se ele tem, o pivot com data e motivo.
     *
     * @return Collection<int, Cosmetic>
     */
    public function catalogFor(User $user): Collection
    {
        $owned = $user->cosmetics()->get()->keyBy('id');
        $equipped = $user->equippedIds();

        return Cosmetic::showcaseOrder()->get()->map(function (Cosmetic $cosmetic) use ($owned, $equipped) {
            $mine = $owned->get($cosmetic->id);
            $cosmetic->setRelation('pivot', $mine?->pivot);
            $cosmetic->setAttribute('owned', $mine !== null);
            $cosmetic->setAttribute('is_equipped', in_array($cosmetic->id, $equipped, true));

            return $cosmetic;
        });
    }
}
