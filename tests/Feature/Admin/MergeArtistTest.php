<?php

use App\Actions\MergeArtist;
use App\Jobs\ImportArtistDiscography;
use App\Models\Album;
use App\Models\Artist;
use Illuminate\Support\Facades\Queue;

test('merging an artist credits the target on its albums with the same position', function () {
    [$source, $target] = Artist::factory()->count(2)->create();
    [$main, $guest, $shared] = Album::factory()->count(3)->create();
    $source->albums()->attach([
        $main->id => ['position' => 0],
        $guest->id => ['position' => 1],
        $shared->id => ['position' => 0],
    ]);
    $target->albums()->attach($shared->id, ['position' => 2]);

    app(MergeArtist::class)->handle($source, $target);

    expect($target->albums()->get()->mapWithKeys(fn (Album $album) => [$album->id => $album->pivot->position])->sortKeys()->all())
        ->toBe([$main->id => 0, $guest->id => 1, $shared->id => 2])
        ->and($shared->artists()->count())->toBe(1)
        ->and(Artist::find($source->id))->toBeNull();
});

test('merging an artist only fills the empty fields of the target', function () {
    Queue::fake();
    $source = Artist::factory()->create(['country' => 'GB', 'image_url' => 'https://example.com/source.jpg']);
    $target = Artist::factory()->create(['mbid' => null, 'country' => 'FR', 'image_url' => null]);
    $mbid = $source->mbid;

    $merged = app(MergeArtist::class)->handle($source, $target);

    expect($merged)
        ->mbid->toBe($mbid)
        ->country->toBe('FR')
        ->image_url->toBe('https://example.com/source.jpg');
    // A new MBID makes the target importable
    Queue::assertPushed(ImportArtistDiscography::class, fn ($job) => $job->artist->is($target));
});

test('an artist cannot be merged into itself', function () {
    $artist = Artist::factory()->create();

    app(MergeArtist::class)->handle($artist, $artist);
})->throws(InvalidArgumentException::class);

test('a failing artist merge changes nothing', function () {
    [$source, $target] = Artist::factory()->count(2)->create();
    $album = Album::factory()->create();
    $source->albums()->attach($album->id, ['position' => 0]);
    Artist::deleting(fn () => throw new RuntimeException('boom'));

    expect(fn () => app(MergeArtist::class)->handle($source, $target))->toThrow(RuntimeException::class);

    expect($album->artists()->pluck('artists.id')->all())->toBe([$source->id]);
});

test('deleting an artist keeps its albums and imports nothing', function () {
    $artist = Artist::factory()->create();
    $album = Album::factory()->create();
    $artist->albums()->attach($album->id, ['position' => 0]);
    Queue::fake();

    $artist->delete();

    expect(Album::find($album->id))->not->toBeNull()
        ->and($album->artists()->count())->toBe(0);
    Queue::assertNothingPushed();
});
