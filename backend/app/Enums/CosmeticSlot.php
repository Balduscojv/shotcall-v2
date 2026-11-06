<?php

namespace App\Enums;

/** Tipos de prêmio. Cada usuário equipa no máximo um de cada. */
enum CosmeticSlot: string
{
    case Banner = 'BANNER';
    case Frame = 'FRAME';
    case Title = 'TITLE';

    public function label(): string
    {
        return match ($this) {
            self::Banner => 'Banner',
            self::Frame => 'Moldura',
            self::Title => 'Título',
        };
    }

    /** Coluna do usuário que guarda o prêmio equipado deste tipo. */
    public function equippedColumn(): string
    {
        return 'equipped_'.strtolower($this->name).'_id';
    }

    /** Aceita "banner", "BANNER" etc. (como vem na URL). */
    public static function fromParam(string $value): ?self
    {
        return self::tryFrom(strtoupper($value));
    }
}
