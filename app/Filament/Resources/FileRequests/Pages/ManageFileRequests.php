<?php

namespace App\Filament\Resources\FileRequests\Pages;

use App\Filament\Resources\FileRequests\FileRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFileRequests extends ManageRecords
{
    protected static string $resource = FileRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
