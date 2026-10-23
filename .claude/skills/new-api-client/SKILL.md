---
name: new-api-client
description: Conventions and skeleton for adding an external HTTP API client (MusicBrainz, Cover Art Archive, Spotify, Last.fm, Discogs...). Use when creating or changing an API integration, an HTTP client class, a DTO for an API response, or tests that fake HTTP calls.
---

Every external API client in Boitazik follows the same recipe. Work test-first: write a red Pest test, then the code, then refactor.

Full skeleton (config, client, DTO, fixture, test): see [skeleton.md](skeleton.md). Read it before writing the first file, and adapt it: it is an example, not code to paste.

## Conventions

1. **Client class**: `app/Services/<Api>/<Api>Client.php`, `final`, stateless. Callers get it through the container (type-hinted constructor or method injection), never with `new`. Autowiring is enough; add a binding only if the client really needs one.
2. **Configuration**: one `'<api>' => [...]` entry in `config/services.php` (`base_url`, `timeout`, `user_agent`, keys...). Secrets come only from `env()`. Add every new variable to `.env.example` **without a value**. Never commit a secret. The client reads `config('services.<api>.*')`, never `env()`.
3. **HTTP**: a private `request(): PendingRequest` method builds every call with `Http::baseUrl()`, `timeout()`, `retry()` (a few attempts, short backoff), `acceptJson()`, and `withUserAgent()` when the API requires it (MusicBrainz does). Public methods end with `->throw()` so HTTP errors surface as `RequestException`. When every call shares the same parameters (single endpoint with a `method` parameter, API key in the query, `format=json`: Last.fm), add them in one private `call()` method rather than in each public method.
   - **Errors in the body**: some APIs report errors inside the JSON, sometimes with a 200 status (Last.fm: `{"error": 6, "message": "..."}`). Detect them in `call()` and throw a dedicated `<Api>Exception` carrying the API message and code.
4. **Rate limit**: when the API documents a limit (MusicBrainz: 1 request per second), a private `throttle()` waits with `RateLimiter` before each call, keyed by the API name. Never exceed the documented limit.
5. **DTOs**: responses become read-only PHP objects in `app/Services/<Api>/Data/<Name>.php`: `final` class, promoted `public readonly` properties (PHP 8.1: no `readonly class`), a `fromArray(array $data): self` named constructor. Public client methods never return raw arrays from the response: a single DTO, or a list of DTOs typed `@return array<int, Dto>`. `fromArray()` absorbs the API's quirks so callers never see them: numbers sent as strings are cast, `""` becomes `null`, and a list that arrives as a single object (or as `""` when empty) becomes a real list.
6. **Tests**: `tests/Feature/Services/<Api>/<Api>ClientTest.php`.
   - `beforeEach`: `Http::preventStrayRequests()` (no real HTTP call, ever) and `Sleep::fake(syncWithCarbon: true)` (throttle and retries never really wait). If the API needs a key, set a fake one there with `config(['services.<api>.api_key' => 'test-key'])`: tests never depend on `.env`.
   - `Http::fake()` keyed by URL pattern, returning a JSON fixture from `tests/Fixtures/<Api>/<case>.json` (a real response, trimmed to what the test needs).
   - **Recording a fixture**: call the real API once, by hand, with the app's User-Agent and within the rate limit, then trim the response to the fields `fromArray()` reads (keep ids, drop tags, aliases, scores...). Write it pretty-printed (`python3 -m json.tool`) so diffs stay readable. Pick public catalog data only: never a response carrying an API key, a user name or any personal data. Example: `curl -s -A "Boitazik/0.1 ( https://github.com/ChrisManin/boitazik )" "https://musicbrainz.org/ws/2/release?query=barcode:724385522925&fmt=json" | python3 -m json.tool > raw.json`.
   - Assert the returned DTOs, the outgoing request with `Http::assertSent()` (URL, query, headers such as User-Agent), one error case (`RequestException`, plus `<Api>Exception` for errors in the body), and the throttle (`Sleep::assertSlept()`) when there is one.
7. **Finish**: `vendor/bin/pint`, then `php artisan test`.
