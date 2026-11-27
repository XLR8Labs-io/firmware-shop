@props(['products', 'showDownloads' => false])

<ul class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
    @foreach ($products as $product)
        <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
            <a href="{{ route('products.show', $product) }}" class="min-w-0 font-medium break-words hover:text-accent-content hover:underline sm:flex-1">{{ $product->title }}</a>
            <span class="flex shrink-0 items-center gap-4 text-sm text-zinc-500 dark:text-zinc-400">
                @if ($product->published_at)
                    <x-local-time :at="$product->published_at" format="j M Y" />
                @endif
                <span>{{ Number::fileSize($product->file_size, precision: 1) }}</span>
                @if ($showDownloads)
                    <span>{{ trans_choice(':count download|:count downloads', $product->download_count) }}</span>
                @endif
                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $product->displayPrice() }}</span>
            </span>
        </li>
    @endforeach
</ul>
