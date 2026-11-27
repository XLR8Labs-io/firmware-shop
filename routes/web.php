<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\ProductController;
use App\Livewire\AddFunds;
use App\Livewire\CryptoPayment;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogueController::class, 'home'])->name('home');
Route::get('recent', [CatalogueController::class, 'recent'])->name('recent');
Route::get('top', [CatalogueController::class, 'top'])->name('top');
Route::get('search', [CatalogueController::class, 'search'])->middleware('throttle:60,1')->name('search');
Route::get('folders/{folder:slug}', [FolderController::class, 'show'])->name('folders.show');
Route::get('files/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('rss', [FeedController::class, 'rss'])->name('rss');

Route::post('files/{product:slug}/download', [DownloadController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:20,1'])
    ->name('downloads.store');
// No login needed: the token is the permission, so download managers and other devices work.
Route::get('dl/{token}/{fileName}', [DownloadController::class, 'stream'])
    ->middleware('throttle:downloads')
    ->where('fileName', '.*')
    ->name('downloads.stream');
Route::get('sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('account/purchases', [AccountController::class, 'purchases'])->name('account.purchases');
    Route::get('account/wallet', [AccountController::class, 'wallet'])->name('account.wallet');
    Route::livewire('account/add-funds', AddFunds::class)->name('account.add-funds');
    Route::livewire('account/crypto/{invoice}', CryptoPayment::class)->name('account.crypto');
});

require __DIR__.'/settings.php';
