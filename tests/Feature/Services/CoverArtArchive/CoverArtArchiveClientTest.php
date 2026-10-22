<?php

use App\Services\CoverArtArchive\CoverArtArchiveClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const OK_COMPUTER_MBID = 'b1392450-e666-3926-a536-22c65f834433';

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake(syncWithCarbon: true);
});

test('it returns the front cover URL when the release group has one', function () {
    // Real response: a 307 redirect to the thumbnail stored on archive.org.
    Http::fake([
        'coverartarchive.org/*' => Http::response('See: https://archive.org/download/mbid-30702389-5c67-4438-9ea0-2351c8de0f1d/mbid-30702389-5c67-4438-9ea0-2351c8de0f1d-43287476628_thumb250.jpg', 307, [
            'Location' => 'https://archive.org/download/mbid-30702389-5c67-4438-9ea0-2351c8de0f1d/mbid-30702389-5c67-4438-9ea0-2351c8de0f1d-43287476628_thumb250.jpg',
        ]),
    ]);

    $url = app(CoverArtArchiveClient::class)->frontCoverUrl(OK_COMPUTER_MBID);

    expect($url)->toBe('https://coverartarchive.org/release-group/'.OK_COMPUTER_MBID.'/front-250');

    Http::assertSent(fn (Request $request) => $request->method() === 'HEAD'
        && $request->url() === 'https://coverartarchive.org/release-group/'.OK_COMPUTER_MBID.'/front-250'
        && str_starts_with($request->header('User-Agent')[0], 'Boitazik/'));
});

test('it returns null when the release group has no cover', function () {
    Http::fake(['coverartarchive.org/*' => Http::response(null, 404)]);

    expect(app(CoverArtArchiveClient::class)->frontCoverUrl(OK_COMPUTER_MBID))->toBeNull();
});

test('it throws when the API is unavailable', function () {
    Http::fake(['coverartarchive.org/*' => Http::response(null, 503)]);

    app(CoverArtArchiveClient::class)->frontCoverUrl(OK_COMPUTER_MBID);
})->throws(RequestException::class);
