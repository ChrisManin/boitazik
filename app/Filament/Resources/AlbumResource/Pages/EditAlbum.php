<?php

namespace App\Filament\Resources\AlbumResource\Pages;

use App\Filament\Resources\AlbumResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAlbum extends EditRecord
{
    protected static string $resource = AlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AlbumResource::configureMergeAction(Actions\Action::make('merge')),
            AlbumResource::configureDeleteAction(Actions\DeleteAction::make()),
            AlbumResource::configureDeleteWithCopiesAction(Actions\Action::make('deleteWithCopies')),
        ];
    }
}
