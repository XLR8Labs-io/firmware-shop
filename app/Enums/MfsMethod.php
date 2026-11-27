<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Bangladeshi mobile wallets customers pay into.
 */
enum MfsMethod: string implements HasLabel
{
    case Bkash = 'bkash';

    case Nagad = 'nagad';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
        };
    }

    public function ledgerType(): LedgerEntryType
    {
        return match ($this) {
            self::Bkash => LedgerEntryType::DepositBkash,
            self::Nagad => LedgerEntryType::DepositNagad,
        };
    }

    /**
     * What the customer chooses in their app, which depends on the kind of account we have.
     */
    public static function instructionFor(string $accountType): string
    {
        return match ($accountType) {
            'merchant' => __('Payment'),
            'agent' => __('Cash Out'),
            default => __('Send Money'),
        };
    }
}
