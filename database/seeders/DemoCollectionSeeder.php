<?php

namespace Database\Seeders;

use App\Enums\ReleaseFormat;
use App\Models\Album;
use App\Models\Artist;
use App\Models\CollectionItem;
use App\Models\Release;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fills the regular user's collection with fake albums, for local development only.
 */
class DemoCollectionSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', config('seeder.user.email'))->first();

        // Idempotent: never add items to a collection that already has some
        if ($user === null || $user->collectionItems()->exists()) {
            return;
        }

        $artists = Artist::factory()->count(10)->create();
        $formats = [ReleaseFormat::Vinyl, ReleaseFormat::Cd, ReleaseFormat::Cassette, null];

        foreach (range(0, 29) as $i) {
            $album = Album::factory()->create([
                // Every third album has no cover, to show the default visual
                'cover_url' => $i % 3 === 0 ? null : "https://picsum.photos/seed/boitazik-{$i}/300",
            ]);

            $album->artists()->attach($artists[$i % 10], ['position' => 0]);
            if ($i % 7 === 0) {
                $album->artists()->attach($artists[($i + 1) % 10], ['position' => 1]);
            }

            $format = $formats[$i % count($formats)];
            $release = $format ? Release::factory()->for($album)->create(['format' => $format]) : null;

            CollectionItem::factory()->for($user)->create([
                'album_id' => $album->id,
                'release_id' => $release?->id,
            ]);
        }
    }
}
