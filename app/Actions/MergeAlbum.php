<?php

namespace App\Actions;

use App\Models\Album;
use App\Models\CollectionItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// Folds a duplicate album into the right one: no user loses a copy
class MergeAlbum
{
    // Only empty fields of the target are filled: its own values win
    private const FILLABLE_FROM_SOURCE = ['mbid', 'first_release_year', 'cover_url', 'spotify_id'];

    public function handle(Album $source, Album $target): Album
    {
        if ($source->is($target)) {
            throw new InvalidArgumentException('An album cannot be merged into itself.');
        }

        return DB::transaction(function () use ($source, $target) {
            // The release of each copy is kept: it moves along with the album
            CollectionItem::where('album_id', $source->id)->update(['album_id' => $target->id]);
            $source->releases()->update(['album_id' => $target->id]);
            $this->creditMissingArtists($source, $target);

            $values = $source->only(self::FILLABLE_FROM_SOURCE);
            // Deleted first: the MBID is unique and moves to the target
            $source->delete();

            foreach ($values as $field => $value) {
                if ($target->{$field} === null && $value !== null) {
                    $target->{$field} = $value;
                }
            }
            $target->save();

            return $target->refresh();
        });
    }

    // Artists missing from the target come after its own, in the source order
    private function creditMissingArtists(Album $source, Album $target): void
    {
        $credited = $target->artists()->pluck('artists.id');
        $position = $target->artists()->max('album_artist.position') ?? -1;

        foreach ($source->artists()->get() as $artist) {
            if (! $credited->contains($artist->id)) {
                $target->artists()->attach($artist->id, ['position' => ++$position]);
            }
        }
    }
}
