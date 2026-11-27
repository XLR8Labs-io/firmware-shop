<?php

namespace App\Filament\Resources\DriveImports\Pages;

use App\Filament\Resources\DriveImports\DriveImportResource;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Jobs\ImportDriveFolder;
use App\Models\DriveImport;
use App\Services\Drive\DriveId;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageDriveImports extends ManageRecords
{
    protected static string $resource = DriveImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import from Drive')
                ->icon(Heroicon::OutlinedCloudArrowDown)
                ->modalDescription('Brings the folders and files inside a Google Drive folder into the catalogue. New files arrive as drafts. Anything imported before is left as it is.')
                ->schema([
                    TextInput::make('drive_folder')
                        ->label('Drive folder link or ID')
                        ->required()
                        ->rules([
                            fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (! is_string($value) || DriveId::fromInput($value) === null) {
                                    $fail('Paste a Google Drive folder link or folder ID.');
                                }
                            },
                        ]),
                    Select::make('parent_folder_id')
                        ->label('Import into')
                        ->placeholder('Top level')
                        ->helperText('At the top level, files sitting directly in the Drive folder are skipped, because every file needs a folder.')
                        ->relationship('parentFolder', 'name')
                        ->searchable(),
                    ProductForm::priceInput('default_price')
                        ->label('Price for new files'),
                ])
                ->action(function (array $data): void {
                    $import = DriveImport::query()->create([
                        'user_id' => auth()->id(),
                        'drive_folder_id' => DriveId::fromInput($data['drive_folder']),
                        'parent_folder_id' => $data['parent_folder_id'] ?? null,
                        'default_price' => $data['default_price'],
                    ]);

                    ImportDriveFolder::dispatch($import);

                    Notification::make()
                        ->success()
                        ->title('Import started')
                        ->body('Progress shows in the table. Large folders can take a few minutes.')
                        ->send();
                }),
        ];
    }
}
