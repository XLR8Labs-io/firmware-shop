<x-layouts::site :title="$announcement->title" :description="Str::limit(strip_tags(Str::markdown($announcement->body, ['html_input' => 'strip'])), 160)">
    <article class="mx-auto w-full max-w-3xl space-y-6 px-4 py-10 sm:px-6">
        <div class="space-y-2">
            <a href="{{ route('announcements.index') }}" class="text-sm font-medium text-accent-content hover:underline">{{ __('All announcements') }}</a>
            <h1 class="text-2xl font-semibold break-words">{{ $announcement->title }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400"><x-local-time :at="$announcement->published_at" /></p>
        </div>

        <x-markdown :text="$announcement->body" />
    </article>
</x-layouts::site>
