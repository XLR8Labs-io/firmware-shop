<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\FolderTree;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Request $request, Product $product, FolderTree $tree): View
    {
        abort_unless($product->status === ProductStatus::Published && $tree->isVisible($product->folder_id), 404);

        $product->load('folder');

        return view('products.show', [
            'product' => $product,
            'breadcrumbs' => $product->folder->ancestors()->push($product->folder),
            'showPassword' => $product->passwordVisibleTo($request->user()),
            'ownsProduct' => $request->user()?->ownsProduct($product) ?? false,
            'relatedProducts' => Product::query()
                ->visible()
                ->where('folder_id', $product->folder_id)
                ->whereKeyNot($product->id)
                ->latest('published_at')
                ->limit(6)
                ->get(),
        ]);
    }
}
