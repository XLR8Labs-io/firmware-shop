<?php

namespace App\Filament\Resources\Folders\Tables;

use App\Models\Folder;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FoldersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('parent')
                // Deleted files still belong to the folder, so they count too.
                ->withCount(['children', 'products' => fn (Builder $query): Builder => $query->withoutGlobalScope(SoftDeletingScope::class)]))
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk('public')
                    ->square(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.name')
                    ->label('Inside')
                    ->placeholder('Top level'),
                TextColumn::make('children_count')
                    ->label('Folders')
                    ->numeric(),
                TextColumn::make('products_count')
                    ->label('Files')
                    ->numeric(),
                IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                TernaryFilter::make('top_level')
                    ->label('Top level')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('parent_id'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('parent_id'),
                    ),
                SelectFilter::make('parent_id')
                    ->label('Inside folder')
                    ->relationship('parent', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                // The database refuses to delete a folder that still holds anything.
                DeleteAction::make()
                    ->disabled(fn (Folder $record): bool => $record->children_count > 0 || $record->products_count > 0)
                    ->tooltip(fn (Folder $record): ?string => $record->children_count > 0 || $record->products_count > 0
                        ? 'Move or delete the folders and files inside it first.'
                        : null),
            ]);
    }
}
