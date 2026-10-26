<?php

use App\Actions\MergeAlbum;
use App\Models\Album;
use App\Models\Artist;
use App\Models\CollectionItem;
use App\Models\Release;

test('merging an album keeps the copies of every owner', function () {
    [$source, $target] = Album::factory()->count(2)->create();
    $release = Release::factory()->for($source)->create();
    $first = CollectionItem::factory()->for($release)->create(['album_id' => $source->id]);
    $second = CollectionItem::factory()->withoutRelease()->create(['album_id' => $source->id]);

    app(MergeAlbum::class)->handle($source, $target);

    expect($first->fresh())->album_id->toBe($target->id)->release_id->toBe($release->id)
        ->and($second->fresh())->album_id->toBe($target->id)->release_id->toBeNull()
        ->and($first->user_id)->not->toBe($second->user_id)
        ->and(Album::find($source->id))->toBeNull();
});

test('merging an album moves its releases', function () {
    [$source, $target] = Album::factory()->count(2)->create();
    $releases = Release::factory()->count(2)->for($source)->create();

    app(MergeAlbum::class)->handle($source, $target);

    expect($target->releases()->pluck('id')->sort()->values()->all())
        ->toBe($releases->pluck('id')->sort()->values()->all());
});

test('merging an album credits the missing artists after the target ones', function () {
    [$source, $target] = Album::factory()->count(2)->create();
    [$shared, $guest, $main] = Artist::factory()->count(3)->create();
    $source->artists()->attach([$shared->id => ['position' => 0], $guest->id => ['position' => 1]]);
    $target->artists()->attach([$main->id => ['position' => 0], $shared->id => ['position' => 1]]);

    app(MergeAlbum::class)->handle($source, $target);

    expect($target->artists()->get()->mapWithKeys(fn (Artist $artist) => [$artist->id => $artist->pivot->position])->all())
        ->toBe([$main->id => 0, $shared->id => 1, $guest->id => 2]);
});

test('merging an album only fills the empty fields of the target', function () {
    $source = Album::factory()->create([
        'first_release_year' => 1980,
        'cover_url' => 'https://example.com/source.jpg',
        'spotify_id' => 'source-spotify',
    ]);
    $target = Album::factory()->create([
        'mbid' => null,
        'first_release_year' => 1981,
        'cover_url' => null,
        'spotify_id' => 'target-spotify',
    ]);
    $mbid = $source->mbid;

    $merged = app(MergeAlbum::class)->handle($source, $target);

    expect($merged)
        ->mbid->toBe($mbid)
        ->first_release_year->toBe(1981)
        ->cover_url->toBe('https://example.com/source.jpg')
        ->spotify_id->toBe('target-spotify');
});

test('an album cannot be merged into itself', function () {
    $album = Album::factory()->create();

    app(MergeAlbum::class)->handle($album, $album);
})->throws(InvalidArgumentException::class);

test('a failing album merge changes nothing', function () {
    [$source, $target] = Album::factory()->count(2)->create();
    $item = CollectionItem::factory()->create(['album_id' => $source->id, 'release_id' => null]);
    $release = Release::factory()->for($source)->create();
    Album::deleting(fn () => throw new RuntimeException('boom'));

    expect(fn () => app(MergeAlbum::class)->handle($source, $target))->toThrow(RuntimeException::class);

    expect($item->fresh()->album_id)->toBe($source->id)
        ->and($release->fresh()->album_id)->toBe($source->id)
        ->and(Album::find($source->id))->not->toBeNull();
});
