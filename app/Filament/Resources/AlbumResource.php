<?php

namespace App\Filament\Resources;

use App\Actions\MergeAlbum;
use App\Enums\AlbumType;
use App\Filament\Resources\AlbumResource\Pages;
use App\Filament\Resources\AlbumResource\RelationManagers;
use App\Models\Album;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class AlbumResource extends Resource
{
    protected static ?string $model = Album::class;

    protected static ?string $navigationIcon = 'heroicon-o-musical-note';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(191),
                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options(AlbumType::class)
                    ->enum(AlbumType::class)
                    ->default(AlbumType::Album)
                    ->required(),
                Forms\Components\TextInput::make('first_release_year')
                    ->label('Année de première sortie')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(2100),
                Forms\Components\TextInput::make('mbid')
                    ->label('MBID')
                    ->uuid()
                    ->unique(Album::class, 'mbid', ignoreRecord: true),
                Forms\Components\TextInput::make('spotify_id')
                    ->label('ID Spotify')
                    ->maxLength(191),
                Forms\Components\TextInput::make('cover_url')
                    ->label('URL de la pochette')
                    ->url()
                    ->maxLength(191),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('artists.name')
                    ->label('Artistes')
                    ->listWithLineBreaks(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('first_release_year')
                    ->label('Année')
                    ->sortable(),
                Tables\Columns\TextColumn::make('mbid')
                    ->label('MBID')
                    ->searchable(),
            ])
            ->defaultSort('title')
            ->actions([
                Tables\Actions\EditAction::make(),
                static::configureMergeAction(Tables\Actions\Action::make('merge')),
                static::configureDeleteAction(Tables\Actions\DeleteAction::make()),
                static::configureDeleteWithCopiesAction(Tables\Actions\Action::make('deleteWithCopies')),
            ]);
    }

    // Shared by the list and the edit page, whose action classes differ
    public static function configureDeleteAction(MountableAction $action): MountableAction
    {
        return $action
            ->modalDescription('Ses éditions et ses liens vers les artistes seront supprimés.')
            // An owned album would fail on the foreign key: the copies must move first
            ->before(function (MountableAction $action, Album $record) {
                $copies = $record->collectionItems()->count();

                if ($copies > 0) {
                    Notification::make()
                        ->danger()
                        ->title('Suppression impossible')
                        ->body("Cet album est possédé par {$copies} exemplaire(s). S'il existe sous une autre fiche, utilisez « Fusionner dans… » ; s'il est fictif, « Supprimer avec ses exemplaires ».")
                        ->persistent()
                        ->send();

                    $action->cancel();
                }
            });
    }

    // A fictitious album has no real counterpart to merge into: its copies go with it
    public static function configureDeleteWithCopiesAction(MountableAction $action): MountableAction
    {
        return $action
            ->label('Supprimer avec ses exemplaires')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (Album $record) => $record->collectionItems()->exists())
            ->modalHeading(fn (Album $record) => "Supprimer « {$record->title} » et ses exemplaires")
            ->modalDescription(fn (Album $record) => sprintf(
                'Réservé aux albums fictifs : %d exemplaire(s) de %d utilisateur(s) seront supprimés définitivement, ainsi que les éditions. Si l\'album existe vraiment, utilisez plutôt « Fusionner dans… ».',
                $record->collectionItems()->count(),
                $record->collectionItems()->distinct()->count('user_id'),
            ))
            ->modalSubmitActionLabel('Supprimer définitivement')
            // Users lose data: the admin types the title again, a click is not enough
            ->form(fn (Album $record) => [
                Forms\Components\TextInput::make('title_confirmation')
                    ->label("Retapez le titre de l'album pour confirmer")
                    ->required()
                    ->in([$record->title])
                    ->validationMessages(['in' => 'Le titre ne correspond pas.']),
            ])
            ->action(function (MountableAction $action, Album $record, $livewire) {
                DB::transaction(function () use ($record) {
                    $record->collectionItems()->delete();
                    $record->delete();
                });

                Notification::make()->success()->title("Album « {$record->title} » supprimé avec ses exemplaires")->send();

                if ($livewire instanceof EditRecord) {
                    $action->redirect(static::getUrl('index'));
                }
            });
    }

    public static function configureMergeAction(MountableAction $action): MountableAction
    {
        return $action
            ->label('Fusionner dans…')
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('warning')
            ->modalHeading(fn (Album $record) => "Fusionner « {$record->title} »")
            ->modalDescription(fn (Album $record) => sprintf(
                '%d exemplaire(s) et %d édition(s) seront déplacés vers l\'album choisi, qui garde ses propres valeurs. Cet album sera ensuite supprimé.',
                $record->collectionItems()->count(),
                $record->releases()->count(),
            ))
            ->modalSubmitActionLabel('Fusionner')
            ->form(fn (Album $record) => [
                Forms\Components\Select::make('target_id')
                    ->label('Album cible')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Album::with('artists')
                        ->whereKeyNot($record->id)
                        ->where(fn ($query) => $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhereHas('artists', fn ($artists) => $artists->where('name', 'like', "%{$search}%")))
                        ->orderBy('title')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Album $album) => [$album->id => ReleaseResource::albumOptionLabel($album)]))
                    ->getOptionLabelUsing(fn ($value) => ($album = Album::with('artists')->find($value))
                        ? ReleaseResource::albumOptionLabel($album)
                        : null)
                    ->required()
                    ->exists(Album::class, 'id')
                    ->notIn([$record->id]),
            ])
            ->action(function (MountableAction $action, Album $record, array $data, $livewire) {
                $target = app(MergeAlbum::class)->handle($record, Album::findOrFail($data['target_id']));

                Notification::make()->success()->title("Album fusionné dans « {$target->title} »")->send();

                // The edited album no longer exists: show the one that took its place
                if ($livewire instanceof EditRecord) {
                    $action->redirect(static::getUrl('edit', ['record' => $target]));
                }
            });
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ArtistsRelationManager::class,
            RelationManagers\ReleasesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlbums::route('/'),
            'create' => Pages\CreateAlbum::route('/create'),
            'edit' => Pages\EditAlbum::route('/{record}/edit'),
        ];
    }
}
