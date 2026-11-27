<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseStatus: string implements HasColor, HasLabel
{
    case Active = 'active';

    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Refunded => 'Refunded',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Refunded => 'gray',
        };
    }
}
