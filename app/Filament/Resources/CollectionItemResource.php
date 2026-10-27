<?php

namespace App\Filament\Resources;

use App\Enums\ItemCondition;
use App\Filament\Resources\CollectionItemResource\Pages;
use App\Models\Album;
use App\Models\CollectionItem;
use App\Models\Release;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class CollectionItemResource extends Resource
{
    protected static ?string $model = CollectionItem::class;

    protected static ?string $modelLabel = 'exemplaire';

    protected static ?string $pluralModelLabel = 'exemplaires';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 4;

    // CollectionItemPolicy guards the front, where users only reach their own items.
    // The panel itself is admin-only (User::canAccessPanel), and admins manage every item.
    protected static bool $shouldSkipAuthorization = true;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Utilisateur')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('album_id')
                    ->label('Album')
                    ->relationship('album', 'title', fn ($query) => $query->with('artists'))
                    ->getOptionLabelFromRecordUsing(fn (Album $album) => ReleaseResource::albumOptionLabel($album))
                    ->searchable()
                    ->required()
                    ->live()
                    // The previous album's release no longer fits
                    ->afterStateUpdated(fn (Set $set) => $set('release_id', null)),
                Forms\Components\Select::make('release_id')
                    ->label('Édition')
                    ->relationship(
                        'release',
                        'label',
                        fn (Builder $query, Get $get) => $query->where('album_id', $get('album_id')),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Release $release) => static::releaseOptionLabel($release))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    // The options are filtered client-side only: check the album on save too
                    ->rule(fn (Get $get) => Rule::exists('releases', 'id')->where('album_id', $get('album_id'))),
                Forms\Components\Select::make('condition')
                    ->label('État')
                    ->options(ItemCondition::class)
                    ->enum(ItemCondition::class),
                Forms\Components\DatePicker::make('acquired_at')
                    ->label("Date d'acquisition")
                    ->beforeOrEqual('today'),
                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * "Vinyle — Harvest Records, 1972 (GB)": several releases of an album often share a format.
     */
    public static function releaseOptionLabel(Release $release): string
    {
        $details = collect([$release->label, $release->release_year])->filter()->join(', ');

        return $release->format->getLabel()
            .($details === '' ? '' : " — {$details}")
            .($release->country === null ? '' : " ({$release->country})");
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'album.artists', 'release']))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('album.title')
                    ->label('Album')
                    ->description(fn (CollectionItem $record) => $record->album->artists->pluck('name')->join(', '))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('release.format')
                    ->label('Format')
                    ->badge(),
                Tables\Columns\TextColumn::make('condition')
                    ->label('État'),
                Tables\Columns\TextColumn::make('acquired_at')
                    ->label("Date d'acquisition")
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user')
                    ->label('Utilisateur')
                    ->relationship('user', 'name')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        // No create page: users add items from the front
        return [
            'index' => Pages\ListCollectionItems::route('/'),
            'edit' => Pages\EditCollectionItem::route('/{record}/edit'),
        ];
    }
}
