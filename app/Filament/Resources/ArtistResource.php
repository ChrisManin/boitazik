<?php

namespace App\Filament\Resources;

use App\Actions\MergeArtist;
use App\Filament\Resources\ArtistResource\Pages;
use App\Models\Artist;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ArtistResource extends Resource
{
    protected static ?string $model = Artist::class;

    protected static ?string $modelLabel = 'artiste';

    protected static ?string $navigationIcon = 'heroicon-o-microphone';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(191),
                Forms\Components\TextInput::make('sort_name')
                    ->label('Nom de tri')
                    ->maxLength(191),
                // A new or changed MBID queues a discography import (see Artist::booted)
                Forms\Components\TextInput::make('mbid')
                    ->label('MBID')
                    ->uuid()
                    ->unique(Artist::class, 'mbid', ignoreRecord: true),
                Forms\Components\TextInput::make('spotify_id')
                    ->label('ID Spotify')
                    ->maxLength(191),
                Forms\Components\TextInput::make('country')
                    ->label('Pays (code ISO)')
                    ->length(2)
                    // Same case as the MusicBrainz imports
                    ->dehydrateStateUsing(fn (?string $state) => $state === null ? null : strtoupper($state)),
                Forms\Components\TextInput::make('image_url')
                    ->label('URL de l\'image')
                    ->url()
                    ->maxLength(191),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_name')
                    ->label('Nom de tri')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('country')
                    ->label('Pays'),
                Tables\Columns\TextColumn::make('mbid')
                    ->label('MBID')
                    ->searchable(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                static::configureMergeAction(Tables\Actions\Action::make('merge')),
                static::configureDeleteAction(Tables\Actions\DeleteAction::make()),
            ]);
    }

    // Shared by the list and the edit page, whose action classes differ
    public static function configureDeleteAction(MountableAction $action): MountableAction
    {
        // The pivot rows cascade: the albums stay in the catalog
        return $action->modalDescription(fn (Artist $record) => sprintf(
            'Cet artiste sera retiré de %d album(s), qui seront conservés.',
            $record->albums()->count(),
        ));
    }

    public static function configureMergeAction(MountableAction $action): MountableAction
    {
        return $action
            ->label('Fusionner dans…')
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('warning')
            ->modalHeading(fn (Artist $record) => "Fusionner « {$record->name} »")
            ->modalDescription(fn (Artist $record) => sprintf(
                'Ses %d album(s) seront rattachés à l\'artiste choisi, qui garde ses propres valeurs. Cet artiste sera ensuite supprimé.',
                $record->albums()->count(),
            ))
            ->modalSubmitActionLabel('Fusionner')
            ->form(fn (Artist $record) => [
                Forms\Components\Select::make('target_id')
                    ->label('Artiste cible')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Artist::whereKeyNot($record->id)
                        ->where(fn ($query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('mbid', $search))
                        ->orderBy('name')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Artist $artist) => [$artist->id => static::artistOptionLabel($artist)]))
                    ->getOptionLabelUsing(fn ($value) => ($artist = Artist::find($value))
                        ? static::artistOptionLabel($artist)
                        : null)
                    ->required()
                    ->exists(Artist::class, 'id')
                    ->notIn([$record->id]),
            ])
            ->action(function (MountableAction $action, Artist $record, array $data, $livewire) {
                $target = app(MergeArtist::class)->handle($record, Artist::findOrFail($data['target_id']));

                Notification::make()->success()->title("Artiste fusionné dans « {$target->name} »")->send();

                // The edited artist no longer exists: show the one that took its place
                if ($livewire instanceof EditRecord) {
                    $action->redirect(static::getUrl('edit', ['record' => $target]));
                }
            });
    }

    // Duplicates usually share a name: country and MBID tell them apart
    public static function artistOptionLabel(Artist $artist): string
    {
        return $artist->name
            .($artist->country === null ? '' : " ({$artist->country})")
            .($artist->mbid === null ? '' : " — {$artist->mbid}");
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArtists::route('/'),
            'create' => Pages\CreateArtist::route('/create'),
            'edit' => Pages\EditArtist::route('/{record}/edit'),
        ];
    }
}
