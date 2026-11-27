<?php

namespace App\Filament\Resources\FileRequests;

use App\Enums\FileRequestStatus;
use App\Filament\Resources\FileRequests\Pages\ManageFileRequests;
use App\Models\FileRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Files visitors asked for. Replies are shown to signed-in customers on the request page.
 */
class FileRequestResource extends Resource
{
    protected static ?string $model = FileRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'file request';

    protected static ?string $recordTitleAttribute = 'model';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = FileRequest::query()->where('status', FileRequestStatus::Open)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('Asked')->since()->sortable(),
                TextColumn::make('brand')->searchable(),
                TextColumn::make('model')->searchable()->wrap(),
                TextColumn::make('details')->limit(60)->tooltip(fn (FileRequest $record): ?string => $record->details)->toggleable(),
                TextColumn::make('name')->description(fn (FileRequest $record): string => $record->contact)->searchable(['name', 'contact']),
                TextColumn::make('user.email')->label('Account')->placeholder('Guest')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(FileRequestStatus::class)->default(FileRequestStatus::Open->value),
            ])
            ->recordActions([
                Action::make('reply')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->fillForm(fn (FileRequest $record): array => [
                        'status' => $record->status === FileRequestStatus::Open ? FileRequestStatus::Done : $record->status,
                        'admin_reply' => $record->admin_reply,
                    ])
                    ->schema([
                        Select::make('status')
                            ->options(FileRequestStatus::class)
                            ->required(),
                        Textarea::make('admin_reply')
                            ->label('Reply')
                            ->helperText('Customers who were signed in see this on the Request a file page. Guests need to be contacted directly.')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->action(function (FileRequest $record, array $data): void {
                        $record->update([
                            'status' => $data['status'],
                            'admin_reply' => filled($data['admin_reply']) ? $data['admin_reply'] : null,
                        ]);

                        Notification::make()->success()->title('Request updated')->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFileRequests::route('/'),
        ];
    }
}
