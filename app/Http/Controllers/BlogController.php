<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('blog.index', [
            'articles' => Article::query()->published()->latest('published_at')->paginate(12),
        ]);
    }

    public function show(Article $article): View
    {
        abort_unless($article->isPublic(), 404);

        return view('blog.show', [
            'article' => $article,
            'moreArticles' => Article::query()->published()->whereKeyNot($article->id)->latest('published_at')->limit(3)->get(),
        ]);
    }
}
