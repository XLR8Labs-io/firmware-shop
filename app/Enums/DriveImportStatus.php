<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DriveImportStatus: string implements HasColor, HasLabel
{
    case Queued = 'queued';

    case Running = 'running';

    case Finished = 'finished';

    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Queued => 'Waiting to start',
            self::Running => 'Running',
            self::Finished => 'Finished',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Running => 'info',
            self::Finished => 'success',
            self::Failed => 'danger',
        };
    }
}
