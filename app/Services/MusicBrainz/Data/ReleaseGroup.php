<?php

namespace App\Services\MusicBrainz\Data;

final class ReleaseGroup
{
    /**
     * @param  array<int, ArtistCredit>  $artists
     */
    public function __construct(
        public readonly string $mbid,
        public readonly string $title,
        public readonly ?string $primaryType,
        public readonly ?int $firstReleaseYear,
        public readonly array $artists,
    ) {}

    public static function fromArray(array $data): self
    {
        $date = $data['first-release-date'] ?? '';

        return new self(
            mbid: $data['id'],
            title: $data['title'],
            primaryType: ($data['primary-type'] ?? '') ?: null,
            firstReleaseYear: $date !== '' ? (int) substr($date, 0, 4) : null,
            artists: array_map(
                fn (array $credit) => ArtistCredit::fromArray($credit),
                $data['artist-credit'] ?? [],
            ),
        );
    }
}
