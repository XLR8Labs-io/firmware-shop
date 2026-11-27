<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why money moved. Transfer types arrive with the phases that add them.
 */
enum LedgerEntryType: string implements HasLabel
{
    case Purchase = 'purchase';

    case Refund = 'refund';

    case AdminCredit = 'admin_credit';

    case AdminDebit = 'admin_debit';

    case DepositBkash = 'deposit_bkash';

    case DepositNagad = 'deposit_nagad';

    case DepositCrypto = 'deposit_crypto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Refund => 'Refund',
            self::AdminCredit => 'Added by admin',
            self::AdminDebit => 'Removed by admin',
            self::DepositBkash => 'bKash deposit',
            self::DepositNagad => 'Nagad deposit',
            self::DepositCrypto => 'Crypto deposit',
        };
    }
}
