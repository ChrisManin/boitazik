<?php

use App\Enums\ItemCondition;
use App\Models\Album;
use App\Models\CollectionItem;
use App\Models\Release;
use App\Models\User;
use Illuminate\Database\QueryException;

test('an item without a release is valid', function () {
    $item = CollectionItem::factory()->withoutRelease()->create()->fresh();

    expect($item->release_id)->toBeNull()
        ->and($item->release)->toBeNull()
        ->and($item->album)->toBeInstanceOf(Album::class)
        ->and($item->user)->toBeInstanceOf(User::class);
});

test('an item belongs to the album of its release', function () {
    $item = CollectionItem::factory()->create();

    expect($item->release)->toBeInstanceOf(Release::class)
        ->and($item->album_id)->toBe($item->release->album_id);
});

test('a user collection does not contain items of another user', function () {
    [$alice, $bob] = User::factory()->count(2)->create();
    $aliceItems = CollectionItem::factory()->count(2)->for($alice)->create();
    CollectionItem::factory()->for($bob)->create();

    expect($alice->collectionItems->pluck('id')->sort()->values()->all())
        ->toBe($aliceItems->pluck('id')->sort()->values()->all());
});

test('deleting a user deletes their items but keeps the catalog', function () {
    $item = CollectionItem::factory()->create();

    $item->user->delete();

    expect(CollectionItem::count())->toBe(0)
        ->and(Album::whereKey($item->album_id)->exists())->toBeTrue()
        ->and(Release::whereKey($item->release_id)->exists())->toBeTrue();
});

test('deleting a release keeps the item without a release', function () {
    $item = CollectionItem::factory()->create();

    $item->release->delete();

    expect($item->fresh()->release_id)->toBeNull();
});

test('an owned album cannot be deleted', function () {
    $item = CollectionItem::factory()->withoutRelease()->create();

    $item->album->delete();
})->throws(QueryException::class);

test('item condition is cast to an enum', function () {
    $item = CollectionItem::factory()->create(['condition' => ItemCondition::NearMint]);

    expect($item->fresh()->condition)->toBe(ItemCondition::NearMint);
});
