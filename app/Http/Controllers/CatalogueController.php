<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Product;
use App\Services\FolderTree;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    public function home(): View
    {
        return view('catalogue.home', [
            'recentProducts' => Product::query()->visible()->latest('published_at')->limit(10)->get(),
            'topProducts' => Product::query()->visible()->orderByDesc('download_count')->latest('published_at')->limit(10)->get(),
            'featuredProducts' => Product::query()->visible()->where('is_featured', true)->latest('published_at')->limit(8)->get(),
            'brands' => $this->brands(),
        ]);
    }

    public function recent(): View
    {
        return view('catalogue.list', [
            'heading' => __('Recent files'),
            'showDownloads' => false,
            'products' => Product::query()->visible()->latest('published_at')->paginate(30),
        ]);
    }

    public function top(): View
    {
        return view('catalogue.list', [
            'heading' => __('Top files'),
            'showDownloads' => true,
            'products' => Product::query()->visible()->orderByDesc('download_count')->latest('published_at')->paginate(30),
        ]);
    }

    public function search(Request $request, FolderTree $tree): View
    {
        $term = Str::limit(trim($request->string('q')->toString()), 100, '');
        $brands = $this->brands();
        $brand = $brands->firstWhere('id', $request->integer('brand'));

        $products = $term === ''
            ? null
            : Product::query()
                ->visible()
                ->search($term)
                ->when($brand, fn ($query, Folder $brand) => $query->whereIn('folder_id', $tree->descendantIds($brand->id)))
                ->latest('published_at')
                ->paginate(30)
                ->withQueryString();

        return view('catalogue.search', [
            'term' => $term,
            'brands' => $brands,
            'selectedBrand' => $brand,
            'products' => $products,
        ]);
    }

    /**
     * @return Collection<int, Folder>
     */
    private function brands(): Collection
    {
        return Folder::query()
            ->whereNull('parent_id')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
