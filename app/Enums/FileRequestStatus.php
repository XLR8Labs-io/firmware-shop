<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FileRequestStatus: string implements HasColor, HasLabel
{
    case Open = 'open';

    case Done = 'done';

    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Done => 'Added',
            self::Rejected => 'Not available',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Done => 'success',
            self::Rejected => 'danger',
        };
    }
}
