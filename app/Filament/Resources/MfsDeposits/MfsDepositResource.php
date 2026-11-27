<?php

namespace App\Filament\Resources\MfsDeposits;

use App\Enums\DepositStatus;
use App\Enums\MfsMethod;
use App\Filament\Resources\MfsDeposits\Pages\ManageMfsDeposits;
use App\Models\MfsDeposit;
use App\Models\User;
use App\Services\Deposits\DepositNotAllowed;
use App\Services\Deposits\MfsDepositService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * The queue of bKash and Nagad deposits. Check each TrxID in the bKash or Nagad app
 * before approving.
 */
class MfsDepositResource extends Resource
{
    protected static ?string $model = MfsDeposit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'bKash and Nagad';

    protected static ?string $modelLabel = 'deposit';

    protected static ?int $navigationSort = 7;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = MfsDeposit::query()->where('status', DepositStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'reviewedBy']))
            ->columns([
                TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
                TextColumn::make('user.email')->label('Customer')->searchable(),
                TextColumn::make('method')->badge(),
                TextColumn::make('amount_bdt')->label('BDT')->numeric(2),
                TextColumn::make('amount_usd')->label('USD')->money('USD'),
                TextColumn::make('sender_number')->label('From')->searchable(),
                TextColumn::make('trx_id')->label('TrxID')->searchable()->copyable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('reviewedBy.name')->label('Checked by')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(DepositStatus::class)->default(DepositStatus::Pending->value),
                SelectFilter::make('method')->options(MfsMethod::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (MfsDeposit $record): bool => $record->status === DepositStatus::Pending)
                    ->modalDescription(fn (MfsDeposit $record): string => "Check TrxID {$record->trx_id} in the {$record->method->getLabel()} app first. This adds \${$record->amount_usd} to the customer's wallet.")
                    ->schema([
                        TextInput::make('amount_bdt')
                            ->label('Amount received (BDT)')
                            ->helperText(fn (MfsDeposit $record): string => "Change it only if the customer sent a different amount. Dollars are recalculated at the rate saved with the deposit (1 USD = {$record->rate_bdt_per_usd} BDT).")
                            ->default(fn (MfsDeposit $record): string => $record->amount_bdt)
                            ->required()
                            ->regex('/^\d{1,9}(\.\d{1,2})?$/'),
                    ])
                    ->action(function (MfsDeposit $record, array $data, MfsDepositService $deposits, Action $action): void {
                        $changed = bccomp($data['amount_bdt'], $record->amount_bdt, 2) !== 0;

                        try {
                            $deposit = $deposits->approve($record, self::admin(), $changed ? $data['amount_bdt'] : null);
                        } catch (DepositNotAllowed|InvalidArgumentException $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();
                            $action->halt();

                            return;
                        }

                        Notification::make()->success()->title("Added \${$deposit->amount_usd} to the wallet")->send();
                    }),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (MfsDeposit $record): bool => $record->status === DepositStatus::Pending)
                    ->schema([
                        Textarea::make('reason')
                            ->helperText('The customer sees this reason by email and on their Add funds page.')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (MfsDeposit $record, array $data, MfsDepositService $deposits, Action $action): void {
                        try {
                            $deposits->reject($record, self::admin(), $data['reason']);
                        } catch (DepositNotAllowed $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();
                            $action->halt();

                            return;
                        }

                        Notification::make()->success()->title('Deposit rejected')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMfsDeposits::route('/'),
        ];
    }

    private static function admin(): User
    {
        $admin = auth()->user();
        abort_unless($admin instanceof User, 403);

        return $admin;
    }
}
