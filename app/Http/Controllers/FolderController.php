<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Product;
use App\Services\FolderTree;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FolderController extends Controller
{
    /**
     * Sort options shown on the page; anything else in the URL falls back to newest.
     */
    private const array SORTS = ['newest', 'downloads', 'price'];

    public function show(Request $request, Folder $folder, FolderTree $tree): View
    {
        abort_unless($tree->isVisible($folder->id), 404);

        $sort = in_array($request->query('sort'), self::SORTS, true) ? (string) $request->query('sort') : 'newest';

        $products = Product::query()
            ->visible()
            ->where('folder_id', $folder->id)
            ->when($sort === 'downloads', fn ($query) => $query->orderByDesc('download_count'))
            ->when($sort === 'price', fn ($query) => $query->orderBy('price'))
            ->latest('published_at')
            ->paginate(30)
            ->withQueryString();

        return view('folders.show', [
            'folder' => $folder,
            'breadcrumbs' => $folder->ancestors(),
            'subfolders' => $folder->children()->where('is_visible', true)->get(),
            'products' => $products,
            'sort' => $sort,
        ]);
    }
}
