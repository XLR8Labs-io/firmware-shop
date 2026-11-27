<x-layouts::site :title="$term === '' ? __('Search') : __('Search: :term', ['term' => $term])">
    <div class="mx-auto w-full max-w-6xl space-y-6 px-4 py-10 sm:px-6">
        <h1 class="text-2xl font-semibold">{{ __('Search files') }}</h1>

        <form method="GET" action="{{ route('search') }}" role="search" class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-96">
                <flux:input name="q" :value="$term" :label="__('Model, model number or codename')" icon="magnifying-glass" maxlength="100" />
            </div>
            @if ($brands->isNotEmpty())
                <div class="w-full sm:w-56">
                    <flux:select name="brand" :label="__('Brand')">
                        <flux:select.option value="">{{ __('All brands') }}</flux:select.option>
                        @foreach ($brands as $brand)
                            <flux:select.option :value="$brand->id" :selected="$selectedBrand?->id === $brand->id">{{ $brand->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif
            <flux:button type="submit" variant="primary">{{ __('Search') }}</flux:button>
        </form>

        @if ($products !== null)
            @if ($products->isEmpty())
                <p class="text-zinc-600 dark:text-zinc-400">{{ __('No files match ":term". Try the model number or codename, or request the file.', ['term' => $term]) }}</p>
            @else
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ trans_choice(':count file found|:count files found', $products->total()) }}</p>
                <x-product-list :products="$products" />
                {{ $products->links() }}
            @endif
        @endif
    </div>
</x-layouts::site>
