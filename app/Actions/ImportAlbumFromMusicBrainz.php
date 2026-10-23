<?php

namespace App\Actions;

use App\Enums\AlbumType;
use App\Models\Album;
use App\Models\Artist;
use App\Services\CoverArtArchive\CoverArtArchiveClient;
use App\Services\MusicBrainz\Data\ArtistCredit;
use App\Services\MusicBrainz\Data\ReleaseGroup;
use App\Services\MusicBrainz\MusicBrainzClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

// Brings a MusicBrainz release group into the shared catalog, merging manual entries of the same name
class ImportAlbumFromMusicBrainz
{
    public function __construct(
        private readonly MusicBrainzClient $musicBrainz,
        private readonly CoverArtArchiveClient $coverArt,
    ) {}

    /**
     * @throws RequestException|ConnectionException when MusicBrainz fails
     */
    public function handle(string $mbid): Album
    {
        return $this->fromReleaseGroup($this->musicBrainz->lookupReleaseGroup($mbid));
    }

    // For a release group already fetched, with its credits (from a release lookup, for instance)
    public function fromReleaseGroup(ReleaseGroup $releaseGroup): Album
    {
        // HTTP calls stay outside the transaction: no lock is held while waiting for the APIs
        $coverUrl = $this->coverUrl($releaseGroup->mbid);

        return DB::transaction(function () use ($releaseGroup, $coverUrl) {
            $artists = array_map(fn (ArtistCredit $credit) => $this->findOrCreateArtist($credit), $releaseGroup->artists);

            return $this->findOrCreateAlbum($releaseGroup, $artists, $coverUrl);
        });
    }

    // A missing cover never blocks the import
    private function coverUrl(string $mbid): ?string
    {
        try {
            return $this->coverArt->frontCoverUrl($mbid);
        } catch (RequestException|ConnectionException) {
            return null;
        }
    }

    private function findOrCreateArtist(ArtistCredit $credit): Artist
    {
        $artist = Artist::where('mbid', $credit->mbid)->first();

        if ($artist !== null) {
            return $artist;
        }

        $artist = Artist::whereNull('mbid')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($credit->name)])
            ->orderBy('id')
            ->first();

        if ($artist !== null) {
            $artist->update(['mbid' => $credit->mbid]);

            return $artist;
        }

        return Artist::create(['name' => $credit->name, 'mbid' => $credit->mbid]);
    }

    /**
     * @param  array<int, Artist>  $artists  in credit order
     */
    private function findOrCreateAlbum(ReleaseGroup $releaseGroup, array $artists, ?string $coverUrl): Album
    {
        $album = Album::where('mbid', $releaseGroup->mbid)->first();

        if ($album !== null) {
            return $album;
        }

        $attributes = [
            'mbid' => $releaseGroup->mbid,
            'type' => AlbumType::fromMusicBrainz($releaseGroup->primaryType, $releaseGroup->secondaryTypes),
            'cover_url' => $coverUrl,
        ];
        $positions = collect($artists)
            ->mapWithKeys(fn (Artist $artist, int $position) => [$artist->id => ['position' => $position]])
            ->all();

        $album = $this->findManualAlbum($releaseGroup->title, $artists[0] ?? null);

        if ($album !== null) {
            $album->update([
                ...$attributes,
                'first_release_year' => $album->first_release_year ?? $releaseGroup->firstReleaseYear,
            ]);
            $album->artists()->sync($positions);

            return $album;
        }

        $album = Album::create([
            ...$attributes,
            'title' => $releaseGroup->title,
            'first_release_year' => $releaseGroup->firstReleaseYear,
        ]);
        $album->artists()->attach($positions);

        return $album;
    }

    // An album entered by hand: no MBID, same title, credited to the main artist
    private function findManualAlbum(string $title, ?Artist $mainArtist): ?Album
    {
        return $mainArtist?->albums()
            ->whereNull('albums.mbid')
            ->whereRaw('LOWER(albums.title) = ?', [mb_strtolower($title)])
            ->orderBy('albums.id')
            ->first();
    }
}
