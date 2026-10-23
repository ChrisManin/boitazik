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

const MB_AERIAL_RELEASE = '5eceb9ff-d7d2-31b8-b070-d6b7ab588611';
const MB_AERIAL = '6169f5bc-b5ff-3348-b806-1b0f2a414217';
const MB_KATE_BUSH = '4b585938-f271-45e2-b19a-91c634b5e396';

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake(syncWithCarbon: true);
});

/**
 * Fake the MusicBrainz release lookup and the Cover Art Archive HEAD request.
 */
function fakeReleaseImport(): void
{
    Http::fake([
        'musicbrainz.org/ws/2/release/*' => Http::response(fixture('MusicBrainz', 'release-lookup')),
        'coverartarchive.org/*' => Http::response('', 307, ['Location' => 'https://archive.org/download/cover_thumb250.jpg']),
    ]);
}

test('a barcode returns its edition for the front', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release?*' => Http::response(fixture('MusicBrainz', 'release-search-barcode-cd')),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson('/collection/barcode?code=0827969777220')
        ->assertOk()
        ->assertExactJson(['data' => [
            [
                'release_mbid' => MB_AERIAL_RELEASE,
                'album' => [
                    'mbid' => MB_AERIAL,
                    'title' => 'Aerial',
                    'artists' => ['Kate Bush'],
                    'year' => null,
                    'type' => 'Album',
                    'cover_url' => 'https://coverartarchive.org/release-group/'.MB_AERIAL.'/front-250',
                ],
                'format' => 'CD',
                'label' => 'Columbia',
                'catalog_number' => '82796 97772 2',
                'country' => 'US',
                'year' => 2005,
                'barcode' => '0827969777220',
            ],
        ]]);

    Http::assertSentCount(1);
});

test('a vinyl barcode returns a vinyl edition', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release?*' => Http::response(fixture('MusicBrainz', 'release-search-barcode-vinyl')),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson('/collection/barcode?code=602547288271')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.format', 'Vinyle')
        ->assertJsonPath('data.0.album.title', 'Made in Heaven')
        ->assertJsonPath('data.0.album.artists', ['Queen']);
});

test('a barcode shared by several editions returns them all', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release?*' => Http::response(fixture('MusicBrainz', 'release-search-barcode-multiple')),
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->getJson('/collection/barcode?code=724385522925')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    expect($response->json('data.*.country'))->toBe(['US', 'FR', 'GB'])
        ->and($response->json('data.*.label'))->toBe(['Capitol Records', 'Parlophone', 'Parlophone']);
});

test('an unknown barcode returns no edition', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release?*' => Http::response(fixture('MusicBrainz', 'release-search-barcode-empty')),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson('/collection/barcode?code=880882178313')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('the barcode accepts spaces and dashes', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release?*' => Http::response(fixture('MusicBrainz', 'release-search-barcode-empty')),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson('/collection/barcode?code='.urlencode('5 099749-534728'))
        ->assertOk();

    Http::assertSent(fn (Request $request) => $request['query'] === 'barcode:5099749534728');
});

test('an invalid barcode is rejected', function (?string $code) {
    $this->actingAs(User::factory()->create())
        ->getJson('/collection/barcode?code='.urlencode((string) $code))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    Http::assertNothingSent();
})->with(['missing' => null, 'too short' => '1234567', 'too long' => '123456789012345', 'letters' => 'abcdefghijkl']);

test('guests cannot search by barcode', function () {
    $this->getJson('/collection/barcode?code=724385522925')->assertUnauthorized();

    Http::assertNothingSent();
});

test('the barcode search answers 503 when MusicBrainz is unavailable', function (mixed $fake) {
    Http::fake(['musicbrainz.org/*' => $fake]);

    $this->actingAs(User::factory()->create())
        ->getJson('/collection/barcode?code=724385522925')
        ->assertStatus(503)
        ->assertExactJson(['message' => "MusicBrainz ne répond pas pour le moment. Réessayez ou saisissez l'album à la main."]);
})->with([
    'unavailable' => fn () => Http::response(null, 503),
    'unreachable' => fn () => fn () => throw new ConnectionException('Connection timed out'),
]);

test('an edition chosen by barcode is imported with its details into my collection', function () {
    fakeReleaseImport();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/collection', [
            'release_mbid' => MB_AERIAL_RELEASE,
            // Ignored: the edition gives the album and the format
            'mbid' => '',
            'artist' => 'Someone else',
            'title' => 'Something else',
            'format' => 'vinyl',
            'condition' => 'near_mint',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/collection');

    $album = Album::sole();
    $release = Release::sole();
    $item = CollectionItem::sole();

    expect($album->mbid)->toBe(MB_AERIAL)
        ->and($album->title)->toBe('Aerial')
        ->and($album->type)->toBe(AlbumType::Album)
        ->and($album->first_release_year)->toBe(2005)
        ->and($album->cover_url)->toBe('https://coverartarchive.org/release-group/'.MB_AERIAL.'/front-250')
        ->and($album->artists->pluck('mbid')->all())->toBe([MB_KATE_BUSH])
        ->and($release->mbid)->toBe(MB_AERIAL_RELEASE)
        ->and($release->album_id)->toBe($album->id)
        ->and($release->format)->toBe(ReleaseFormat::Cd)
        ->and($release->label)->toBe('Columbia')
        ->and($release->catalog_number)->toBe('82796 97772 2')
        ->and($release->country)->toBe('US')
        ->and($release->release_year)->toBe(2005)
        ->and($release->barcode)->toBe('0827969777220')
        ->and($item->user_id)->toBe($user->id)
        ->and($item->album_id)->toBe($album->id)
        ->and($item->release_id)->toBe($release->id)
        ->and($item->condition)->toBe(ItemCondition::NearMint);

    // The release lookup carries the release group: no second MusicBrainz call
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/ws/2/release/'.MB_AERIAL_RELEASE));
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/ws/2/release-group/'));
});

test('importing the same edition twice creates no duplicate', function () {
    fakeReleaseImport();

    $this->actingAs(User::factory()->create())->post('/collection', ['release_mbid' => MB_AERIAL_RELEASE]);
    $this->actingAs(User::factory()->create())->post('/collection', ['release_mbid' => MB_AERIAL_RELEASE])
        ->assertSessionHasNoErrors();

    expect(Album::count())->toBe(1)
        ->and(Artist::count())->toBe(1)
        ->and(Release::count())->toBe(1)
        ->and(CollectionItem::count())->toBe(2)
        ->and(CollectionItem::pluck('release_id')->unique()->all())->toBe([Release::sole()->id]);
});

test('an edition of an album already imported by title reuses the album', function () {
    $album = Album::factory()->create(['mbid' => MB_AERIAL, 'title' => 'Aerial']);
    fakeReleaseImport();

    $this->actingAs(User::factory()->create())
        ->post('/collection', ['release_mbid' => MB_AERIAL_RELEASE])
        ->assertSessionHasNoErrors();

    expect(Album::count())->toBe(1)
        ->and(Release::sole()->album_id)->toBe($album->id);
});

test('nothing is created when MusicBrainz fails during the edition import', function (int $status) {
    Http::fake(['musicbrainz.org/*' => Http::response(null, $status)]);

    $this->actingAs(User::factory()->create())
        ->from('/collection/create')
        ->post('/collection', ['release_mbid' => MB_AERIAL_RELEASE])
        ->assertRedirect('/collection/create')
        ->assertSessionHasErrors(['release_mbid' => "Impossible d'importer cette édition depuis MusicBrainz. Réessayez ou cherchez l'album par titre."]);

    expect(Album::count())->toBe(0)
        ->and(Release::count())->toBe(0)
        ->and(CollectionItem::count())->toBe(0);
})->with(['unavailable' => 503, 'unknown MBID' => 404]);

test('an invalid edition MBID is rejected', function () {
    $this->actingAs(User::factory()->create())
        ->post('/collection', ['release_mbid' => 'not-a-uuid'])
        ->assertSessionHasErrors(['release_mbid' => "L'identifiant de l'édition n'est pas valide."]);

    Http::assertNothingSent();
});
