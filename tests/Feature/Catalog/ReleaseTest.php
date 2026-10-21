<?php

use App\Enums\ReleaseFormat;
use App\Models\Album;
use App\Models\Release;
use Illuminate\Database\QueryException;

test('an album has many releases', function () {
    $album = Album::factory()->create();
    Release::factory()->count(2)->for($album)->create();

    expect($album->releases)->toHaveCount(2)
        ->and($album->releases->first()->album->is($album))->toBeTrue();
});

test('release format is cast to an enum', function () {
    $release = Release::factory()->create(['format' => ReleaseFormat::Vinyl]);

    expect($release->fresh()->format)->toBe(ReleaseFormat::Vinyl);
});

test('release mbid is unique', function () {
    $mbid = fake()->uuid();
    Release::factory()->create(['mbid' => $mbid]);

    Release::factory()->create(['mbid' => $mbid]);
})->throws(QueryException::class);

test('several releases without mbid are allowed', function () {
    Release::factory()->count(2)->create(['mbid' => null]);

    expect(Release::whereNull('mbid')->count())->toBe(2);
});
