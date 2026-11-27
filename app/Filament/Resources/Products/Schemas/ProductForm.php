<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductStatus;
use App\Models\Folder;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        static::folderSelect(),
                        TextInput::make('slug')
                            ->helperText('Used in the web address. Leave empty to create it from the title.')
                            ->required(fn (string $operation): bool => $operation === 'edit')
                            ->alphaDash()
                            ->maxLength(150)
                            ->unique(ignoreRecord: true),
                        Textarea::make('model_names')
                            ->label('Models and codenames')
                            ->helperText('Model names, model numbers and codenames people search for, separated by commas.')
                            ->rows(2)
                            ->maxLength(2000),
                        Textarea::make('short_description')
                            ->helperText('Shown on cards and in lists.')
                            ->rows(2)
                            ->maxLength(500),
                        MarkdownEditor::make('description'),
                    ]),
                Section::make('Sale')
                    ->schema([
                        static::priceInput(),
                        Select::make('status')
                            ->options(ProductStatus::class)
                            ->default(ProductStatus::Draft)
                            ->required(),
                        Toggle::make('is_featured')
                            ->label('Featured on the home page'),
                        TextInput::make('file_password')
                            ->label('File password')
                            ->helperText('Shown only to people who can download the file.')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('File on Google Drive')
                    ->schema([
                        TextInput::make('drive_file_id')
                            ->label('Drive file ID')
                            ->required()
                            ->maxLength(255)
                            ->regex('/^[A-Za-z0-9_-]+$/')
                            ->unique(ignoreRecord: true),
                        TextInput::make('mirror_drive_file_id')
                            ->label('Mirror file ID')
                            ->helperText('Used when Google limits downloads of the main file.')
                            ->maxLength(255)
                            ->regex('/^[A-Za-z0-9_-]+$/'),
                        TextInput::make('file_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('file_size')
                            ->label('File size in bytes')
                            ->required()
                            ->integer()
                            ->minValue(0),
                        TextInput::make('md5')
                            ->label('MD5')
                            ->regex('/^[a-fA-F0-9]{32}$/'),
                        TextInput::make('mime_type')
                            ->label('MIME type')
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }

    public static function priceInput(): TextInput
    {
        return TextInput::make('price')
            ->helperText('0.00 makes the file free (customers still need to log in).')
            ->required()
            ->prefix('$')
            ->default('0.00')
            ->regex('/^\d{1,16}(\.\d{1,2})?$/')
            ->validationMessages(['regex' => 'Enter an amount in dollars with up to two decimals, such as 40 or 12.50.']);
    }

    public static function folderSelect(): Select
    {
        return Select::make('folder_id')
            ->label('Folder')
            ->relationship(
                name: 'folder',
                titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query): Builder => $query->with('parent'),
            )
            ->getOptionLabelFromRecordUsing(fn (Folder $folder): string => $folder->parent === null
                ? $folder->name
                : "{$folder->parent->name} / {$folder->name}")
            ->searchable()
            ->required();
    }
}
