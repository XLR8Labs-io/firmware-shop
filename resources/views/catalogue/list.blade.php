<x-layouts::site :title="$heading">
    <div class="mx-auto w-full max-w-6xl space-y-6 px-4 py-10 sm:px-6">
        <h1 class="text-2xl font-semibold">{{ $heading }}</h1>

        @if ($products->isEmpty())
            <p class="text-zinc-600 dark:text-zinc-400">{{ __('No files have been published yet.') }}</p>
        @else
            <x-product-list :products="$products" :show-downloads="$showDownloads" />
            {{ $products->links() }}
        @endif
    </div>
</x-layouts::site>
