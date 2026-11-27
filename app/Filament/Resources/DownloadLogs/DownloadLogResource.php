<?php

namespace App\Filament\Resources\DownloadLogs;

use App\Enums\DownloadStatus;
use App\Filament\Resources\DownloadLogs\Pages\ManageDownloadLogs;
use App\Models\DownloadLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DownloadLogResource extends Resource
{
    protected static ?string $model = DownloadLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Downloads';

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'product', 'downloadToken']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('product.title')
                    ->label('File')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('ip')
                    ->label('IP'),
                TextColumn::make('link_ips')
                    ->label('IPs on link')
                    ->state(fn (DownloadLog $record): int => count($record->downloadToken->ip_addresses ?? []))
                    ->color(fn (int $state): ?string => $state > 1 ? 'warning' : null),
                TextColumn::make('drive_file_used')
                    ->label('Drive file'),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(DownloadStatus::class),
                SelectFilter::make('product_id')
                    ->label('File')
                    ->relationship('product', 'title')
                    ->searchable(),
                SelectFilter::make('user_id')
                    ->label('Customer')
                    ->relationship('user', 'email')
                    ->searchable(),
                Filter::make('many_ips')
                    ->label('Link used from more than one IP')
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'downloadToken',
                        fn (Builder $token): Builder => $token->whereRaw('JSON_LENGTH(ip_addresses) > 1'),
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDownloadLogs::route('/'),
        ];
    }
}
