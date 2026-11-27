<?php

namespace App\Filament\Resources\Folders\Schemas;

use App\Models\Folder;
use App\Services\FolderTree;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class FolderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('parent_id')
                            ->label('Inside folder')
                            ->placeholder('Top level, such as a brand')
                            ->relationship(
                                name: 'parent',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, ?Folder $record): Builder => $record === null
                                    ? $query
                                    : $query->whereKeyNot(app(FolderTree::class)->descendantIds($record->id)),
                            )
                            ->searchable()
                            ->rules([
                                fn (?Folder $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    if ($record !== null && filled($value) && in_array((int) $value, app(FolderTree::class)->descendantIds($record->id), true)) {
                                        $fail('A folder cannot be moved inside itself or one of its own folders.');
                                    }
                                },
                            ]),
                        TextInput::make('slug')
                            ->helperText('Used in the web address. Leave empty to create it from the name.')
                            ->required(fn (string $operation): bool => $operation === 'edit')
                            ->alphaDash()
                            ->maxLength(150)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000),
                    ]),
                Section::make('Display')
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Image')
                            ->image()
                            ->disk('public')
                            ->directory('folders')
                            ->maxSize(2048),
                        TextInput::make('sort_order')
                            ->label('Sort order')
                            ->helperText('Lower numbers come first among folders in the same place.')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('is_visible')
                            ->label('Visible to customers')
                            ->helperText('Hiding a folder also hides every folder and file inside it.')
                            ->default(true),
                        TextInput::make('drive_folder_id')
                            ->label('Google Drive folder ID')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?Folder $record): bool => filled($record?->drive_folder_id)),
                    ]),
            ]);
    }
}
