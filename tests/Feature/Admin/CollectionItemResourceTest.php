<?php

use App\Enums\ItemCondition;
use App\Filament\Resources\CollectionItemResource\Pages\EditCollectionItem;
use App\Filament\Resources\CollectionItemResource\Pages\ListCollectionItems;
use App\Models\Album;
use App\Models\CollectionItem;
use App\Models\Release;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteAction as TableDeleteAction;
use Livewire\Livewire;

test('an admin sees the items of every user', function () {
    $items = CollectionItem::factory()->count(2)->create();

    $this->actingAs(admin());

    expect($items[0]->user_id)->not->toBe($items[1]->user_id);
    Livewire::test(ListCollectionItems::class)->assertCanSeeTableRecords($items);
});

test('the user filter keeps only that user\'s items', function () {
    $alice = CollectionItem::factory()->create();
    $bob = CollectionItem::factory()->create();

    $this->actingAs(admin());

    Livewire::test(ListCollectionItems::class)
        ->filterTable('user', $alice->user_id)
        ->assertCanSeeTableRecords([$alice])
        ->assertCanNotSeeTableRecords([$bob]);
});

test('items can be searched by album title and by user name', function () {
    $wanted = CollectionItem::factory()->withoutRelease()->create([
        'user_id' => User::factory()->create(['name' => 'Alice Martin'])->id,
        'album_id' => Album::factory()->create(['title' => 'Harvest'])->id,
    ]);
    $other = CollectionItem::factory()
        ->for(User::factory()->create(['name' => 'Bob Durand']))
        ->create();

    $this->actingAs(admin());

    Livewire::test(ListCollectionItems::class)
        ->searchTable('Harvest')
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other])
        ->searchTable('Alice')
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other]);
});

test('an admin edits the condition and notes of another user\'s item', function () {
    $item = CollectionItem::factory()->create(['condition' => ItemCondition::Good]);

    $this->actingAs(admin());

    Livewire::test(EditCollectionItem::class, ['record' => $item->getRouteKey()])
        ->fillForm(['condition' => ItemCondition::Mint->value, 'notes' => 'Pochette ouvrante'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($item->fresh())
        ->condition->toBe(ItemCondition::Mint)
        ->notes->toBe('Pochette ouvrante');
});

test('a release of another album is refused', function () {
    $item = CollectionItem::factory()->create();
    $foreign = Release::factory()->create();

    $this->actingAs(admin());

    Livewire::test(EditCollectionItem::class, ['record' => $item->getRouteKey()])
        ->fillForm(['release_id' => $foreign->id])
        ->call('save')
        ->assertHasFormErrors(['release_id']);

    expect($item->fresh()->release_id)->not->toBe($foreign->id);
});

test('the release is optional', function () {
    $item = CollectionItem::factory()->create();

    $this->actingAs(admin());

    Livewire::test(EditCollectionItem::class, ['record' => $item->getRouteKey()])
        ->fillForm(['release_id' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($item->fresh()->release_id)->toBeNull();
});

test('an item can be deleted from the list', function () {
    $item = CollectionItem::factory()->create();

    $this->actingAs(admin());

    Livewire::test(ListCollectionItems::class)->callTableAction(TableDeleteAction::class, $item);

    expect(CollectionItem::find($item->id))->toBeNull();
});

test('an item can be deleted from its edit page', function () {
    $item = CollectionItem::factory()->create();

    $this->actingAs(admin());

    Livewire::test(EditCollectionItem::class, ['record' => $item->getRouteKey()])
        ->callAction(DeleteAction::class);

    expect(CollectionItem::find($item->id))->toBeNull();
});

test('items cannot be created from the back-office', function () {
    $this->actingAs(admin())
        ->get('/admin/collection-items/create')
        ->assertNotFound();
});

test('a regular user is forbidden from the resource', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $item = CollectionItem::factory()->for($user)->create();

    $this->actingAs($user)->get('/admin/collection-items')->assertForbidden();
    $this->actingAs($user)->get("/admin/collection-items/{$item->id}/edit")->assertForbidden();
});
