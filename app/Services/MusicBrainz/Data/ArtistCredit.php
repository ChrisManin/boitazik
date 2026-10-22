<?php

namespace App\Services\MusicBrainz\Data;

final class ArtistCredit
{
    public function __construct(
        public readonly string $mbid,
        public readonly string $name,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            mbid: $data['artist']['id'],
            name: $data['artist']['name'] ?? $data['name'],
        );
    }
}
