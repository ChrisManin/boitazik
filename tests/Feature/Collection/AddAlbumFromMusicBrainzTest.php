<?php

use App\Enums\AlbumType;
use App\Enums\ItemCondition;
use App\Enums\ReleaseFormat;
use App\Models\Album;
use App\Models\Artist;
use App\Models\CollectionItem;
use App\Models\Release;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const MB_OK_COMPUTER = 'b1392450-e666-3926-a536-22c65f834433';
const MB_RADIOHEAD = 'a74b1b7f-71a5-4011-9441-d0b5e4122711';

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake(syncWithCarbon: true);
});

/**
 * Fake the MusicBrainz lookup and the Cover Art Archive HEAD request.
 *
 * @param  array<string, mixed>  $overrides  fields replaced in the lookup fixture
 */
function fakeImport(array $overrides = [], int $coverStatus = 307): void
{
    Http::fake([
        'musicbrainz.org/ws/2/release-group/*' => Http::response([...fixture('MusicBrainz', 'release-group-lookup'), ...$overrides]),
        'coverartarchive.org/*' => $coverStatus === 307
            ? Http::response('', 307, ['Location' => 'https://archive.org/download/cover_thumb250.jpg'])
            : Http::response(null, $coverStatus),
    ]);
}

test('the search returns MusicBrainz results for the front', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release-group?*' => Http::response(fixture('MusicBrainz', 'release-group-search')),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson('/collection/search?title=OK%20Computer&artist=Radiohead')
        ->assertOk()
        ->assertExactJson(['data' => [
            [
                'mbid' => MB_OK_COMPUTER,
                'title' => 'OK Computer',
                'artists' => ['Radiohead'],
                'year' => 1997,
                'type' => 'Album',
                'cover_url' => 'https://coverartarchive.org/release-group/'.MB_OK_COMPUTER.'/front-250',
            ],
            [
                'mbid' => '50c8a447-cf06-43f8-b2da-450e31a1c434',
                'title' => 'Watch the Throne',
                'artists' => ['Dean Blunt', 'James Ferraro'],
                'year' => 2012,
                'type' => 'EP',
                'cover_url' => 'https://coverartarchive.org/release-group/50c8a447-cf06-43f8-b2da-450e31a1c434/front-250',
            ],
        ]]);

    // Only the search is sent: cover URLs are built without any call
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request['query'] === 'releasegroup:"OK Computer" AND artist:"Radiohead"');
});

test('guests cannot search', function () {
    $this->getJson('/collection/search?title=OK%20Computer')->assertUnauthorized();

    Http::assertNothingSent();
});

test('the search requires a title', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/collection/search?artist=Radiohead')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title' => 'Le titre est obligatoire.']);

    Http::assertNothingSent();
});

test('the search answers 503 when MusicBrainz is unavailable', function () {
    Http::fake(['musicbrainz.org/*' => Http::response(null, 503)]);

    $this->actingAs(User::factory()->create())
        ->getJson('/collection/search?title=OK%20Computer')
        ->assertStatus(503)
        ->assertExactJson(['message' => "MusicBrainz ne répond pas pour le moment. Réessayez ou saisissez l'album à la main."]);
});

test('the search answers 503 when MusicBrainz cannot be reached', function () {
    Http::fake(['musicbrainz.org/*' => fn () => throw new ConnectionException('Connection timed out')]);

    $this->actingAs(User::factory()->create())
        ->getJson('/collection/search?title=OK%20Computer')
        ->assertStatus(503)
        ->assertJsonPath('message', "MusicBrainz ne répond pas pour le moment. Réessayez ou saisissez l'album à la main.");
});

test('MusicBrainz types are mapped to album types', function (?string $primary, array $secondary, AlbumType $expected) {
    expect(AlbumType::fromMusicBrainz($primary, $secondary))->toBe($expected);
})->with([
    'album' => ['Album', [], AlbumType::Album],
    'ep' => ['EP', [], AlbumType::Ep],
    'single' => ['Single', [], AlbumType::Single],
    'live wins' => ['Album', ['Live'], AlbumType::Live],
    'compilation wins' => ['Album', ['Compilation'], AlbumType::Compilation],
    'soundtrack wins' => ['Album', ['Soundtrack'], AlbumType::Soundtrack],
    'other secondary ignored' => ['EP', ['Remix'], AlbumType::Ep],
    'broadcast' => ['Broadcast', [], AlbumType::Other],
    'none' => [null, [], AlbumType::Other],
]);

test('an album chosen on MusicBrainz is imported into my collection', function () {
    fakeImport([
        'secondary-types' => ['Live'],
        'artist-credit' => [
            ['name' => 'Radiohead', 'joinphrase' => ' & ', 'artist' => ['id' => MB_RADIOHEAD, 'name' => 'Radiohead']],
            ['name' => 'Björk', 'artist' => ['id' => '87c5dedd-371d-4a53-9f7f-80522fb7f3cb', 'name' => 'Björk']],
        ],
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/collection', [
            'mbid' => MB_OK_COMPUTER,
            // Ignored when an MBID is given
            'artist' => 'Someone else',
            'title' => 'Something else',
            'year' => 2001,
            'format' => 'vinyl',
            'condition' => 'near_mint',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/collection');

    $album = Album::sole();
    $item = CollectionItem::sole();

    expect($album->mbid)->toBe(MB_OK_COMPUTER)
        ->and($album->title)->toBe('OK Computer')
        ->and($album->type)->toBe(AlbumType::Live)
        ->and($album->first_release_year)->toBe(1997)
        ->and($album->cover_url)->toBe('https://coverartarchive.org/release-group/'.MB_OK_COMPUTER.'/front-250')
        ->and($album->artists->pluck('name')->all())->toBe(['Radiohead', 'Björk'])
        ->and($album->artists->pluck('pivot.position')->all())->toBe([0, 1])
        ->and($album->artists->pluck('mbid')->all())->toBe([MB_RADIOHEAD, '87c5dedd-371d-4a53-9f7f-80522fb7f3cb'])
        ->and($item->user_id)->toBe($user->id)
        ->and($item->album_id)->toBe($album->id)
        ->and($item->release->format)->toBe(ReleaseFormat::Vinyl)
        ->and($item->condition)->toBe(ItemCondition::NearMint);
});

test('importing the same album twice creates no duplicate', function () {
    fakeImport();

    $this->actingAs(User::factory()->create())->post('/collection', ['mbid' => MB_OK_COMPUTER]);
    $this->actingAs(User::factory()->create())->post('/collection', ['mbid' => MB_OK_COMPUTER])
        ->assertSessionHasNoErrors();

    expect(Album::count())->toBe(1)
        ->and(Artist::count())->toBe(1)
        ->and(CollectionItem::count())->toBe(2)
        ->and(CollectionItem::pluck('album_id')->unique()->all())->toBe([Album::sole()->id]);
});

test('a manually entered artist and album receive the MBID', function () {
    fakeImport();
    $artist = Artist::factory()->create(['name' => 'radiohead', 'mbid' => null]);
    $album = Album::factory()->create(['title' => 'ok computer', 'mbid' => null, 'type' => AlbumType::Other, 'cover_url' => null, 'first_release_year' => null]);
    $album->artists()->attach($artist, ['position' => 0]);

    $this->actingAs(User::factory()->create())
        ->post('/collection', ['mbid' => MB_OK_COMPUTER])
        ->assertSessionHasNoErrors();

    $album->refresh();

    expect(Artist::count())->toBe(1)
        ->and(Album::count())->toBe(1)
        ->and($artist->fresh()->mbid)->toBe(MB_RADIOHEAD)
        ->and($album->mbid)->toBe(MB_OK_COMPUTER)
        ->and($album->type)->toBe(AlbumType::Album)
        ->and($album->first_release_year)->toBe(1997)
        ->and($album->cover_url)->toBe('https://coverartarchive.org/release-group/'.MB_OK_COMPUTER.'/front-250')
        ->and(CollectionItem::sole()->album_id)->toBe($album->id);
});

test('an album without cover is imported with a null cover', function () {
    fakeImport(coverStatus: 404);

    $this->actingAs(User::factory()->create())
        ->post('/collection', ['mbid' => MB_OK_COMPUTER])
        ->assertSessionHasNoErrors();

    expect(Album::sole()->cover_url)->toBeNull();
});

test('a Cover Art Archive error does not block the import', function () {
    fakeImport(coverStatus: 503);

    $this->actingAs(User::factory()->create())
        ->post('/collection', ['mbid' => MB_OK_COMPUTER])
        ->assertSessionHasNoErrors();

    expect(Album::sole()->cover_url)->toBeNull();
});

test('nothing is created when MusicBrainz fails during the import', function (int $status) {
    Http::fake(['musicbrainz.org/*' => Http::response(null, $status)]);

    $this->actingAs(User::factory()->create())
        ->from('/collection/create')
        ->post('/collection', ['mbid' => MB_OK_COMPUTER, 'format' => 'cd'])
        ->assertRedirect('/collection/create')
        ->assertSessionHasErrors(['mbid' => "Impossible d'importer cet album depuis MusicBrainz. Réessayez ou saisissez-le à la main."])
        ->assertSessionHasInput('mbid', MB_OK_COMPUTER);

    expect(Album::count())->toBe(0)
        ->and(Artist::count())->toBe(0)
        ->and(Release::count())->toBe(0)
        ->and(CollectionItem::count())->toBe(0);
})->with(['unavailable' => 503, 'unknown MBID' => 404]);

test('an invalid MBID is rejected', function () {
    $this->actingAs(User::factory()->create())
        ->from('/collection/create')
        ->post('/collection', ['mbid' => 'not-a-uuid'])
        ->assertSessionHasErrors(['mbid' => "L'identifiant MusicBrainz n'est pas valide."]);

    Http::assertNothingSent();
});
