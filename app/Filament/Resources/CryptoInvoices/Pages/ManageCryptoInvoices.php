<?php

namespace App\Filament\Resources\CryptoInvoices\Pages;

use App\Filament\Resources\CryptoInvoices\CryptoInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCryptoInvoices extends ManageRecords
{
    protected static string $resource = CryptoInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
