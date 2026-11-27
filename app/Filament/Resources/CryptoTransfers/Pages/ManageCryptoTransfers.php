<?php

namespace App\Filament\Resources\CryptoTransfers\Pages;

use App\Filament\Resources\CryptoTransfers\CryptoTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCryptoTransfers extends ManageRecords
{
    protected static string $resource = CryptoTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
