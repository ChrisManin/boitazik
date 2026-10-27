<?php

namespace App\Services\MusicBrainz\Data;

final class Artist
{
    public function __construct(
        public readonly string $mbid,
        public readonly string $name,
        // ISO 3166-1 alpha-2 code
        public readonly ?string $country,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            mbid: $data['id'],
            name: $data['name'],
            country: ($data['country'] ?? '') ?: null,
        );
    }
}
