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
    /**
     * @param  array{artist: string, title: string, year?: ?int, format?: ?string, condition?: ?string, acquired_at?: ?string, notes?: ?string}  $data
     */
    public function handle(User $user, array $data): CollectionItem
    {
        return DB::transaction(function () use ($user, $data) {
            $artist = $this->findOrCreateArtist($data['artist']);
            $year = isset($data['year']) ? (int) $data['year'] : null;
            $album = $this->findOrCreateAlbum($artist, $data['title'], $year);

            $format = isset($data['format']) ? ReleaseFormat::from($data['format']) : null;
            $release = $format ? $album->minimalRelease($format) : null;

            return $user->collectionItems()->create([
                'album_id' => $album->id,
                'release_id' => $release?->id,
                'condition' => $data['condition'] ?? null,
                'acquired_at' => $data['acquired_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
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
