<?php

use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogueController::class, 'home'])->name('home');
Route::get('recent', [CatalogueController::class, 'recent'])->name('recent');
Route::get('top', [CatalogueController::class, 'top'])->name('top');
Route::get('search', [CatalogueController::class, 'search'])->middleware('throttle:60,1')->name('search');
Route::get('folders/{folder:slug}', [FolderController::class, 'show'])->name('folders.show');
Route::get('files/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('rss', [FeedController::class, 'rss'])->name('rss');
Route::get('sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
