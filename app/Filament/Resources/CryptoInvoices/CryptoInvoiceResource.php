<?php

namespace App\Filament\Resources\CryptoInvoices;

use App\Enums\CryptoInvoiceStatus;
use App\Enums\CryptoNetwork;
use App\Filament\Resources\CryptoInvoices\Pages\ManageCryptoInvoices;
use App\Models\CryptoInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CryptoInvoiceResource extends Resource
{
    protected static ?string $model = CryptoInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'Crypto invoices';

    protected static ?int $navigationSort = 9;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
                TextColumn::make('user.email')->label('Customer')->searchable(),
                TextColumn::make('network')->badge(),
                TextColumn::make('pay_amount')->label('Must arrive (USDT)')->numeric(2),
                TextColumn::make('status')->badge(),
                TextColumn::make('tx_hash')->label('Hash')->limit(18)->copyable(),
                TextColumn::make('paid_at')->label('Paid')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(CryptoInvoiceStatus::class),
                SelectFilter::make('network')->options(CryptoNetwork::class),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCryptoInvoices::route('/'),
        ];
    }
}
