<?php

namespace App\Services\Fantasy;

/**
 * Valida uma escalação antes de salvar. Devolve a lista de erros (vazia = válida).
 * $players: lista de ['id' => int, 'position' => 'G'|'F'|'C', 'price' => float].
 */
final class LineupValidator
{
    public function __construct(private readonly array $slots) {}

    /** @return list<string> */
    public function validate(array $players, ?int $cestinhaId, float $patrimony, bool $marketOpen): array
    {
        $errors = [];

        if (! $marketOpen) {
            $errors[] = 'market_closed';
        }

        $ids = array_column($players, 'id');
        if (count($ids) !== count(array_unique($ids))) {
            $errors[] = 'duplicate_player';
        }

        $count = array_count_values(array_column($players, 'position'));
        foreach ($this->slots as $position => $needed) {
            if (($count[$position] ?? 0) !== $needed) {
                $errors[] = "position_{$position}_needs_{$needed}";
            }
        }

        if ($cestinhaId === null || ! in_array($cestinhaId, $ids, true)) {
            $errors[] = 'cestinha_not_in_lineup';
        }

        $cost = round(array_sum(array_column($players, 'price')), 2);
        if ($cost > round($patrimony, 2)) {
            $errors[] = 'insufficient_balance';
        }

        return $errors;
    }
}
