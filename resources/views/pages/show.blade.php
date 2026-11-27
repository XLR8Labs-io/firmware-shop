<x-layouts::site :title="$page->title">
    <article class="mx-auto w-full max-w-3xl space-y-6 px-4 py-10 sm:px-6">
        <h1 class="text-2xl font-semibold break-words">{{ $page->title }}</h1>
        <x-markdown :text="$page->body" />
    </article>
</x-layouts::site>
