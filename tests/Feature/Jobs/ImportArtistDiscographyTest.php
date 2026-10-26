<?php

use App\Enums\AlbumType;
use App\Jobs\ImportArtistDiscography;
use App\Models\Album;
use App\Models\Artist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake(syncWithCarbon: true);
});

function radiohead(array $attributes = []): Artist
{
    return Artist::withoutEvents(fn () => Artist::factory()->create([
        'name' => 'Radiohead',
        'mbid' => 'a74b1b7f-71a5-4011-9441-d0b5e4122711',
        ...$attributes,
    ]));
}

// Two pages of release groups; In Rainbows has no cover
function fakeDiscography(): void
{
    Http::fake([
        'musicbrainz.org/ws/2/release-group*' => Http::sequence()
            ->push(fixture('MusicBrainz', 'release-group-browse-page-1'))
            ->push(fixture('MusicBrainz', 'release-group-browse-page-2'))
            ->push(fixture('MusicBrainz', 'release-group-browse-page-1'))
            ->push(fixture('MusicBrainz', 'release-group-browse-page-2')),
        'coverartarchive.org/release-group/6e335887-60ba-38f0-95af-fae7774336bf/*' => Http::response(null, 404),
        'coverartarchive.org/*' => Http::response('', 307, ['Location' => 'https://archive.org/download/cover_thumb250.jpg']),
    ]);
}

function importDiscography(Artist $artist): void
{
    app()->call([new ImportArtistDiscography($artist), 'handle']);
}

test('creating an artist with a MBID queues the discography import', function () {
    Queue::fake();

    $artist = Artist::create(['name' => 'Radiohead', 'mbid' => 'a74b1b7f-71a5-4011-9441-d0b5e4122711']);

    Queue::assertPushed(ImportArtistDiscography::class, fn (ImportArtistDiscography $job) => $job->artist->is($artist));
});

test('creating an artist without MBID queues nothing', function () {
    Queue::fake();

    Artist::create(['name' => 'Groupe local']);

    Queue::assertNothingPushed();
});

test('an artist enriched less than 30 days ago is not imported again', function () {
    Queue::fake();

    Artist::create([
        'name' => 'Radiohead',
        'mbid' => 'a74b1b7f-71a5-4011-9441-d0b5e4122711',
        'enriched_at' => now()->subDays(29),
    ]);

    Queue::assertNothingPushed();
});

test('an artist enriched more than 30 days ago is imported again', function () {
    Queue::fake();

    Artist::create([
        'name' => 'Radiohead',
        'mbid' => 'a74b1b7f-71a5-4011-9441-d0b5e4122711',
        'enriched_at' => now()->subDays(31),
    ]);

    Queue::assertPushed(ImportArtistDiscography::class);
});

test('a manual artist linked to MusicBrainz queues the import', function () {
    Queue::fake();
    $artist = Artist::create(['name' => 'Radiohead']);

    $artist->update(['mbid' => 'a74b1b7f-71a5-4011-9441-d0b5e4122711']);

    Queue::assertPushed(ImportArtistDiscography::class);
});

test('correcting the MBID of a recently enriched artist queues the import', function () {
    Queue::fake();
    $artist = Artist::create([
        'name' => 'Radiohead',
        'mbid' => 'a74b1b7f-71a5-4011-9441-d0b5e4122711',
        'enriched_at' => now()->subDay(),
    ]);

    $artist->update(['mbid' => 'b10bbbfc-cf9e-42e0-be17-e2c3e1d2600d']);

    Queue::assertPushed(ImportArtistDiscography::class);
});

test('updating another field of an artist queues nothing', function () {
    $artist = radiohead();
    Queue::fake();

    $artist->update(['country' => 'GB']);

    Queue::assertNothingPushed();
});

test('the import runs on the queue, unique per artist, with retries', function () {
    $job = new ImportArtistDiscography(radiohead());

    expect($job)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class)
        ->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe((string) $job->artist->id)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([60, 300]);
});

test('it imports the albums of every page and links them to the artist', function () {
    fakeDiscography();
    $artist = radiohead();

    importDiscography($artist);

    $albums = $artist->albums()->get()->sortBy('title')->values();
    expect($albums->pluck('title')->all())->toBe(['Greenhouse Effect vs. Radiohead', 'Hail to the Thief', 'In Rainbows'])
        ->and($albums->pluck('type')->all())->toBe([AlbumType::Album, AlbumType::Album, AlbumType::Album])
        ->and($albums[1]->mbid)->toBe('5c14fd50-a2f1-3672-9537-b0dad91bea2f')
        ->and($albums[1]->first_release_year)->toBe(2003)
        ->and($albums[1]->cover_url)->toBe('https://coverartarchive.org/release-group/5c14fd50-a2f1-3672-9537-b0dad91bea2f/front-250')
        ->and($albums[2]->cover_url)->toBeNull()
        ->and($albums[0]->pivot->position)->toBe(1)
        ->and($albums[1]->pivot->position)->toBe(0)
        ->and($artist->fresh()->enriched_at)->not->toBeNull();
});

test('it skips singles, broadcasts and interviews', function () {
    fakeDiscography();

    importDiscography(radiohead());

    expect(Album::pluck('title')->all())->not->toContain('Lucky')
        ->not->toContain('Live in Berlin (September 2016) #StayHome #WithMe')
        ->not->toContain('The Interview');
});

test('it is idempotent', function () {
    fakeDiscography();
    $artist = radiohead();

    importDiscography($artist);
    importDiscography($artist);

    expect(Album::count())->toBe(3)
        ->and(DB::table('album_artist')->count())->toBe(3);
});

test('it links an album already known by MBID without duplicating it', function () {
    fakeDiscography();
    $album = Album::factory()->create([
        'title' => 'Hail to the Thief',
        'mbid' => '5c14fd50-a2f1-3672-9537-b0dad91bea2f',
        'cover_url' => 'https://example.com/cover.jpg',
    ]);
    $artist = radiohead();

    importDiscography($artist);

    expect(Album::where('mbid', '5c14fd50-a2f1-3672-9537-b0dad91bea2f')->count())->toBe(1)
        ->and($album->fresh()->cover_url)->toBe('https://example.com/cover.jpg')
        ->and($artist->albums()->whereKey($album)->exists())->toBeTrue();
});

test('it merges an album entered by hand under the same title', function () {
    fakeDiscography();
    $artist = radiohead();
    $manual = Album::factory()->create(['title' => 'hail to the thief', 'mbid' => null, 'first_release_year' => null]);
    $manual->artists()->attach($artist, ['position' => 0]);

    importDiscography($artist);

    expect(Album::whereRaw('LOWER(title) = ?', ['hail to the thief'])->count())->toBe(1)
        ->and($manual->fresh()->mbid)->toBe('5c14fd50-a2f1-3672-9537-b0dad91bea2f')
        ->and($manual->fresh()->first_release_year)->toBe(2003);
});
