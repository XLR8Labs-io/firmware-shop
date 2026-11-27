<?php

namespace App\Filament\Resources\Purchases;

use App\Enums\PurchaseStatus;
use App\Filament\Resources\Purchases\Pages\ManagePurchases;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Wallet\PurchaseNotAllowed;
use App\Services\Wallet\PurchaseService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'product']))
            ->columns([
                TextColumn::make('updated_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('product.title')
                    ->label('File')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('price_paid')
                    ->label('Paid')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('refunded_at')
                    ->label('Refunded')
                    ->dateTime(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(PurchaseStatus::class),
            ])
            ->recordActions([
                self::refundAction(),
            ]);
    }

    /**
     * Returns the price paid to the customer's wallet and removes their access.
     */
    public static function refundAction(): Action
    {
        return Action::make('refund')
            ->label('Refund')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (Purchase $record): string => "Returns \${$record->price_paid} to the customer's wallet and removes their access to the file.")
            ->schema([
                Textarea::make('note')
                    ->label('Reason')
                    ->helperText('Saved with the refund. Only admins see it.')
                    ->maxLength(500),
            ])
            ->visible(fn (Purchase $record): bool => $record->status === PurchaseStatus::Active)
            ->action(function (Purchase $record, array $data, PurchaseService $purchases, Action $action): void {
                $admin = auth()->user();
                abort_unless($admin instanceof User, 403);

                try {
                    $purchases->refund($record, $admin, $data['note'] ?? null);
                } catch (PurchaseNotAllowed $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                    $action->halt();
                }

                Notification::make()->success()->title("Refunded \${$record->price_paid}")->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePurchases::route('/'),
        ];
    }
}
