<?php

use App\Enums\ItemCondition;
use App\Enums\ReleaseFormat;
use App\Models\Album;
use App\Models\Artist;
use App\Models\CollectionItem;
use App\Models\Release;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a catalog album credited to the given artist.
 */
function catalogAlbum(Artist $artist, string $title, ?int $year = null): Album
{
    $album = Album::factory()->create(['title' => $title, 'first_release_year' => $year]);
    $album->artists()->attach($artist, ['position' => 0]);

    return $album;
}

test('guests cannot add an album', function () {
    $this->get('/collection/create')->assertRedirect('/login');
    $this->post('/collection', ['artist' => 'Air', 'title' => 'Moon Safari'])->assertRedirect('/login');

    expect(CollectionItem::count())->toBe(0);
});

test('the create page lists formats and conditions', function () {
    $this->actingAs(User::factory()->create())
        ->get('/collection/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Collection/Create')
            ->has('formats', count(ReleaseFormat::cases()))
            ->where('formats.1', ['value' => 'vinyl', 'label' => 'Vinyle'])
            ->has('conditions', count(ItemCondition::cases()))
            ->where('conditions.0.value', 'mint')
        );
});

test('a complete album is added to my collection', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/collection', [
            'artist' => 'Daft Punk',
            'title' => 'Discovery',
            'year' => 2001,
            'format' => 'vinyl',
            'condition' => 'near_mint',
            'acquired_at' => '2024-05-12',
            'notes' => 'Pressage original',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/collection')
        ->assertSessionHas('success', 'Album ajouté à votre collection.');

    $artist = Artist::sole();
    $album = Album::sole();
    $release = Release::sole();
    $item = CollectionItem::sole();

    expect($artist->name)->toBe('Daft Punk')
        ->and($album->title)->toBe('Discovery')
        ->and($album->first_release_year)->toBe(2001)
        ->and($album->artists->modelKeys())->toBe([$artist->id])
        ->and($release->album_id)->toBe($album->id)
        ->and($release->format)->toBe(ReleaseFormat::Vinyl)
        ->and($item->user_id)->toBe($user->id)
        ->and($item->album_id)->toBe($album->id)
        ->and($item->release_id)->toBe($release->id)
        ->and($item->condition)->toBe(ItemCondition::NearMint)
        ->and($item->acquired_at->toDateString())->toBe('2024-05-12')
        ->and($item->notes)->toBe('Pressage original');
});

test('an empty mbid keeps the manual entry', function () {
    // The create form always sends mbid, empty in manual mode
    $this->actingAs(User::factory()->create())
        ->post('/collection', ['mbid' => '', 'artist' => 'Air', 'title' => 'Moon Safari', 'year' => 1998])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/collection');

    expect(Album::sole()->title)->toBe('Moon Safari')
        ->and(Album::sole()->first_release_year)->toBe(1998)
        ->and(Artist::sole()->name)->toBe('Air');
});

test('the success message is shared with the front', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['success' => 'Album ajouté à votre collection.'])
        ->get('/collection')
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.success', 'Album ajouté à votre collection.')
        );
});

test('an album without format has no release', function () {
    $this->actingAs(User::factory()->create())
        ->post('/collection', ['artist' => 'Air', 'title' => 'Moon Safari'])
        ->assertRedirect('/collection');

    expect(Release::count())->toBe(0)
        ->and(CollectionItem::sole()->release_id)->toBeNull();
});

test('an existing artist and album are reused, whatever the case', function () {
    $artist = Artist::factory()->create(['name' => 'Radiohead']);
    $album = catalogAlbum($artist, 'OK Computer', 1997);

    $this->actingAs(User::factory()->create())
        ->post('/collection', ['artist' => 'radiohead', 'title' => 'ok computer', 'year' => 2017])
        ->assertRedirect('/collection');

    expect(Artist::count())->toBe(1)
        ->and(Album::count())->toBe(1)
        ->and(CollectionItem::sole()->album_id)->toBe($album->id)
        // A user never overwrites the shared year
        ->and($album->fresh()->first_release_year)->toBe(1997);
});

test('a missing year is filled on a reused album', function () {
    $album = catalogAlbum(Artist::factory()->create(['name' => 'Air']), 'Moon Safari');

    $this->actingAs(User::factory()->create())
        ->post('/collection', ['artist' => 'Air', 'title' => 'Moon Safari', 'year' => 1998]);

    expect($album->fresh()->first_release_year)->toBe(1998);
});

test('the same title by another artist is a new album', function () {
    $existing = catalogAlbum(Artist::factory()->create(['name' => 'Weezer']), 'Weezer');

    $this->actingAs(User::factory()->create())
        ->post('/collection', ['artist' => 'Other Band', 'title' => 'Weezer']);

    expect(Album::count())->toBe(2)
        ->and(CollectionItem::sole()->album_id)->not->toBe($existing->id);
});

test('a minimal release of the same format is reused', function () {
    $album = catalogAlbum(Artist::factory()->create(['name' => 'Air']), 'Moon Safari');
    $minimal = Release::create(['album_id' => $album->id, 'format' => ReleaseFormat::Cd]);

    $this->actingAs(User::factory()->create())
        ->post('/collection', ['artist' => 'Air', 'title' => 'Moon Safari', 'format' => 'cd']);

    expect(Release::count())->toBe(1)
        ->and(CollectionItem::sole()->release_id)->toBe($minimal->id);
});

test('a detailed release is never reused by a manual entry', function () {
    $album = catalogAlbum(Artist::factory()->create(['name' => 'Air']), 'Moon Safari');
    $detailed = Release::factory()->for($album)->create(['format' => ReleaseFormat::Cd]);

    $this->actingAs(User::factory()->create())
        ->post('/collection', ['artist' => 'Air', 'title' => 'Moon Safari', 'format' => 'cd']);

    expect(Release::count())->toBe(2)
        ->and(CollectionItem::sole()->release_id)->not->toBe($detailed->id);
});

test('invalid input is rejected with French messages', function () {
    $this->actingAs(User::factory()->create())
        ->from('/collection/create')
        ->post('/collection', [
            'artist' => '',
            'title' => '',
            'year' => 1800,
            'format' => 'laserdisc',
            'condition' => 'broken',
            'acquired_at' => now()->addDay()->toDateString(),
        ])
        ->assertRedirect('/collection/create')
        ->assertSessionHasErrors([
            'artist' => "L'artiste est obligatoire.",
            'title' => 'Le titre est obligatoire.',
            'year' => "L'année doit être comprise entre 1900 et ".(now()->year + 1).'.',
            'format' => "Le format choisi n'est pas valide.",
            'condition' => "L'état choisi n'est pas valide.",
            'acquired_at' => "La date d'acquisition ne peut pas être dans le futur.",
        ]);

    expect(CollectionItem::count())->toBe(0);
});
