<?php

namespace App\Actions;

use App\Enums\ReleaseFormat;
use App\Models\Album;
use App\Models\Artist;
use App\Models\CollectionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Adds a copy to a user's collection, reusing the shared catalog to avoid duplicates
class AddAlbumToCollection
{
    public function __construct(
        private readonly ImportAlbumFromMusicBrainz $importAlbum,
        private readonly ImportReleaseFromMusicBrainz $importRelease,
    ) {}

    /**
     * Either a release MBID (exact edition, found by barcode), an MBID (album imported from MusicBrainz),
     * or a manual artist and title.
     *
     * @param  array{release_mbid?: ?string, mbid?: ?string, artist?: string, title?: string, year?: ?int, format?: ?string, condition?: ?string, acquired_at?: ?string, notes?: ?string}  $data
     *
     * @throws \Illuminate\Http\Client\RequestException|\Illuminate\Http\Client\ConnectionException when the MusicBrainz import fails
     */
    public function handle(User $user, array $data): CollectionItem
    {
        // The imports call the APIs: they run before the copy's transaction
        $importedRelease = isset($data['release_mbid']) ? $this->importRelease->handle($data['release_mbid']) : null;
        $importedAlbum = isset($data['mbid']) ? $this->importAlbum->handle($data['mbid']) : null;

        return DB::transaction(function () use ($user, $data, $importedRelease, $importedAlbum) {
            $album = $importedRelease?->album ?? $importedAlbum ?? $this->findOrCreateManualAlbum($data);

            $format = isset($data['format']) ? ReleaseFormat::from($data['format']) : null;
            $release = $importedRelease ?? ($format ? $album->minimalRelease($format) : null);

            return $user->collectionItems()->create([
                'album_id' => $album->id,
                'release_id' => $release?->id,
                'condition' => $data['condition'] ?? null,
                'acquired_at' => $data['acquired_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * @param  array{artist: string, title: string, year?: ?int}  $data
     */
    private function findOrCreateManualAlbum(array $data): Album
    {
        $artist = $this->findOrCreateArtist($data['artist']);
        $year = isset($data['year']) ? (int) $data['year'] : null;

        return $this->findOrCreateAlbum($artist, $data['title'], $year);
    }

    private function findOrCreateArtist(string $name): Artist
    {
        return Artist::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->orderBy('id')->first()
            ?? Artist::create(['name' => $name]);
    }

    private function findOrCreateAlbum(Artist $artist, string $title, ?int $year): Album
    {
        $album = $artist->albums()
            ->whereRaw('LOWER(albums.title) = ?', [mb_strtolower($title)])
            ->orderBy('albums.id')
            ->first();

        if ($album === null) {
            $album = Album::create(['title' => $title, 'first_release_year' => $year]);
            $album->artists()->attach($artist, ['position' => 0]);

            return $album;
        }

        // Fill a missing year, but never overwrite shared catalog data
        if ($album->first_release_year === null && $year !== null) {
            $album->update(['first_release_year' => $year]);
        }

        return $album;
    }
}
