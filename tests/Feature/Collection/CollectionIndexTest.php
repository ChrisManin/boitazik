<?php

use App\Enums\ReleaseFormat;
use App\Models\Album;
use App\Models\Artist;
use App\Models\CollectionItem;
use App\Models\Release;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create an item for the user, on an album by the given artist.
 */
function ownedAlbum(User $user, string $artist, string $title, ?ReleaseFormat $format = null): CollectionItem
{
    $album = Album::factory()
        ->hasAttached(Artist::factory()->state(['name' => $artist, 'sort_name' => $artist]), ['position' => 0], 'artists')
        ->create(['title' => $title]);

    if ($format === null) {
        return CollectionItem::factory()->for($user)->create(['album_id' => $album->id, 'release_id' => null]);
    }

    $release = Release::factory()->for($album)->create(['format' => $format]);

    return CollectionItem::factory()->for($user)->for($release)->create(['album_id' => $album->id]);
}

test('guests are redirected to the login page', function () {
    $this->get('/collection')->assertRedirect('/login');
});

test('the collection page only shows my items', function () {
    $user = User::factory()->create();
    $mine = ownedAlbum($user, 'Björk', 'Homogenic', ReleaseFormat::Vinyl);
    ownedAlbum(User::factory()->create(), 'Portishead', 'Dummy');

    $this->actingAs($user)
        ->get('/collection')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Collection/Index')
            ->has('items.data', 1)
            ->has('items.data.0', fn (Assert $item) => $item
                ->where('id', $mine->id)
                ->where('title', 'Homogenic')
                ->where('artists', ['Björk'])
                ->where('year', $mine->album->first_release_year)
                ->where('format', 'Vinyle')
                ->where('cover_url', null)
            )
        );
});

test('the format is null when the release is unknown', function () {
    $user = User::factory()->create();
    ownedAlbum($user, 'Air', 'Moon Safari');

    $this->actingAs($user)
        ->get('/collection')
        ->assertInertia(fn (Assert $page) => $page->where('items.data.0.format', null));
});

test('items are sorted by artist then title', function () {
    $user = User::factory()->create();
    ownedAlbum($user, 'Massive Attack', 'Mezzanine');
    ownedAlbum($user, 'Air', 'Talkie Walkie');
    ownedAlbum($user, 'Massive Attack', 'Blue Lines');
    ownedAlbum($user, 'Air', 'Moon Safari');

    $this->actingAs($user)
        ->get('/collection')
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.title', 'Moon Safari')
            ->where('items.data.1.title', 'Talkie Walkie')
            ->where('items.data.2.title', 'Blue Lines')
            ->where('items.data.3.title', 'Mezzanine')
        );
});

test('the artist sort name falls back to the name', function () {
    $user = User::factory()->create();
    ownedAlbum($user, 'Zebda', 'Essence ordinaire');
    $item = ownedAlbum($user, 'Beatles', 'Abbey Road');
    $item->album->artists()->update(['sort_name' => null]);

    $this->actingAs($user)
        ->get('/collection')
        ->assertInertia(fn (Assert $page) => $page->where('items.data.0.title', 'Abbey Road'));
});

test('an empty collection has no items', function () {
    $this->actingAs(User::factory()->create())
        ->get('/collection')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Collection/Index')
            ->has('items.data', 0)
        );
});

test('the collection is paginated', function () {
    $user = User::factory()->create();
    CollectionItem::factory()->count(25)->for($user)->create();

    $this->actingAs($user)
        ->get('/collection')
        ->assertInertia(fn (Assert $page) => $page
            ->has('items.data', 24)
            ->where('items.meta.last_page', 2)
        );

    $this->actingAs($user)
        ->get('/collection?page=2')
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1));
});

test('the collection page does not run one query per item', function () {
    $user = User::factory()->create();
    $countQueries = function () use ($user) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get('/collection')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    ownedAlbum($user, 'Air', 'Moon Safari', ReleaseFormat::Cd);
    $withOneItem = $countQueries();

    foreach (range(1, 4) as $i) {
        ownedAlbum($user, "Artist $i", "Album $i", ReleaseFormat::Vinyl);
    }

    expect($countQueries())->toBe($withOneItem);
});

test('a user can only view their own items', function () {
    $item = CollectionItem::factory()->create();

    expect($item->user->can('view', $item))->toBeTrue()
        ->and(User::factory()->create()->can('view', $item))->toBeFalse();
});
