<?php

namespace App\Filament\Resources\AlbumResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ArtistsRelationManager extends RelationManager
{
    protected static string $relationship = 'artists';

    protected static ?string $title = 'Artistes';

    protected static ?string $modelLabel = 'artiste';

    // Only the credit position is edited here: artist details live in ArtistResource
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->positionField(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('position')
                    ->label('Position'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom'),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->recordSelectSearchColumns(['name', 'mbid'])
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()->label('Artiste'),
                        $this->positionField(),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
            ]);
    }

    private function positionField(): Forms\Components\TextInput
    {
        // Credit order for collaborations (0 = main artist); a new artist goes after the others
        return Forms\Components\TextInput::make('position')
            ->label('Position')
            ->helperText('0 = artiste principal')
            ->integer()
            ->minValue(0)
            ->maxValue(65535)
            ->default(fn () => ($this->getOwnerRecord()->artists()->max('position') ?? -1) + 1)
            ->required();
    }
}
