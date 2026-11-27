<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CryptoInvoiceStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';

    case Paid = 'paid';

    /** Past its open time; a payment within the reservation window still matches. */
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for payment',
            self::Paid => 'Paid',
            self::Expired => 'Expired',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Expired => 'gray',
        };
    }
}
