<?php

use App\Filament\Resources\AlbumResource;
use App\Filament\Resources\AlbumResource\Pages\EditAlbum;
use App\Filament\Resources\AlbumResource\Pages\ListAlbums;
use App\Filament\Resources\ArtistResource;
use App\Filament\Resources\ArtistResource\Pages\EditArtist;
use App\Filament\Resources\ArtistResource\Pages\ListArtists;
use App\Filament\Resources\ReleaseResource\Pages\ListReleases;
use App\Models\Album;
use App\Models\Artist;
use App\Models\CollectionItem;
use App\Models\Release;
use Filament\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteAction as TableDeleteAction;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(admin()));

test('an album nobody owns is deleted with its releases and artist links', function () {
    $album = Album::factory()->withArtist()->create();
    Release::factory()->for($album)->create();

    Livewire::test(EditAlbum::class, ['record' => $album->getRouteKey()])
        ->callAction(DeleteAction::class);

    expect(Album::find($album->id))->toBeNull()
        ->and(Release::count())->toBe(0)
        ->and(Artist::first()->albums()->count())->toBe(0);
});

test('an album nobody owns can be deleted from the list', function () {
    $album = Album::factory()->create();

    Livewire::test(ListAlbums::class)->callTableAction(TableDeleteAction::class, $album);

    expect(Album::find($album->id))->toBeNull();
});

test('an owned album cannot be deleted', function () {
    $album = Album::factory()->create();
    CollectionItem::factory()->count(2)->create(['album_id' => $album->id, 'release_id' => null]);

    Livewire::test(EditAlbum::class, ['record' => $album->getRouteKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified('Suppression impossible');
    Livewire::test(ListAlbums::class)
        ->callTableAction(TableDeleteAction::class, $album)
        ->assertNotified('Suppression impossible');

    expect(Album::find($album->id))->not->toBeNull()
        ->and(CollectionItem::where('album_id', $album->id)->count())->toBe(2);
});

test('a fictitious album is deleted with its copies once its title is typed again', function () {
    $album = Album::factory()->create(['title' => 'Faux album']);
    $release = Release::factory()->for($album)->create();
    CollectionItem::factory()->count(2)->create(['album_id' => $album->id, 'release_id' => $release->id]);
    $other = CollectionItem::factory()->create();

    Livewire::test(EditAlbum::class, ['record' => $album->getRouteKey()])
        ->callAction('deleteWithCopies', data: ['title_confirmation' => 'Faux album'])
        ->assertHasNoActionErrors()
        ->assertRedirect(AlbumResource::getUrl('index'));

    expect(Album::find($album->id))->toBeNull()
        ->and(CollectionItem::pluck('id')->all())->toBe([$other->id]);
});

test('a fictitious album is not deleted without its exact title', function () {
    $album = Album::factory()->create(['title' => 'Faux album']);
    CollectionItem::factory()->create(['album_id' => $album->id, 'release_id' => null]);

    Livewire::test(ListAlbums::class)
        ->callTableAction('deleteWithCopies', $album, data: ['title_confirmation' => 'Autre'])
        ->assertHasTableActionErrors(['title_confirmation']);

    expect(Album::find($album->id))->not->toBeNull()
        ->and(CollectionItem::count())->toBe(1);
});

test('deleting with copies is only offered for owned albums', function () {
    $owned = Album::factory()->create();
    CollectionItem::factory()->create(['album_id' => $owned->id, 'release_id' => null]);
    $free = Album::factory()->create();

    Livewire::test(ListAlbums::class)
        ->assertTableActionVisible('deleteWithCopies', $owned)
        ->assertTableActionHidden('deleteWithCopies', $free);
});

test('an album is merged from its edit page', function () {
    [$source, $target] = Album::factory()->count(2)->create();
    $item = CollectionItem::factory()->create(['album_id' => $source->id, 'release_id' => null]);

    Livewire::test(EditAlbum::class, ['record' => $source->getRouteKey()])
        ->callAction('merge', data: ['target_id' => $target->id])
        ->assertHasNoActionErrors()
        ->assertRedirect(AlbumResource::getUrl('edit', ['record' => $target]));

    expect($item->fresh()->album_id)->toBe($target->id)
        ->and(Album::find($source->id))->toBeNull();
});

test('an album is merged from the list', function () {
    [$source, $target] = Album::factory()->count(2)->create();

    Livewire::test(ListAlbums::class)
        ->callTableAction('merge', $source, data: ['target_id' => $target->id])
        ->assertHasNoTableActionErrors();

    expect(Album::find($source->id))->toBeNull();
});

test('an album cannot be merged into itself or a missing album', function (Closure $target) {
    $album = Album::factory()->create();

    Livewire::test(EditAlbum::class, ['record' => $album->getRouteKey()])
        ->callAction('merge', data: ['target_id' => $target($album)])
        ->assertHasActionErrors(['target_id']);

    expect(Album::find($album->id))->not->toBeNull();
})->with([
    'itself' => [fn (Album $album) => $album->id],
    'missing' => [fn () => 999999],
]);

test('an artist is deleted and its albums are kept', function () {
    $album = Album::factory()->withArtist()->create();
    $artist = $album->artists->first();

    Livewire::test(EditArtist::class, ['record' => $artist->getRouteKey()])
        ->callAction(DeleteAction::class);

    expect(Artist::find($artist->id))->toBeNull()
        ->and(Album::find($album->id))->not->toBeNull();
});

test('an artist can be deleted from the list', function () {
    $artist = Artist::factory()->create();

    Livewire::test(ListArtists::class)->callTableAction(TableDeleteAction::class, $artist);

    expect(Artist::find($artist->id))->toBeNull();
});

test('an artist is merged from its edit page', function () {
    [$source, $target] = Artist::factory()->count(2)->create();
    $album = Album::factory()->create();
    $source->albums()->attach($album->id, ['position' => 0]);

    Livewire::test(EditArtist::class, ['record' => $source->getRouteKey()])
        ->callAction('merge', data: ['target_id' => $target->id])
        ->assertHasNoActionErrors()
        ->assertRedirect(ArtistResource::getUrl('edit', ['record' => $target]));

    expect($album->artists()->pluck('artists.id')->all())->toBe([$target->id]);
});

test('an artist cannot be merged into itself or a missing artist', function (Closure $target) {
    $artist = Artist::factory()->create();

    Livewire::test(ListArtists::class)
        ->callTableAction('merge', $artist, data: ['target_id' => $target($artist)])
        ->assertHasTableActionErrors(['target_id']);

    expect(Artist::find($artist->id))->not->toBeNull();
})->with([
    'itself' => [fn (Artist $artist) => $artist->id],
    'missing' => [fn () => 999999],
]);

test('releases still cannot be deleted', function () {
    Livewire::test(ListReleases::class)
        ->assertTableActionDoesNotExist('delete');
});
