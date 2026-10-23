<?php

namespace App\Actions;

use App\Enums\ReleaseFormat;
use App\Models\Release;
use App\Services\MusicBrainz\MusicBrainzClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

// Brings a MusicBrainz release (an exact edition) into the shared catalog, with its album
class ImportReleaseFromMusicBrainz
{
    public function __construct(
        private readonly MusicBrainzClient $musicBrainz,
        private readonly ImportAlbumFromMusicBrainz $importAlbum,
    ) {}

    /**
     * @throws RequestException|ConnectionException when MusicBrainz fails
     */
    public function handle(string $mbid): Release
    {
        // The lookup carries the release group and its credits: the album needs no extra call
        $data = $this->musicBrainz->lookupRelease($mbid);
        $album = $this->importAlbum->fromReleaseGroup($data->releaseGroup);

        return Release::firstOrCreate(['mbid' => $data->mbid], [
            'album_id' => $album->id,
            'format' => ReleaseFormat::fromMusicBrainz($data->format),
            'label' => $data->label,
            'catalog_number' => $data->catalogNumber,
            'country' => $data->country,
            'release_year' => $data->releaseYear,
            'barcode' => $data->barcode,
        ]);
    }
}
