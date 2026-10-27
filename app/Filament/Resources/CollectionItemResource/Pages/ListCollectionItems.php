<?php

namespace App\Filament\Resources\CollectionItemResource\Pages;

use App\Filament\Resources\CollectionItemResource;
use Filament\Resources\Pages\ListRecords;

class ListCollectionItems extends ListRecords
{
    protected static string $resource = CollectionItemResource::class;
}
