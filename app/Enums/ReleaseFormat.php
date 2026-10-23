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
     * Maps a MusicBrainz medium format ("CD", "12\" Vinyl"…).
     */
    public static function fromMusicBrainz(?string $format): self
    {
        return match (true) {
            $format === null => self::Other,
            str_contains($format, 'Vinyl') => self::Vinyl,
            $format === 'Cassette' => self::Cassette,
            $format === 'Digital Media' => self::Digital,
            // CD, Enhanced CD, SACD, HDCD, SHM-CD… but not CD-ROM or CD-R
            str_ends_with($format, 'CD') => self::Cd,
            default => self::Other,
        };
    }

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
