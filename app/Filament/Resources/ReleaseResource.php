<?php

namespace App\Filament\Resources;

use App\Enums\ReleaseFormat;
use App\Filament\Resources\ReleaseResource\Pages;
use App\Models\Album;
use App\Models\Release;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReleaseResource extends Resource
{
    protected static ?string $model = Release::class;

    protected static ?string $modelLabel = 'édition';

    protected static ?string $pluralModelLabel = 'éditions';

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('album_id')
                    ->label('Album')
                    ->relationship('album', 'title', fn ($query) => $query->with('artists'))
                    // Titles like "Greatest Hits" are shared: artists and year tell albums apart
                    ->getOptionLabelFromRecordUsing(fn (Album $album) => static::albumOptionLabel($album))
                    ->searchable()
                    ->required(),
                ...static::detailFields(),
            ]);
    }

    /**
     * Release fields without the album, shared with the album's releases relation manager.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function detailFields(): array
    {
        return [
            Forms\Components\Select::make('format')
                ->label('Format')
                ->options(ReleaseFormat::class)
                ->enum(ReleaseFormat::class)
                ->required(),
            Forms\Components\TextInput::make('label')
                ->label('Label')
                ->maxLength(191),
            Forms\Components\TextInput::make('catalog_number')
                ->label('Référence catalogue')
                ->maxLength(191),
            Forms\Components\TextInput::make('country')
                ->label('Pays (code ISO)')
                ->length(2)
                // Same case as the MusicBrainz imports
                ->dehydrateStateUsing(fn (?string $state) => $state === null ? null : strtoupper($state)),
            Forms\Components\TextInput::make('release_year')
                ->label('Année de sortie')
                ->integer()
                ->minValue(1)
                ->maxValue(2100),
            Forms\Components\TextInput::make('barcode')
                ->label('Code-barres')
                ->maxLength(191),
            Forms\Components\TextInput::make('mbid')
                ->label('MBID')
                ->uuid()
                ->unique(Release::class, 'mbid', ignoreRecord: true),
        ];
    }

    public static function albumOptionLabel(Album $album): string
    {
        $artists = $album->artists->pluck('name')->join(', ');

        return $album->title
            .($artists === '' ? '' : " — {$artists}")
            .($album->first_release_year === null ? '' : " ({$album->first_release_year})");
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('album.title')
                    ->label('Album')
                    ->searchable()
                    ->sortable(),
                ...static::detailColumns(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    /**
     * Release columns without the album, shared with the album's releases relation manager.
     *
     * @return array<int, Tables\Columns\Column>
     */
    public static function detailColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('format')
                ->label('Format')
                ->badge(),
            Tables\Columns\TextColumn::make('label')
                ->label('Label')
                ->searchable(),
            Tables\Columns\TextColumn::make('catalog_number')
                ->label('Référence')
                ->searchable(),
            Tables\Columns\TextColumn::make('country')
                ->label('Pays'),
            Tables\Columns\TextColumn::make('release_year')
                ->label('Année')
                ->sortable(),
            Tables\Columns\TextColumn::make('barcode')
                ->label('Code-barres')
                ->searchable(),
            Tables\Columns\TextColumn::make('mbid')
                ->label('MBID')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReleases::route('/'),
            'create' => Pages\CreateRelease::route('/create'),
            'edit' => Pages\EditRelease::route('/{record}/edit'),
        ];
    }
}
