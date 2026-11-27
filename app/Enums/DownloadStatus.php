<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DownloadStatus: string implements HasColor, HasLabel
{
    case Started = 'started';

    /** Google temporarily stopped downloads of the file (and of its mirror, if any). */
    case QuotaExceeded = 'quota_exceeded';

    case Error = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::Started => 'Started',
            self::QuotaExceeded => 'Google quota reached',
            self::Error => 'Error',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Started => 'success',
            self::QuotaExceeded => 'warning',
            self::Error => 'danger',
        };
    }
}
