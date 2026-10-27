<?php

use App\Enums\AlbumType;
use App\Models\Album;
use App\Models\Artist;
use App\Models\CollectionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create an album credited to the artist.
 */
function discographyAlbum(Artist $artist, string $title, AlbumType $type = AlbumType::Album, ?int $year = 2000): Album
{
    $album = Album::factory()->create(['title' => $title, 'type' => $type, 'first_release_year' => $year]);
    $artist->albums()->attach($album, ['position' => 0]);

    return $album;
}

test('guests are redirected to the login page', function () {
    $this->get('/artists/'.Artist::factory()->create()->id)->assertRedirect('/login');
});

test('the artist page shows the artist and their discography grouped by type', function () {
    $artist = Artist::factory()->create(['name' => 'Björk', 'country' => 'IS', 'image_url' => 'https://img/bjork.jpg', 'enriched_at' => now()]);
    discographyAlbum($artist, 'Vespertine', year: 2001);
    discographyAlbum($artist, 'Debut', year: 1993);
    discographyAlbum($artist, 'Unknown year', year: null);
    discographyAlbum($artist, 'Telegram', AlbumType::Compilation, 1996);
    discographyAlbum($artist, 'Family Tree', AlbumType::Ep, 2002);

    $this->actingAs(User::factory()->create())
        ->get("/artists/{$artist->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Artists/Show')
            ->where('artist', [
                'id' => $artist->id,
                'name' => 'Björk',
                'country' => 'IS',
                'image_url' => 'https://img/bjork.jpg',
            ])
            ->where('importing', false)
            ->has('discography', 3)
            ->where('discography.0.type', 'album')
            ->where('discography.0.label', 'Album')
            ->where('discography.0.albums.0.title', 'Debut')
            ->where('discography.0.albums.1.title', 'Vespertine')
            ->where('discography.0.albums.2.title', 'Unknown year')
            ->where('discography.1.label', 'EP')
            ->where('discography.2.label', 'Compilation')
            ->has('discography.2.albums.0', fn (Assert $album) => $album
                ->where('title', 'Telegram')
                ->where('year', 1996)
                ->has('id')
                ->has('cover_url')
                ->where('owned', false)
            )
        );
});

test('only albums owned by the current user are marked as owned', function () {
    $user = User::factory()->create();
    $artist = Artist::factory()->create();
    $mine = discographyAlbum($artist, 'Mine', year: 1990);
    $theirs = discographyAlbum($artist, 'Theirs', year: 1991);
    CollectionItem::factory()->for($user)->create(['album_id' => $mine->id, 'release_id' => null]);
    CollectionItem::factory()->create(['album_id' => $theirs->id, 'release_id' => null]);

    $this->actingAs($user)
        ->get("/artists/{$artist->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('discography.0.albums.0.owned', true)
            ->where('discography.0.albums.1.owned', false)
        );
});

test('the page tells whether the discography is being imported or has failed', function (?string $mbid, ?string $enrichedAt, ?string $failedAt, bool $importing, bool $importFailed) {
    $artist = Artist::factory()->create(['mbid' => $mbid, 'enriched_at' => $enrichedAt, 'discography_failed_at' => $failedAt]);

    $this->actingAs(User::factory()->create())
        ->get("/artists/{$artist->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('importing', $importing)
            ->where('importFailed', $importFailed)
        );
})->with([
    'not enriched yet' => ['a74b1b7f-71a5-4011-9441-d0b5e4122711', null, null, true, false],
    'enriched' => ['a74b1b7f-71a5-4011-9441-d0b5e4122711', '2026-10-01 00:00:00', null, false, false],
    'no MBID, never imported' => [null, null, null, false, false],
    'import failed for good' => ['a74b1b7f-71a5-4011-9441-d0b5e4122711', null, '2026-10-27 00:00:00', false, true],
    'refresh failed, old discography still shown' => ['a74b1b7f-71a5-4011-9441-d0b5e4122711', '2026-10-01 00:00:00', '2026-10-27 00:00:00', false, false],
]);

test('the artist page does not run one query per album', function () {
    $user = User::factory()->create();
    $artist = Artist::factory()->create();
    $countQueries = function () use ($user, $artist) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get("/artists/{$artist->id}")->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $first = discographyAlbum($artist, 'First');
    CollectionItem::factory()->for($user)->create(['album_id' => $first->id, 'release_id' => null]);
    $withOneAlbum = $countQueries();

    foreach (range(1, 4) as $i) {
        discographyAlbum($artist, "Album $i", AlbumType::cases()[$i]);
    }

    expect($countQueries())->toBe($withOneAlbum);
});
