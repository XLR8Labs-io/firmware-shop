<?php

use App\Enums\CryptoInvoiceStatus;
use App\Models\CryptoInvoice;
use App\Models\DownloadToken;
use App\Services\Crypto\TronScanner;
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

Schedule::call(fn () => app(TronScanner::class)->scan())
    ->everyMinute()
    ->name('crypto-tron-scan')
    ->withoutOverlapping();

Schedule::call(fn () => CryptoInvoice::query()
    ->where('status', CryptoInvoiceStatus::Pending)
    ->where('expires_at', '<', now())
    ->update(['status' => CryptoInvoiceStatus::Expired]))
    ->everyFiveMinutes()
    ->name('crypto-expire');
