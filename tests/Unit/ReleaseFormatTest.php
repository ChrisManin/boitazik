<?php

use App\Enums\ReleaseFormat;

test('it maps MusicBrainz formats to a release format', function (?string $musicBrainz, ReleaseFormat $expected) {
    expect(ReleaseFormat::fromMusicBrainz($musicBrainz))->toBe($expected);
})->with([
    'CD' => ['CD', ReleaseFormat::Cd],
    'Enhanced CD' => ['Enhanced CD', ReleaseFormat::Cd],
    'SACD' => ['SACD', ReleaseFormat::Cd],
    'HDCD' => ['HDCD', ReleaseFormat::Cd],
    '12" Vinyl' => ['12" Vinyl', ReleaseFormat::Vinyl],
    '7" Vinyl' => ['7" Vinyl', ReleaseFormat::Vinyl],
    'Vinyl' => ['Vinyl', ReleaseFormat::Vinyl],
    'Cassette' => ['Cassette', ReleaseFormat::Cassette],
    'Digital Media' => ['Digital Media', ReleaseFormat::Digital],
    'DVD' => ['DVD', ReleaseFormat::Other],
    'CD-ROM' => ['CD-ROM', ReleaseFormat::Other],
    'unknown' => [null, ReleaseFormat::Other],
]);
