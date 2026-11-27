<x-layouts::site :title="$article->title" :description="$article->excerpt" :image="$article->coverImageUrl()">
    <article class="mx-auto w-full max-w-3xl space-y-6 px-4 py-10 sm:px-6">
        <div class="space-y-2">
            <a href="{{ route('blog.index') }}" class="text-sm font-medium text-accent-content hover:underline">{{ __('Blog') }}</a>
            <h1 class="text-2xl font-semibold break-words">{{ $article->title }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400"><x-local-time :at="$article->published_at" /></p>
        </div>

        @if ($article->cover_image)
            <img src="{{ $article->coverImageUrl() }}" alt="" class="aspect-video w-full rounded-lg object-cover">
        @endif

        <x-markdown :text="$article->body" />
    </article>

    @if ($moreArticles->isNotEmpty())
        <section aria-labelledby="more-heading" class="mx-auto w-full max-w-3xl space-y-3 px-4 pb-10 sm:px-6">
            <h2 id="more-heading" class="text-lg font-semibold">{{ __('More posts') }}</h2>
            <ul class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($moreArticles as $more)
                    <li class="py-3">
                        <a href="{{ route('blog.show', $more) }}" class="font-medium break-words hover:text-accent-content hover:underline">{{ $more->title }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts::site>
