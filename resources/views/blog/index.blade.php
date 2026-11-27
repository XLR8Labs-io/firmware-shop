<x-layouts::site :title="__('Blog')" :description="__('Guides and news about firmware, flashing and phone repair.')">
    <div class="mx-auto w-full max-w-6xl space-y-6 px-4 py-10 sm:px-6">
        <h1 class="text-2xl font-semibold">{{ __('Blog') }}</h1>

        @if ($articles->isEmpty())
            <p class="text-zinc-600 dark:text-zinc-400">{{ __('There are no posts yet.') }}</p>
        @else
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <li class="flex flex-col overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                        @if ($article->cover_image)
                            <img src="{{ $article->coverImageUrl() }}" alt="" class="aspect-video w-full object-cover" loading="lazy">
                        @endif
                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <a href="{{ route('blog.show', $article) }}" class="font-medium break-words hover:text-accent-content hover:underline">{{ $article->title }}</a>
                            @if ($article->excerpt)
                                <p class="line-clamp-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $article->excerpt }}</p>
                            @endif
                            <p class="mt-auto pt-2 text-sm text-zinc-500 dark:text-zinc-400"><x-local-time :at="$article->published_at" /></p>
                        </div>
                    </li>
                @endforeach
            </ul>
            {{ $articles->links() }}
        @endif
    </div>
</x-layouts::site>
