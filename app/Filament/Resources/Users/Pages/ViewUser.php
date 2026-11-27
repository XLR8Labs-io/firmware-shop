<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\LedgerEntryType;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Wallet\InsufficientFunds;
use App\Services\Wallet\WalletService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Every change is a ledger entry recording the admin and their reason.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addMoney')
                ->label('Add money')
                ->icon(Heroicon::OutlinedPlus)
                ->schema(self::moneyFields())
                ->action(function (array $data, WalletService $wallet): void {
                    $wallet->credit($this->customer(), $data['amount'], LedgerEntryType::AdminCredit, 'admin:'.Str::uuid(), $data['note'], $this->admin());

                    Notification::make()->success()->title("Added \${$data['amount']}")->send();
                }),
            Action::make('removeMoney')
                ->label('Remove money')
                ->icon(Heroicon::OutlinedMinus)
                ->color('danger')
                ->schema(self::moneyFields())
                ->action(function (array $data, WalletService $wallet, Action $action): void {
                    try {
                        $wallet->debit($this->customer(), $data['amount'], LedgerEntryType::AdminDebit, 'admin:'.Str::uuid(), $data['note'], $this->admin());
                    } catch (InsufficientFunds $exception) {
                        Notification::make()->danger()->title("The balance is only \${$exception->balance}")->send();
                        $action->halt();
                    }

                    Notification::make()->success()->title("Removed \${$data['amount']}")->send();
                }),
            Action::make('ban')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn (): bool => ! $this->customer()->is_banned && ! $this->customer()->is_admin)
                ->modalDescription('The customer can still sign in and use their wallet, but cannot buy or download files. Download links they already have stop working.')
                ->schema([
                    Textarea::make('reason')
                        ->helperText('The customer sees this reason.')
                        ->required()
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $this->customer()->ban($data['reason']);

                    Notification::make()->success()->title('Customer banned')->send();
                }),
            Action::make('unban')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (): bool => $this->customer()->is_banned)
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->customer()->unban();

                    Notification::make()->success()->title('Ban lifted')->send();
                }),
        ];
    }

    /**
     * @return array<int, TextInput|Textarea>
     */
    private static function moneyFields(): array
    {
        return [
            TextInput::make('amount')
                ->required()
                ->prefix('$')
                ->regex('/^\d{1,16}(\.\d{1,2})?$/')
                ->rules(['numeric', 'gt:0'])
                ->validationMessages(['regex' => 'Enter an amount in dollars with up to two decimals, such as 10 or 2.50.']),
            Textarea::make('note')
                ->label('Reason')
                ->helperText('Saved with the entry. Only admins see it.')
                ->required()
                ->maxLength(500),
        ];
    }

    private function customer(): User
    {
        $record = $this->getRecord();
        abort_unless($record instanceof User, 404);

        return $record;
    }

    private function admin(): User
    {
        $admin = auth()->user();
        abort_unless($admin instanceof User, 403);

        return $admin;
    }
}
