<?php

namespace App\Enums;

enum AlbumType: string
{
    case Album = 'album';
    case Ep = 'ep';
    case Single = 'single';
    case Live = 'live';
    case Compilation = 'compilation';
    case Soundtrack = 'soundtrack';
    case Other = 'other';

    /**
     * Map a MusicBrainz release group type: a meaningful secondary type wins over the primary one.
     *
     * @param  array<int, string>  $secondary
     */
    public static function fromMusicBrainz(?string $primary, array $secondary): self
    {
        foreach (['Live' => self::Live, 'Compilation' => self::Compilation, 'Soundtrack' => self::Soundtrack] as $type => $case) {
            if (in_array($type, $secondary, true)) {
                return $case;
            }
        }

        return match ($primary) {
            'Album' => self::Album,
            'EP' => self::Ep,
            'Single' => self::Single,
            default => self::Other,
        };
    }

    /**
     * Label shown in the interface.
     */
    public function label(): string
    {
        return match ($this) {
            self::Album => 'Album',
            self::Ep => 'EP',
            self::Single => 'Single',
            self::Live => 'Live',
            self::Compilation => 'Compilation',
            self::Soundtrack => 'Bande originale',
            self::Other => 'Autre',
        };
    }
}
