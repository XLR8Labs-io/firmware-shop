<?php

namespace App\Filament\Resources\CryptoTransfers;

use App\Enums\CryptoNetwork;
use App\Enums\LedgerEntryType;
use App\Filament\Resources\CryptoTransfers\Pages\ManageCryptoTransfers;
use App\Models\CryptoTransfer;
use App\Models\User;
use App\Services\Wallet\WalletService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Every incoming USDT transfer. Unmatched ones (wrong amount, late, no invoice) wait here
 * for an admin to assign them to the right customer.
 */
class CryptoTransferResource extends Resource
{
    protected static ?string $model = CryptoTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $navigationLabel = 'Crypto transfers';

    protected static ?int $navigationSort = 8;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $unmatched = self::unmatched(CryptoTransfer::query())->count();

        return $unmatched > 0 ? (string) $unmatched : null;
    }

    /**
     * @param  Builder<CryptoTransfer>  $query
     * @return Builder<CryptoTransfer>
     */
    private static function unmatched(Builder $query): Builder
    {
        return $query->whereNull('crypto_invoice_id')->whereNull('assigned_user_id');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['invoice.user', 'assignedUser']))
            ->columns([
                TextColumn::make('block_time')->label('Received')->dateTime()->sortable(),
                TextColumn::make('network')->badge(),
                TextColumn::make('amount')->label('USDT')->numeric(6),
                TextColumn::make('tx_hash')->label('Hash')->limit(18)->copyable()->searchable(),
                TextColumn::make('from_address')->label('From')->limit(14)->copyable(),
                TextColumn::make('credited_to')
                    ->label('Credited to')
                    ->state(fn (CryptoTransfer $record): ?string => $record->invoice?->user->email ?? $record->assignedUser?->email),
            ])
            ->defaultSort('block_time', 'desc')
            ->filters([
                TernaryFilter::make('unmatched')
                    ->label('Waiting for review')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query): Builder => self::unmatched($query),
                        false: fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query->whereNotNull('crypto_invoice_id')->orWhereNotNull('assigned_user_id')),
                    ),
                SelectFilter::make('network')->options(CryptoNetwork::class),
            ])
            ->recordActions([
                Action::make('assign')
                    ->label('Assign to customer')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (CryptoTransfer $record): bool => $record->crypto_invoice_id === null && $record->assigned_user_id === null)
                    ->schema([
                        Select::make('user_id')
                            ->label('Customer')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => User::query()->where('email', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%')->limit(20)->pluck('email', 'id')->all())
                            ->getOptionLabelUsing(fn (mixed $value): ?string => User::query()->whereKey($value)->value('email'))
                            ->required(),
                        TextInput::make('amount')
                            ->label('Amount to add ($)')
                            ->default(fn (CryptoTransfer $record): string => bcadd($record->amount, '0', 2))
                            ->required()
                            ->regex('/^\d{1,16}(\.\d{1,2})?$/')
                            ->rules(['numeric', 'gt:0']),
                        Textarea::make('note')->label('Reason')->required()->maxLength(500),
                    ])
                    ->action(function (CryptoTransfer $record, array $data, WalletService $wallet): void {
                        $admin = auth()->user();
                        $customer = User::query()->whereKey($data['user_id'])->firstOrFail();
                        abort_unless($admin instanceof User, 403);

                        DB::transaction(function () use ($record, $data, $wallet, $admin, $customer): void {
                            $wallet->credit($customer, $data['amount'], LedgerEntryType::DepositCrypto, "{$record->network->value}:{$record->tx_hash}", $data['note'], $admin);
                            $record->update(['assigned_user_id' => $customer->id, 'assigned_by' => $admin->id, 'admin_note' => $data['note']]);
                        });

                        Notification::make()->success()->title("Added \${$data['amount']} to {$customer->email}")->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCryptoTransfers::route('/'),
        ];
    }
}
