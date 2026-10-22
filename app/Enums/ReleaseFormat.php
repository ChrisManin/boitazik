<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelectOptions;

enum ReleaseFormat: string
{
    use HasSelectOptions;

    case Cd = 'cd';
    case Vinyl = 'vinyl';
    case Cassette = 'cassette';
    case Digital = 'digital';
    case Other = 'other';

    /**
     * Label shown in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cd => 'CD',
            self::Vinyl => 'Vinyle',
            self::Cassette => 'Cassette',
            self::Digital => 'Numérique',
            self::Other => 'Autre',
        };
    }
}
