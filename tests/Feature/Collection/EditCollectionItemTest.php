<?php

use App\Enums\ItemCondition;
use App\Enums\ReleaseFormat;
use App\Models\Album;
use App\Models\Artist;
use App\Models\CollectionItem;
use App\Models\Release;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot edit or remove an item', function () {
    $item = CollectionItem::factory()->create();

    $this->get("/collection/{$item->id}/edit")->assertRedirect('/login');
    $this->put("/collection/{$item->id}", ['notes' => 'Hacked'])->assertRedirect('/login');
    $this->delete("/collection/{$item->id}")->assertRedirect('/login');

    expect($item->fresh()->notes)->not->toBe('Hacked');
});

test('the edit page shows the item and the form options', function () {
    $artist = Artist::factory()->create(['name' => 'Daft Punk']);
    $album = Album::factory()->create(['title' => 'Discovery', 'first_release_year' => 2001]);
    $album->artists()->attach($artist, ['position' => 0]);
    $release = Release::factory()->for($album)->create(['format' => ReleaseFormat::Vinyl]);
    $item = CollectionItem::factory()->create([
        'release_id' => $release->id,
        'condition' => ItemCondition::NearMint,
        'acquired_at' => '2024-05-12',
        'notes' => 'Pressage original',
    ]);

    $this->actingAs($item->user)
        ->get("/collection/{$item->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Collection/Edit')
            ->where('item', [
                'id' => $item->id,
                'title' => 'Discovery',
                'cover_url' => $album->cover_url,
                'artists' => ['Daft Punk'],
                'year' => 2001,
                'format' => 'vinyl',
                'condition' => 'near_mint',
                'acquired_at' => '2024-05-12',
                'notes' => 'Pressage original',
            ])
            ->has('formats', count(ReleaseFormat::cases()))
            ->has('conditions', count(ItemCondition::cases()))
        );
});

test('an item is updated', function () {
    $item = CollectionItem::factory()->create();
    $release = $item->release;

    $this->actingAs($item->user)
        ->put("/collection/{$item->id}", [
            'format' => $release->format->value,
            'condition' => 'good',
            'acquired_at' => '2023-01-15',
            'notes' => 'Pochette abîmée',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/collection')
        ->assertSessionHas('success', 'Exemplaire modifié.');

    $item->refresh();

    expect($item->condition)->toBe(ItemCondition::Good)
        ->and($item->acquired_at->toDateString())->toBe('2023-01-15')
        ->and($item->notes)->toBe('Pochette abîmée')
        // Same format: the detailed release is kept
        ->and($item->release_id)->toBe($release->id);
});

test('optional fields can be cleared', function () {
    $item = CollectionItem::factory()->create(['notes' => 'Une note']);

    $this->actingAs($item->user)
        ->put("/collection/{$item->id}", [
            'format' => '',
            'condition' => '',
            'acquired_at' => '',
            'notes' => '',
        ])
        ->assertSessionHasNoErrors();

    $item->refresh();

    expect($item->release_id)->toBeNull()
        ->and($item->condition)->toBeNull()
        ->and($item->acquired_at)->toBeNull()
        ->and($item->notes)->toBeNull()
        // The release stays in the shared catalog
        ->and(Release::count())->toBe(1);
});

test('a new format links the item to a minimal release of the album', function () {
    $item = CollectionItem::factory()->create();
    $oldRelease = $item->release;

    $this->actingAs($item->user)
        ->put("/collection/{$item->id}", ['format' => 'cassette'])
        ->assertSessionHasNoErrors();

    $newRelease = $item->fresh()->release;

    expect($newRelease->id)->not->toBe($oldRelease->id)
        ->and($newRelease->album_id)->toBe($item->album_id)
        ->and($newRelease->format)->toBe(ReleaseFormat::Cassette)
        ->and($newRelease->label)->toBeNull()
        ->and($oldRelease->fresh())->not->toBeNull();
});

test('an existing minimal release of the new format is reused', function () {
    $item = CollectionItem::factory()->withoutRelease()->create();
    $minimal = Release::create(['album_id' => $item->album_id, 'format' => ReleaseFormat::Vinyl]);

    $this->actingAs($item->user)
        ->put("/collection/{$item->id}", ['format' => 'vinyl']);

    expect(Release::count())->toBe(1)
        ->and($item->fresh()->release_id)->toBe($minimal->id);
});

test('invalid updates are rejected with French messages', function () {
    $item = CollectionItem::factory()->create();

    $this->actingAs($item->user)
        ->from("/collection/{$item->id}/edit")
        ->put("/collection/{$item->id}", [
            'format' => 'laserdisc',
            'condition' => 'broken',
            'acquired_at' => now()->addDay()->toDateString(),
            'notes' => str_repeat('a', 2001),
        ])
        ->assertRedirect("/collection/{$item->id}/edit")
        ->assertSessionHasErrors([
            'format' => "Le format choisi n'est pas valide.",
            'condition' => "L'état choisi n'est pas valide.",
            'acquired_at' => "La date d'acquisition ne peut pas être dans le futur.",
            'notes' => 'Les notes ne peuvent pas dépasser 2000 caractères.',
        ]);
});

test('an item is removed but the shared catalog is kept', function () {
    $item = CollectionItem::factory()->create();
    $item->album->artists()->attach(Artist::factory()->create(), ['position' => 0]);

    $this->actingAs($item->user)
        ->delete("/collection/{$item->id}")
        ->assertRedirect('/collection')
        ->assertSessionHas('success', 'Album retiré de votre collection.');

    expect(CollectionItem::count())->toBe(0)
        ->and(Album::count())->toBe(1)
        ->and(Artist::count())->toBe(1)
        ->and(Release::count())->toBe(1);
});

test("another user's item is forbidden", function () {
    $item = CollectionItem::factory()->create(['notes' => 'À moi']);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get("/collection/{$item->id}/edit")->assertForbidden();
    $this->actingAs($intruder)->put("/collection/{$item->id}", ['notes' => 'Volé'])->assertForbidden();
    $this->actingAs($intruder)->delete("/collection/{$item->id}")->assertForbidden();

    expect($item->fresh()->notes)->toBe('À moi');
});
