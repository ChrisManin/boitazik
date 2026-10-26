<?php

use App\Enums\AlbumType;
use App\Enums\ReleaseFormat;
use App\Filament\Resources\AlbumResource\Pages\CreateAlbum;
use App\Filament\Resources\AlbumResource\Pages\EditAlbum;
use App\Filament\Resources\AlbumResource\RelationManagers\ArtistsRelationManager;
use App\Filament\Resources\AlbumResource\RelationManagers\ReleasesRelationManager;
use App\Filament\Resources\ArtistResource\Pages\ListArtists;
use App\Filament\Resources\ReleaseResource;
use App\Filament\Resources\ReleaseResource\Pages\CreateRelease;
use App\Filament\Resources\ReleaseResource\Pages\ListReleases;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Release;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Livewire\Livewire;

dataset('catalog resources', [
    'artists' => ['artists', fn () => Artist::factory()->create()],
    'albums' => ['albums', fn () => Album::factory()->create()],
    'releases' => ['releases', fn () => Release::factory()->create()],
]);

test('admins can list catalog resources', function (string $slug, Closure $record) {
    $record();

    $this->actingAs(admin())
        ->get("/admin/{$slug}")
        ->assertOk();
})->with('catalog resources');

test('admins can open the create and edit pages', function (string $slug, Closure $record) {
    $model = $record();
    $this->actingAs(admin());

    $this->get("/admin/{$slug}/create")->assertOk();
    $this->get("/admin/{$slug}/{$model->id}/edit")->assertOk();
})->with('catalog resources');

test('regular users cannot reach catalog resources', function (string $slug, Closure $record) {
    $model = $record();
    $this->actingAs(User::factory()->create(['is_admin' => false]));

    foreach (["/admin/{$slug}", "/admin/{$slug}/create", "/admin/{$slug}/{$model->id}/edit"] as $url) {
        $this->get($url)->assertForbidden();
    }
})->with('catalog resources');

test('lists have a pagination above and below the table', function () {
    Album::factory()->count(11)->create();

    $html = $this->actingAs(admin())->get('/admin/albums')->assertOk()->getContent();

    expect(substr_count($html, 'fi-ta-pagination'))->toBe(2);
});

test('the admin panel uses the full screen width', function () {
    expect(Filament::getPanel('admin')->getMaxContentWidth())->toBe(MaxWidth::Full);
});

test('artists can be searched by MBID', function () {
    $wanted = Artist::factory()->create();
    $other = Artist::factory()->create();
    $this->actingAs(admin());

    Livewire::test(ListArtists::class)
        ->searchTable($wanted->mbid)
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other]);
});

test('releases can be searched by barcode', function () {
    $wanted = Release::factory()->create(['barcode' => '5099749994324']);
    $other = Release::factory()->create(['barcode' => '0724384260927']);
    $this->actingAs(admin());

    Livewire::test(ListReleases::class)
        ->searchTable('5099749994324')
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other]);
});

test('a release is created with a format from the enum', function () {
    $album = Album::factory()->create();
    $this->actingAs(admin());

    Livewire::test(CreateRelease::class)
        ->fillForm([
            'album_id' => $album->id,
            'format' => ReleaseFormat::Vinyl->value,
            'barcode' => '5099749994324',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($album->releases()->sole())
        ->format->toBe(ReleaseFormat::Vinyl)
        ->barcode->toBe('5099749994324');
});

test('an unknown format is rejected', function () {
    $this->actingAs(admin());

    Livewire::test(CreateRelease::class)
        ->fillForm(['album_id' => Album::factory()->create()->id, 'format' => 'laserdisc'])
        ->call('create')
        ->assertHasFormErrors(['format']);
});

test('an artist is attached to an album with a position', function () {
    $album = Album::factory()->create();
    $artist = Artist::factory()->create();
    $this->actingAs(admin());

    Livewire::test(ArtistsRelationManager::class, ['ownerRecord' => $album, 'pageClass' => EditAlbum::class])
        ->callTableAction(AttachAction::class, data: ['recordId' => $artist->id, 'position' => 1])
        ->assertHasNoTableActionErrors();

    expect($album->artists()->sole())
        ->id->toBe($artist->id)
        ->pivot->position->toBe(1);
});

test('a release is added from the album', function () {
    $album = Album::factory()->create();
    $this->actingAs(admin());

    Livewire::test(ReleasesRelationManager::class, ['ownerRecord' => $album, 'pageClass' => EditAlbum::class])
        ->callTableAction(CreateAction::class, data: ['format' => ReleaseFormat::Cassette->value])
        ->assertHasNoTableActionErrors();

    expect($album->releases()->sole()->format)->toBe(ReleaseFormat::Cassette);
});

test('an album is created with a type from the enum', function () {
    $this->actingAs(admin());

    Livewire::test(CreateAlbum::class)
        ->fillForm(['title' => 'Kid A', 'type' => AlbumType::Live->value])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Album::sole()->type)->toBe(AlbumType::Live);
});

test('a new artist defaults to the next free position', function () {
    $album = Album::factory()->create();
    $album->artists()->attach(Artist::factory()->create(), ['position' => 0]);
    $guest = Artist::factory()->create();
    $this->actingAs(admin());

    Livewire::test(ArtistsRelationManager::class, ['ownerRecord' => $album, 'pageClass' => EditAlbum::class])
        ->callTableAction(AttachAction::class, data: ['recordId' => $guest->id])
        ->assertHasNoTableActionErrors();

    expect($album->artists()->find($guest->id)->pivot->position)->toBe(1);
});

test('the position of an attached artist is edited on the pivot', function () {
    $album = Album::factory()->create();
    $artist = Artist::factory()->create();
    $album->artists()->attach($artist, ['position' => 0]);
    $this->actingAs(admin());

    Livewire::test(ArtistsRelationManager::class, ['ownerRecord' => $album, 'pageClass' => EditAlbum::class])
        ->callTableAction(EditAction::class, $artist, data: ['position' => 2])
        ->assertHasNoTableActionErrors();

    expect($album->artists()->sole()->pivot->position)->toBe(2);
});

test('a position beyond the column range is rejected', function () {
    $album = Album::factory()->create();
    $this->actingAs(admin());

    Livewire::test(ArtistsRelationManager::class, ['ownerRecord' => $album, 'pageClass' => EditAlbum::class])
        ->callTableAction(AttachAction::class, data: ['recordId' => Artist::factory()->create()->id, 'position' => 70000])
        ->assertHasTableActionErrors(['position']);
});

test('a country code is stored in upper case', function () {
    $album = Album::factory()->create();
    $this->actingAs(admin());

    Livewire::test(CreateRelease::class)
        ->fillForm(['album_id' => $album->id, 'format' => ReleaseFormat::Cd->value, 'country' => 'fr'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($album->releases()->sole()->country)->toBe('FR');
});

test('a year before 1900 is accepted', function () {
    $album = Album::factory()->create(['first_release_year' => 1898]);
    $this->actingAs(admin());

    Livewire::test(EditAlbum::class, ['record' => $album->getRouteKey()])
        ->fillForm(['title' => 'Early recordings'])
        ->call('save')
        ->assertHasNoFormErrors();
});

test('albums in the release select show their artists', function () {
    $album = Album::factory()->create(['title' => 'Greatest Hits', 'first_release_year' => 1981]);
    $album->artists()->attach(Artist::factory()->create(['name' => 'Queen']), ['position' => 0]);

    expect(ReleaseResource::albumOptionLabel($album))
        ->toBe('Greatest Hits — Queen (1981)');
});
