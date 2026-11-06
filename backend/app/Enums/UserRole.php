<?php

namespace App\Enums;

/** Papel do usuário no sistema (não confundir com o papel dentro de uma liga). */
enum UserRole: string
{
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Player = 'player';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Moderator => 'Moderador',
            self::Player => 'Jogador',
        };
    }
}
