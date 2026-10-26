<?php

namespace App\Filament\Resources;

use App\Enums\AlbumType;
use App\Filament\Resources\AlbumResource\Pages;
use App\Filament\Resources\AlbumResource\RelationManagers;
use App\Models\Album;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
            ]);
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
