<x-layouts::site :description="__('Firmware, flash files and repair solutions for mobile phones, sorted by brand and model.')">
    <div class="mx-auto w-full max-w-6xl space-y-12 px-4 py-10 sm:px-6">
        <h1 class="sr-only">{{ app(App\Services\SiteSettings::class)->siteName() }}</h1>

        @if ($recentProducts->isEmpty() && $brands->isEmpty())
            <p class="text-zinc-600 dark:text-zinc-400">{{ __('No files have been published yet.') }}</p>
        @endif

        @if ($recentProducts->isNotEmpty())
            <div class="grid gap-12 lg:grid-cols-2">
                <section aria-labelledby="recent-heading" class="space-y-4">
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 id="recent-heading" class="text-lg font-semibold">{{ __('Recent files') }}</h2>
                        <a href="{{ route('recent') }}" class="text-sm font-medium text-accent-content hover:underline">{{ __('All recent files') }}</a>
                    </div>
                    <x-product-list :products="$recentProducts" />
                </section>

                <section aria-labelledby="top-heading" class="space-y-4">
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 id="top-heading" class="text-lg font-semibold">{{ __('Top files') }}</h2>
                        <a href="{{ route('top') }}" class="text-sm font-medium text-accent-content hover:underline">{{ __('All top files') }}</a>
                    </div>
                    <x-product-list :products="$topProducts" show-downloads />
                </section>
            </div>
        @endif

        @if ($featuredProducts->isNotEmpty())
            <section aria-labelledby="featured-heading" class="space-y-4">
                <h2 id="featured-heading" class="text-lg font-semibold">{{ __('Featured') }}</h2>
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($featuredProducts as $product)
                        <li class="flex flex-col rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <a href="{{ route('products.show', $product) }}" class="font-medium break-words hover:text-accent-content hover:underline">{{ $product->title }}</a>
                            @if ($product->short_description)
                                <p class="mt-2 line-clamp-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $product->short_description }}</p>
                            @endif
                            <p class="mt-auto flex items-baseline justify-between gap-3 pt-4 text-sm text-zinc-500 dark:text-zinc-400">
                                <span>{{ Number::fileSize($product->file_size, precision: 1) }}</span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $product->displayPrice() }}</span>
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($brands->isNotEmpty())
            <section aria-labelledby="brands-heading" class="space-y-4">
                <h2 id="brands-heading" class="text-lg font-semibold">{{ __('Brands') }}</h2>
                <x-folder-grid :folders="$brands" />
            </section>
        @endif

        @if ($announcements->isNotEmpty())
            <section aria-labelledby="announcements-heading" class="space-y-4">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 id="announcements-heading" class="text-lg font-semibold">{{ __('Announcements') }}</h2>
                    <a href="{{ route('announcements.index') }}" class="text-sm font-medium text-accent-content hover:underline">{{ __('All announcements') }}</a>
                </div>
                <ul class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach ($announcements as $announcement)
                        <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3">
                            <a href="{{ route('announcements.show', $announcement) }}" class="min-w-0 font-medium break-words hover:text-accent-content hover:underline">{{ $announcement->title }}</a>
                            <span class="text-sm text-zinc-500 dark:text-zinc-400"><x-local-time :at="$announcement->published_at" /></span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts::site>
