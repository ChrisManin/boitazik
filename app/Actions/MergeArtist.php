<?php

namespace App\Actions;

use App\Models\Artist;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// Folds a duplicate artist into the right one: its albums are credited to the target
class MergeArtist
{
    // Only empty fields of the target are filled: its own values win
    private const FILLABLE_FROM_SOURCE = ['sort_name', 'mbid', 'spotify_id', 'country', 'image_url'];

    public function handle(Artist $source, Artist $target): Artist
    {
        if ($source->is($target)) {
            throw new InvalidArgumentException('An artist cannot be merged into itself.');
        }

        return DB::transaction(function () use ($source, $target) {
            $credited = $target->albums()->pluck('albums.id');

            foreach ($source->albums()->get() as $album) {
                // Already credited on the album: the target keeps its own position
                if (! $credited->contains($album->id)) {
                    $target->albums()->attach($album->id, ['position' => $album->pivot->position]);
                }
            }

            $values = $source->only(self::FILLABLE_FROM_SOURCE);
            // Deleted first: the MBID is unique and moves to the target
            $source->delete();

            foreach ($values as $field => $value) {
                if ($target->{$field} === null && $value !== null) {
                    $target->{$field} = $value;
                }
            }
            // A new MBID queues the target's discography import, after commit (Artist::booted)
            $target->save();

            return $target->refresh();
        });
    }
}
