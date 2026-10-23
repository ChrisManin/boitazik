<?php

use App\Services\MusicBrainz\Data\ArtistCredit;
use App\Services\MusicBrainz\Data\Release;
use App\Services\MusicBrainz\Data\ReleaseGroup;
use App\Services\MusicBrainz\MusicBrainzClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake(syncWithCarbon: true);
});

test('it searches release groups and returns DTOs', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release-group*' => Http::response(fixture('MusicBrainz', 'release-group-search')),
    ]);

    $results = app(MusicBrainzClient::class)->searchReleaseGroups('OK Computer');

    expect($results)->toHaveCount(2)->each->toBeInstanceOf(ReleaseGroup::class)
        ->and($results[0]->mbid)->toBe('b1392450-e666-3926-a536-22c65f834433')
        ->and($results[0]->title)->toBe('OK Computer')
        ->and($results[0]->primaryType)->toBe('Album')
        ->and($results[0]->firstReleaseYear)->toBe(1997)
        ->and($results[0]->artists)->toEqual([
            new ArtistCredit('a74b1b7f-71a5-4011-9441-d0b5e4122711', 'Radiohead'),
        ])
        ->and($results[1]->primaryType)->toBe('EP')
        ->and($results[1]->artists)->toEqual([
            new ArtistCredit('e8bd5b47-e8b4-4671-a9f6-590a92e88898', 'Dean Blunt'),
            new ArtistCredit('2ad18a65-2298-4902-aae9-fe81c111bb09', 'James Ferraro'),
        ]);
});

test('it sends a Lucene query on title and artist', function () {
    Http::fake(['musicbrainz.org/*' => Http::response(fixture('MusicBrainz', 'release-group-search'))]);

    app(MusicBrainzClient::class)->searchReleaseGroups('OK Computer', 'Radiohead');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://musicbrainz.org/ws/2/release-group?')
        && $request['query'] === 'releasegroup:"OK Computer" AND artist:"Radiohead"'
        && $request['fmt'] === 'json');
});

test('it escapes quotes and backslashes in the query', function () {
    Http::fake(['musicbrainz.org/*' => Http::response(fixture('MusicBrainz', 'release-group-search-empty'))]);

    app(MusicBrainzClient::class)->searchReleaseGroups('Say "Hi" \\o/');

    Http::assertSent(fn (Request $request) => $request['query'] === 'releasegroup:"Say \\"Hi\\" \\\\o/"');
});

test('it sends the Boitazik User-Agent', function () {
    Http::fake(['musicbrainz.org/*' => Http::response(fixture('MusicBrainz', 'release-group-search'))]);
    config(['services.musicbrainz.user_agent' => 'Boitazik/9.9 ( test@example.com )']);

    app(MusicBrainzClient::class)->searchReleaseGroups('OK Computer');

    Http::assertSent(fn (Request $request) => $request->header('User-Agent') === ['Boitazik/9.9 ( test@example.com )']);
});

test('it returns an empty list when nothing matches', function () {
    Http::fake(['musicbrainz.org/*' => Http::response(fixture('MusicBrainz', 'release-group-search-empty'))]);

    expect(app(MusicBrainzClient::class)->searchReleaseGroups('zzqqxxnotanalbum'))->toBe([]);
});

test('it throws when the API is unavailable', function () {
    Http::fake(['musicbrainz.org/*' => Http::response(null, 503)]);

    app(MusicBrainzClient::class)->searchReleaseGroups('OK Computer');
})->throws(RequestException::class);

test('it waits between two calls to respect the rate limit', function () {
    Http::fake(['musicbrainz.org/*' => Http::response(fixture('MusicBrainz', 'release-group-search'))]);
    $client = app(MusicBrainzClient::class);

    $client->searchReleaseGroups('OK Computer');
    Sleep::assertNeverSlept();

    $client->searchReleaseGroups('Kid A');
    Sleep::assertSlept(fn () => true, times: 10);
});

test('it maps a missing release date to a null year', function () {
    $releaseGroup = ReleaseGroup::fromArray([
        'id' => 'b1392450-e666-3926-a536-22c65f834433',
        'title' => 'OK Computer',
        'first-release-date' => '',
    ]);

    expect($releaseGroup->firstReleaseYear)->toBeNull()
        ->and($releaseGroup->primaryType)->toBeNull()
        ->and($releaseGroup->artists)->toBe([]);
});

test('it maps secondary types, empty by default', function () {
    $live = ReleaseGroup::fromArray([
        'id' => 'b1392450-e666-3926-a536-22c65f834433',
        'title' => 'I Might Be Wrong',
        'primary-type' => 'Album',
        'secondary-types' => ['Live'],
    ]);
    $plain = ReleaseGroup::fromArray(['id' => 'b1392450-e666-3926-a536-22c65f834433', 'title' => 'OK Computer']);

    expect($live->secondaryTypes)->toBe(['Live'])
        ->and($plain->secondaryTypes)->toBe([]);
});

test('it looks up a release group by MBID', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release-group/*' => Http::response(fixture('MusicBrainz', 'release-group-lookup')),
    ]);

    $releaseGroup = app(MusicBrainzClient::class)->lookupReleaseGroup('b1392450-e666-3926-a536-22c65f834433');

    expect($releaseGroup)->toBeInstanceOf(ReleaseGroup::class)
        ->and($releaseGroup->mbid)->toBe('b1392450-e666-3926-a536-22c65f834433')
        ->and($releaseGroup->title)->toBe('OK Computer')
        ->and($releaseGroup->primaryType)->toBe('Album')
        ->and($releaseGroup->secondaryTypes)->toBe([])
        ->and($releaseGroup->firstReleaseYear)->toBe(1997)
        ->and($releaseGroup->artists)->toEqual([
            new ArtistCredit('a74b1b7f-71a5-4011-9441-d0b5e4122711', 'Radiohead'),
        ]);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://musicbrainz.org/ws/2/release-group/b1392450-e666-3926-a536-22c65f834433?')
        && $request['inc'] === 'artist-credits'
        && $request['fmt'] === 'json'
        && str_starts_with($request->header('User-Agent')[0], 'Boitazik/'));
});

test('it throws when the looked up release group does not exist', function () {
    Http::fake(['musicbrainz.org/*' => Http::response(['error' => 'Not Found'], 404)]);

    app(MusicBrainzClient::class)->lookupReleaseGroup('00000000-0000-0000-0000-000000000000');
})->throws(RequestException::class);

test('it finds a CD release by barcode', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release*' => Http::response(fixture('MusicBrainz', 'release-search-barcode-cd')),
    ]);

    $releases = app(MusicBrainzClient::class)->findReleasesByBarcode('0827969777220');

    expect($releases)->toHaveCount(1)->each->toBeInstanceOf(Release::class);

    $release = $releases[0];
    expect($release->mbid)->toBe('5eceb9ff-d7d2-31b8-b070-d6b7ab588611')
        ->and($release->title)->toBe('Aerial')
        ->and($release->format)->toBe('CD')
        ->and($release->label)->toBe('Columbia')
        ->and($release->catalogNumber)->toBe('82796 97772 2')
        ->and($release->country)->toBe('US')
        ->and($release->releaseYear)->toBe(2005)
        ->and($release->barcode)->toBe('0827969777220')
        ->and($release->releaseGroup->mbid)->toBe('6169f5bc-b5ff-3348-b806-1b0f2a414217')
        ->and($release->releaseGroup->title)->toBe('Aerial')
        ->and($release->releaseGroup->primaryType)->toBe('Album')
        ->and($release->releaseGroup->artists)->toEqual([
            new ArtistCredit('4b585938-f271-45e2-b19a-91c634b5e396', 'Kate Bush'),
        ]);
});

test('it finds a vinyl release by barcode', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release*' => Http::response(fixture('MusicBrainz', 'release-search-barcode-vinyl')),
    ]);

    $releases = app(MusicBrainzClient::class)->findReleasesByBarcode('602547288271');

    expect($releases)->toHaveCount(1)
        ->and($releases[0]->title)->toBe('Made in Heaven')
        ->and($releases[0]->format)->toBe('12" Vinyl')
        ->and($releases[0]->label)->toBe('Virgin EMI Records')
        ->and($releases[0]->country)->toBe('XE')
        ->and($releases[0]->releaseYear)->toBe(2015)
        ->and($releases[0]->releaseGroup->artists)->toEqual([
            new ArtistCredit('0383dadf-2a4e-4d10-a46a-e9e041da8eb3', 'Queen'),
        ]);
});

test('it returns every edition sharing a barcode', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release*' => Http::response(fixture('MusicBrainz', 'release-search-barcode-multiple')),
    ]);

    $releases = app(MusicBrainzClient::class)->findReleasesByBarcode('724385522925');

    expect($releases)->toHaveCount(3)
        ->and(array_map(fn (Release $release) => $release->country, $releases))->toBe(['US', 'FR', 'GB'])
        ->and(array_map(fn (Release $release) => $release->label, $releases))->toBe(['Capitol Records', 'Parlophone', 'Parlophone'])
        ->and(array_map(fn (Release $release) => $release->releaseGroup->mbid, $releases))
        ->each->toBe('b1392450-e666-3926-a536-22c65f834433');
});

test('it returns no release for an unknown barcode', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release*' => Http::response(fixture('MusicBrainz', 'release-search-barcode-empty')),
    ]);

    expect(app(MusicBrainzClient::class)->findReleasesByBarcode('880882178313'))->toBe([]);
});

test('it searches both the UPC-A and EAN-13 forms of a barcode', function (string $input, string $query) {
    Http::fake(['musicbrainz.org/*' => Http::response(fixture('MusicBrainz', 'release-search-barcode-empty'))]);

    app(MusicBrainzClient::class)->findReleasesByBarcode($input);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://musicbrainz.org/ws/2/release?')
        && $request['query'] === $query
        && $request['fmt'] === 'json');
})->with([
    'UPC-A' => ['827969777220', 'barcode:(827969777220 OR 0827969777220)'],
    'EAN-13 with a leading zero' => ['0827969777220', 'barcode:(0827969777220 OR 827969777220)'],
    'EAN-13' => ['5099749534728', 'barcode:5099749534728'],
    'spaces and dashes' => [' 5 099749-534728 ', 'barcode:5099749534728'],
]);

test('it does not call the API for a barcode without digits', function () {
    Http::fake();

    expect(app(MusicBrainzClient::class)->findReleasesByBarcode(' - '))->toBe([]);
    Http::assertNothingSent();
});

test('it looks up a release by MBID with its release group', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release/*' => Http::response(fixture('MusicBrainz', 'release-lookup')),
    ]);

    $release = app(MusicBrainzClient::class)->lookupRelease('5eceb9ff-d7d2-31b8-b070-d6b7ab588611');

    expect($release->mbid)->toBe('5eceb9ff-d7d2-31b8-b070-d6b7ab588611')
        ->and($release->format)->toBe('CD')
        ->and($release->catalogNumber)->toBe('82796 97772 2')
        ->and($release->releaseGroup->firstReleaseYear)->toBe(2005)
        ->and($release->releaseGroup->secondaryTypes)->toBe([])
        ->and($release->releaseGroup->artists)->toEqual([
            new ArtistCredit('4b585938-f271-45e2-b19a-91c634b5e396', 'Kate Bush'),
        ]);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://musicbrainz.org/ws/2/release/5eceb9ff-d7d2-31b8-b070-d6b7ab588611?')
        && $request['inc'] === 'labels release-groups artist-credits media'
        && $request['fmt'] === 'json');
});

test('it maps a release without label, media or date to nulls', function () {
    $release = Release::fromArray([
        'id' => '5eceb9ff-d7d2-31b8-b070-d6b7ab588611',
        'title' => 'Aerial',
        'date' => '',
        'country' => null,
        'barcode' => '',
        'label-info' => [['catalog-number' => null, 'label' => null]],
        'release-group' => ['id' => '6169f5bc-b5ff-3348-b806-1b0f2a414217', 'title' => 'Aerial'],
    ]);

    expect($release->format)->toBeNull()
        ->and($release->label)->toBeNull()
        ->and($release->catalogNumber)->toBeNull()
        ->and($release->country)->toBeNull()
        ->and($release->releaseYear)->toBeNull()
        ->and($release->barcode)->toBeNull()
        ->and($release->releaseGroup->artists)->toBe([]);
});

test('it throws when the barcode search fails', function () {
    Http::fake(['musicbrainz.org/*' => Http::response(null, 503)]);

    app(MusicBrainzClient::class)->findReleasesByBarcode('724385522925');
})->throws(RequestException::class);

test('it browses every page of an artist release groups', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release-group*' => Http::sequence()
            ->push(fixture('MusicBrainz', 'release-group-browse-page-1'))
            ->push(fixture('MusicBrainz', 'release-group-browse-page-2')),
    ]);

    $results = app(MusicBrainzClient::class)->browseArtistReleaseGroups('a74b1b7f-71a5-4011-9441-d0b5e4122711');

    expect($results)->toHaveCount(6)->each->toBeInstanceOf(ReleaseGroup::class)
        ->and(array_map(fn (ReleaseGroup $group) => $group->title, $results))->toBe([
            'Hail to the Thief',
            'Lucky',
            'Greenhouse Effect vs. Radiohead',
            'In Rainbows',
            'Live in Berlin (September 2016) #StayHome #WithMe',
            'The Interview',
        ])
        ->and($results[2]->artists[1])->toEqual(new ArtistCredit('a74b1b7f-71a5-4011-9441-d0b5e4122711', 'Radiohead'));
});

test('it pages through the artist release groups by offset', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release-group*' => Http::sequence()
            ->push(fixture('MusicBrainz', 'release-group-browse-page-1'))
            ->push(fixture('MusicBrainz', 'release-group-browse-page-2')),
    ]);

    app(MusicBrainzClient::class)->browseArtistReleaseGroups('a74b1b7f-71a5-4011-9441-d0b5e4122711');

    Http::assertSentCount(2);
    foreach ([0, 3] as $offset) {
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://musicbrainz.org/ws/2/release-group?')
            && $request['artist'] === 'a74b1b7f-71a5-4011-9441-d0b5e4122711'
            && $request['inc'] === 'artist-credits'
            && (int) $request['limit'] === 100
            && (int) $request['offset'] === $offset
            && $request['fmt'] === 'json');
    }
});

test('it stops browsing on an empty page', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release-group*' => Http::response(['release-group-count' => 10, 'release-group-offset' => 0, 'release-groups' => []]),
    ]);

    expect(app(MusicBrainzClient::class)->browseArtistReleaseGroups('a74b1b7f-71a5-4011-9441-d0b5e4122711'))->toBe([]);
    Http::assertSentCount(1);
});

test('it throws when browsing release groups fails', function () {
    Http::fake(['musicbrainz.org/*' => Http::response(null, 503)]);

    app(MusicBrainzClient::class)->browseArtistReleaseGroups('a74b1b7f-71a5-4011-9441-d0b5e4122711');
})->throws(RequestException::class);
