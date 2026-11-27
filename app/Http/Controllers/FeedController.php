<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Folder;
use App\Models\Page;
use App\Models\Product;
use App\Services\FolderTree;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class FeedController extends Controller
{
    public function rss(): Response
    {
        return response()
            ->view('feeds.rss', [
                'products' => Product::query()->visible()->latest('published_at')->limit(30)->get(),
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    /**
     * Rebuilt at most once an hour; search engines don't need it fresher than that.
     */
    public function sitemap(FolderTree $tree): Response
    {
        $xml = Cache::remember('sitemap', now()->addHour(), fn (): string => view('feeds.sitemap', [
            'folders' => Folder::query()->whereKey($tree->visibleIds())->get(['id', 'slug', 'updated_at']),
            'products' => Product::query()->visible()->get(['id', 'slug', 'updated_at']),
            'articles' => Article::query()->published()->get(['id', 'slug', 'updated_at']),
            'pages' => Page::query()->published()->get(['id', 'slug', 'updated_at']),
        ])->render());

        return response($xml)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
