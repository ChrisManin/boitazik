<?php

use App\Enums\AlbumType;
use App\Models\Album;
use App\Models\Artist;
use Illuminate\Database\QueryException;

test('album artists are ordered by position', function () {
    $album = Album::factory()->create();
    [$second, $first, $third] = Artist::factory()->count(3)->create();

    $album->artists()->attach([
        $third->id => ['position' => 2],
        $first->id => ['position' => 0],
        $second->id => ['position' => 1],
    ]);

    expect($album->artists->pluck('id')->all())
        ->toBe([$first->id, $second->id, $third->id]);
});

test('artist albums are ordered by position', function () {
    $artist = Artist::factory()->create();
    [$guest, $main] = Album::factory()->count(2)->create();

    $artist->albums()->attach([
        $guest->id => ['position' => 1],
        $main->id => ['position' => 0],
    ]);

    expect($artist->albums->pluck('id')->all())->toBe([$main->id, $guest->id]);
});

test('mbid is unique', function (string $model) {
    $mbid = fake()->uuid();
    $model::factory()->create(['mbid' => $mbid]);

    $model::factory()->create(['mbid' => $mbid]);
})->with(['artist' => [Artist::class], 'album' => [Album::class]])->throws(QueryException::class);

test('several rows without mbid are allowed', function (string $model) {
    $model::factory()->count(2)->create(['mbid' => null]);

    expect($model::whereNull('mbid')->count())->toBe(2);
})->with(['artist' => [Artist::class], 'album' => [Album::class]]);

test('album type is cast to an enum', function () {
    $album = Album::factory()->create(['type' => AlbumType::Live]);

    expect($album->fresh()->type)->toBe(AlbumType::Live);
});

test('album factory can attach a main artist', function () {
    $album = Album::factory()->withArtist()->create();

    expect($album->artists)->toHaveCount(1)
        ->and($album->artists->first()->pivot->position)->toBe(0);
});
