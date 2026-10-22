# API client skeleton

Worked example: searching MusicBrainz release groups (1 request per second, User-Agent required). Rename and adapt it for each API; drop the parts it doesn't need (no rate limit, no User-Agent...).

## Configuration

`config/services.php`:

```php
'musicbrainz' => [
    'base_url' => env('MUSICBRAINZ_BASE_URL', 'https://musicbrainz.org/ws/2'),
    'user_agent' => env('MUSICBRAINZ_USER_AGENT', 'Boitazik/0.1 ( https://github.com/ChrisManin/boitazik )'),
    'timeout' => 10,
    'requests_per_second' => 1,
],
```

`.env.example` (keys only, never a value; an API with a secret adds e.g. `LASTFM_API_KEY=`):

```dotenv
MUSICBRAINZ_BASE_URL=
MUSICBRAINZ_USER_AGENT=
```

An empty variable in `.env` reads as `null` in `env()`, which overrides the default. If a key may be left empty, use `env('X') ?: 'default'`.

## Client

`app/Services/MusicBrainz/MusicBrainzClient.php`:

```php
<?php

namespace App\Services\MusicBrainz;

use App\Services\MusicBrainz\Data\ReleaseGroup;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

final class MusicBrainzClient
{
    /**
     * @return array<int, ReleaseGroup>
     */
    public function searchReleaseGroups(string $query, int $limit = 10): array
    {
        $response = $this->request()
            ->get('release-group', ['query' => $query, 'limit' => $limit, 'fmt' => 'json'])
            ->throw();

        return array_map(
            fn (array $item) => ReleaseGroup::fromArray($item),
            $response->json('release-groups', []),
        );
    }

    private function request(): PendingRequest
    {
        $this->throttle();

        return Http::baseUrl(config('services.musicbrainz.base_url'))
            ->timeout(config('services.musicbrainz.timeout'))
            ->retry(3, 500, throw: false)
            ->acceptJson()
            ->withUserAgent(config('services.musicbrainz.user_agent'));
    }

    private function throttle(): void
    {
        $perSecond = config('services.musicbrainz.requests_per_second');

        while (! RateLimiter::attempt('musicbrainz', $perSecond, fn () => true, 1)) {
            Sleep::for(100)->milliseconds();
        }
    }
}
```

Notes:
- `Sleep` (not `usleep`) so tests can fake the wait.
- Retries go through Laravel's `Sleep` too, so `Sleep::fake()` makes them instant in tests.
- Callers type-hint `MusicBrainzClient` and let the container build it.

## DTO

`app/Services/MusicBrainz/Data/ReleaseGroup.php`:

```php
<?php

namespace App\Services\MusicBrainz\Data;

final class ReleaseGroup
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $primaryType,
        public readonly ?string $firstReleaseDate,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'],
            primaryType: $data['primary-type'] ?? null,
            firstReleaseDate: ($data['first-release-date'] ?? '') ?: null,
        );
    }
}
```

Map only the fields the app uses. Optional API fields become nullable properties.

## Fixture

`tests/Fixtures/MusicBrainz/release-group-search.json`: a real response, trimmed.

```json
{
    "count": 2,
    "offset": 0,
    "release-groups": [
        {
            "id": "b84ee12a-09ef-421b-82de-0441a926375b",
            "title": "OK Computer",
            "primary-type": "Album",
            "first-release-date": "1997-05-21"
        },
        {
            "id": "1b022e01-4da6-387b-8658-8678046e4cef",
            "title": "OK Computer OKNOTOK 1997 2017",
            "primary-type": "Album",
            "first-release-date": ""
        }
    ]
}
```

## Test

`tests/Feature/Services/MusicBrainz/MusicBrainzClientTest.php`:

```php
<?php

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

function musicBrainzFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/MusicBrainz/{$name}.json")), true);
}

test('it searches release groups and returns DTOs', function () {
    Http::fake([
        'musicbrainz.org/ws/2/release-group*' => Http::response(musicBrainzFixture('release-group-search')),
    ]);

    $results = app(MusicBrainzClient::class)->searchReleaseGroups('OK Computer');

    expect($results)->toHaveCount(2)->each->toBeInstanceOf(ReleaseGroup::class)
        ->and($results[0]->id)->toBe('b84ee12a-09ef-421b-82de-0441a926375b')
        ->and($results[0]->firstReleaseDate)->toBe('1997-05-21')
        ->and($results[1]->firstReleaseDate)->toBeNull();

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://musicbrainz.org/ws/2/release-group')
        && $request['query'] === 'OK Computer'
        && $request['fmt'] === 'json'
        && str_starts_with($request->header('User-Agent')[0], 'Boitazik/'));
});

test('it throws when the API fails', function () {
    Http::fake(['musicbrainz.org/*' => Http::response(null, 503)]);

    app(MusicBrainzClient::class)->searchReleaseGroups('OK Computer');
})->throws(RequestException::class);

test('it waits between two calls to respect the rate limit', function () {
    Http::fake(['musicbrainz.org/*' => Http::response(musicBrainzFixture('release-group-search'))]);
    $client = app(MusicBrainzClient::class);

    $client->searchReleaseGroups('OK Computer');
    Sleep::assertNeverSlept();

    $client->searchReleaseGroups('Kid A');
    Sleep::assertSlept(fn () => true, times: 10);
});
```

Notes:
- `Sleep::fake(syncWithCarbon: true)` makes fake sleeps move the clock forward, so the rate limiter (array cache in tests) frees up without real waiting.
- Name the fixture helper after the API (`musicBrainzFixture`): Pest test functions are global, so two clients defining `fixture()` would collide. Once a second client needs one, move a shared `fixture(string $api, string $name)` helper to `tests/Pest.php`.
- With a limit above 1 request per second (Last.fm: 5), make that many calls with `Sleep::assertNeverSlept()`, then one more before `Sleep::assertSlept()`.

## Variant: single endpoint, API key, errors in the body

Last.fm-style APIs (one URL, `method` parameter, key in the query, errors sent as `{"error": 6, "message": "..."}`). The public methods call `call()` instead of `request()`:

```php
public function getArtistInfo(string $artist): Artist
{
    return Artist::fromArray($this->call('artist.getinfo', ['artist' => $artist])->json('artist'));
}

private function call(string $method, array $params): Response
{
    $response = $this->request()->get('', [
        'method' => $method,
        ...$params,
        'api_key' => config('services.lastfm.api_key'),
        'format' => 'json',
    ]);

    if ($response->json('error') !== null) {
        throw new LastFmException($response->json('message', 'Unknown Last.fm error'), (int) $response->json('error'));
    }

    return $response->throw();
}
```

`LastFmException` is a `final` class extending `RuntimeException`, next to the client. Test it with a fixture of the error body: `->throws(LastFmException::class, 'The artist you supplied could not be found')`.
