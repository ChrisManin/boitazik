<?php

namespace App\Filament\Resources\AlbumResource\RelationManagers;

use App\Filament\Resources\ReleaseResource;
use App\Models\Release;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReleasesRelationManager extends RelationManager
{
    protected static string $relationship = 'releases';

    protected static ?string $title = 'Éditions';

    protected static ?string $modelLabel = 'édition';

    public function form(Form $form): Form
    {
        return $form
            ->schema(ReleaseResource::detailFields());
    }

    public function table(Table $table): Table
    {
        return $table
            // The barcode is often empty: the format always names the release
            ->recordTitle(fn (Release $release) => collect([$release->format->label(), $release->label, $release->barcode])->filter()->join(' · '))
            ->columns(ReleaseResource::detailColumns())
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }
}
