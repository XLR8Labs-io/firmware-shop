<?php

namespace App\Filament\Widgets;

use App\Models\DownloadToken;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Every downloaded byte passes through the server; Google plans to charge for Drive
 * API traffic above a daily allowance, so this keeps the volume visible.
 */
class DownloadEgress extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $thisMonth = DownloadToken::query()->where('created_at', '>=', now()->startOfMonth());

        return [
            Stat::make('Downloaded this month', Number::fileSize((int) (clone $thisMonth)->sum('bytes_served'), precision: 1)),
            Stat::make('Download links this month', number_format((clone $thisMonth)->count())),
        ];
    }
}
