<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\LedgerEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The wallet's ledger. Entries are never edited; corrections are new entries.
 */
class LedgerEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgerEntries';

    protected static ?string $title = 'Wallet history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('createdBy'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('amount')
                    ->money('USD'),
                TextColumn::make('note')
                    ->limit(60)
                    ->tooltip(fn (LedgerEntry $record): ?string => $record->note),
                TextColumn::make('createdBy.name')
                    ->label('By admin'),
                TextColumn::make('reference')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc');
    }
}
