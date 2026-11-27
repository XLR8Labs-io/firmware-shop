<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductStatus: string implements HasColor, HasLabel
{
    /** Imported or created but never shown to customers. */
    case Draft = 'draft';

    case Published = 'published';

    /** Taken down after being published. */
    case Hidden = 'hidden';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Hidden => 'Hidden',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::Hidden => 'warning',
        };
    }
}
