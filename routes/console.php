<?php

use App\Models\DownloadToken;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => DownloadToken::query()->where('created_at', '<', now()->subDays(7))->delete())
    ->hourly()
    ->name('delete-old-download-links')
    ->withoutOverlapping();
