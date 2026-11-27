<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\ProductStatus;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Product;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('folder'))
            ->columns([
                TextColumn::make('title')
                    ->searchable(['title', 'model_names'])
                    ->sortable()
                    ->limit(60),
                TextColumn::make('folder.name')
                    ->label('Folder')
                    ->toggleable(),
                TextColumn::make('price')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
                TextColumn::make('download_count')
                    ->label('Downloads')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ProductStatus::class),
                SelectFilter::make('folder_id')
                    ->label('Folder')
                    ->relationship('folder', 'name')
                    ->searchable(),
                TernaryFilter::make('is_featured')
                    ->label('Featured'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::updateAction('publish', 'Publish', Heroicon::OutlinedEye, ['status' => ProductStatus::Published]),
                    self::updateAction('unpublish', 'Unpublish', Heroicon::OutlinedEyeSlash, ['status' => ProductStatus::Hidden]),
                    self::updateAction('feature', 'Feature', Heroicon::OutlinedStar, ['is_featured' => true]),
                    self::updateAction('unfeature', 'Stop featuring', Heroicon::OutlinedNoSymbol, ['is_featured' => false]),
                    BulkAction::make('setPrice')
                        ->label('Set price')
                        ->icon(Heroicon::OutlinedCurrencyDollar)
                        ->schema([ProductForm::priceInput()])
                        ->action(fn (Collection $records, array $data) => self::updateEach($records, ['price' => $data['price']]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('moveToFolder')
                        ->label('Move to folder')
                        ->icon(Heroicon::OutlinedFolderArrowDown)
                        ->schema([ProductForm::folderSelect()])
                        ->action(fn (Collection $records, array $data) => self::updateEach($records, ['folder_id' => $data['folder_id']]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function updateAction(string $name, string $label, Heroicon $icon, array $attributes): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->requiresConfirmation()
            ->action(fn (Collection $records) => self::updateEach($records, $attributes))
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Saves each record so model events run (publishing sets published_at).
     *
     * @param  Collection<int, Product>  $records
     * @param  array<string, mixed>  $attributes
     */
    private static function updateEach(Collection $records, array $attributes): void
    {
        $records->each(fn (Product $product) => $product->update($attributes));
    }
}
