<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArtistResource\Pages;
use App\Models\Artist;
use Filament\Forms;
use Filament\Forms\Form;
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
            ]);
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
