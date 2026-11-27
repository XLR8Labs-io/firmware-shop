<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Purchases\PurchaseResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchasesRelationManager extends RelationManager
{
    protected static string $relationship = 'purchases';

    /**
     * Shown on the user's view page, where relation managers are read-only by default;
     * the refund action has to stay available.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('product'))
            ->columns([
                TextColumn::make('product.title')
                    ->label('File')
                    ->limit(60),
                TextColumn::make('price_paid')
                    ->label('Paid')
                    ->money('USD'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('updated_at')
                    ->label('Date')
                    ->dateTime(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                PurchaseResource::refundAction(),
            ]);
    }
}
