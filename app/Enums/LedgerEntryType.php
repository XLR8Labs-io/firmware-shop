<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why money moved. Deposit and transfer types arrive with the phases that add them.
 */
enum LedgerEntryType: string implements HasLabel
{
    case Purchase = 'purchase';

    case Refund = 'refund';

    case AdminCredit = 'admin_credit';

    case AdminDebit = 'admin_debit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Refund => 'Refund',
            self::AdminCredit => 'Added by admin',
            self::AdminDebit => 'Removed by admin',
        };
    }
}
