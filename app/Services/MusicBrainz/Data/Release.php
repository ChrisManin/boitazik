<?php

namespace App\Services\MusicBrainz\Data;

// One physical edition (pressing) of a release group
final class Release
{
    public function __construct(
        public readonly string $mbid,
        public readonly string $title,
        public readonly ?string $format,
        public readonly ?string $label,
        public readonly ?string $catalogNumber,
        public readonly ?string $country,
        public readonly ?int $releaseYear,
        public readonly ?string $barcode,
        public readonly ReleaseGroup $releaseGroup,
    ) {}

    public static function fromArray(array $data): self
    {
        $date = $data['date'] ?? '';
        $labelInfo = $data['label-info'][0] ?? [];
        $group = $data['release-group'];

        return new self(
            mbid: $data['id'],
            title: $data['title'],
            // A multi-disc release is described by its first medium
            format: ($data['media'][0]['format'] ?? '') ?: null,
            label: ($labelInfo['label']['name'] ?? '') ?: null,
            catalogNumber: ($labelInfo['catalog-number'] ?? '') ?: null,
            country: ($data['country'] ?? '') ?: null,
            releaseYear: $date !== '' ? (int) substr($date, 0, 4) : null,
            barcode: ($data['barcode'] ?? '') ?: null,
            // Searches return the release group without its credits: the release's own credits stand in
            releaseGroup: ReleaseGroup::fromArray([
                ...$group,
                'artist-credit' => $group['artist-credit'] ?? $data['artist-credit'] ?? [],
            ]),
        );
    }
}
