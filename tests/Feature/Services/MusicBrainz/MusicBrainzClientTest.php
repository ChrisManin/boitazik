<?php

use App\Services\MusicBrainz\Data\ArtistCredit;
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
