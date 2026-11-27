<?php

namespace App\Filament\Resources\DriveImports;

use App\Enums\DriveImportStatus;
use App\Filament\Resources\DriveImports\Pages\ManageDriveImports;
use App\Models\DriveImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The history of Drive imports. Imports are started from the page's header action
 * and are never edited afterwards.
 */
class DriveImportResource extends Resource
{
    protected static ?string $model = DriveImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowDown;

    protected static ?string $navigationLabel = 'Drive imports';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('parentFolder'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('drive_folder_id')
                    ->label('Drive folder')
                    ->limit(20)
                    ->copyable(),
                TextColumn::make('parentFolder.name')
                    ->label('Into')
                    ->placeholder('Top level'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('folders_created')
                    ->label('New folders')
                    ->numeric(),
                TextColumn::make('products_created')
                    ->label('New files')
                    ->numeric(),
                TextColumn::make('products_skipped')
                    ->label('Already imported')
                    ->numeric(),
                TextColumn::make('files_skipped')
                    ->label('Skipped')
                    ->tooltip('Google Docs, shortcuts, or files with no site folder to go into.')
                    ->numeric(),
                TextColumn::make('error')
                    ->limit(60)
                    ->tooltip(fn (DriveImport $record): ?string => $record->error),
            ])
            ->defaultSort('created_at', 'desc')
            // Refresh only while an import is waiting or running.
            ->poll(fn (): ?string => DriveImport::query()
                ->whereIn('status', [DriveImportStatus::Queued, DriveImportStatus::Running])
                ->exists() ? '5s' : null);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDriveImports::route('/'),
        ];
    }
}
