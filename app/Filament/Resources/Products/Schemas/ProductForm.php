<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductStatus;
use App\Models\Folder;
use App\Services\Drive\DriveClient;
use App\Services\Drive\DriveException;
use App\Services\Drive\DriveId;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                        static::driveLinkInput()
                            ->columnSpanFull(),
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

    public static function priceInput(string $name = 'price'): TextInput
    {
        return TextInput::make($name)
            ->helperText('0.00 makes the file free (customers still need to log in).')
            ->required()
            ->prefix('$')
            ->default('0.00')
            ->regex('/^\d{1,16}(\.\d{1,2})?$/')
            ->validationMessages(['regex' => 'Enter an amount in dollars with up to two decimals, such as 40 or 12.50.']);
    }

    /**
     * Paste a share link and the file details below fill in from Google Drive.
     */
    public static function driveLinkInput(): TextInput
    {
        return TextInput::make('drive_link')
            ->label('Drive link or file ID')
            ->helperText('Paste the file\'s share link and the details below fill in.')
            ->dehydrated(false)
            ->live(onBlur: true)
            ->afterStateUpdated(function (?string $state, Set $set, Get $get, DriveClient $drive): void {
                if (blank($state)) {
                    return;
                }

                $fileId = DriveId::fromInput($state);

                if ($fileId === null) {
                    Notification::make()->danger()->title('That is not a Google Drive link or file ID.')->send();

                    return;
                }

                try {
                    $file = $drive->metadata($fileId);
                } catch (DriveException $exception) {
                    Notification::make()->danger()->title('Could not read the file from Google Drive')->body($exception->getMessage())->send();

                    return;
                }

                if (! $file->isDownloadable()) {
                    Notification::make()->danger()->title('That is a folder or a Google Docs file, not a file people can download.')->send();

                    return;
                }

                $set('drive_file_id', $file->id);
                $set('file_name', $file->name);
                $set('file_size', $file->size);
                $set('md5', $file->md5);
                $set('mime_type', $file->mimeType);

                if (blank($get('title'))) {
                    $set('title', $file->title());
                }
            });
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
