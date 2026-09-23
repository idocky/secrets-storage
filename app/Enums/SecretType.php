<?php

namespace App\Enums;

enum SecretType: string
{
    case Password = 'password';
    case File = 'file';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Password => 'Password / API key',
            self::File => 'File',
            self::Note => 'Secure Note',
        };
    }
}
