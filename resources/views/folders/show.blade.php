<x-layouts::site :title="$folder->name" :description="$folder->description ?: __('Firmware and files for :name.', ['name' => $folder->name])">
    <div class="mx-auto w-full max-w-6xl space-y-8 px-4 py-10 sm:px-6">
        <div class="space-y-3">
            <x-breadcrumbs :folders="$breadcrumbs" />
            <div class="flex items-center gap-4">
                @if ($folder->image_path)
                    <img src="{{ $folder->imageUrl() }}" alt="" class="size-14 shrink-0 rounded object-contain">
                @endif
                <h1 class="text-2xl font-semibold break-words">{{ $folder->name }}</h1>
            </div>
            @if ($folder->description)
                <p class="max-w-3xl text-zinc-600 dark:text-zinc-400">{{ $folder->description }}</p>
            @endif
        </div>

        @if ($subfolders->isNotEmpty())
            <x-folder-grid :folders="$subfolders" />
        @endif

        @if ($products->isNotEmpty() || request()->has('sort'))
            <section aria-labelledby="files-heading" class="space-y-4">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <h2 id="files-heading" class="text-lg font-semibold">{{ __('Files') }}</h2>
                    <nav aria-label="{{ __('Sort files') }}" class="flex gap-4 text-sm">
                        @foreach (['newest' => __('Newest'), 'downloads' => __('Most downloaded'), 'price' => __('Price')] as $value => $label)
                            <a href="{{ route('folders.show', [$folder, 'sort' => $value]) }}"
                                @class(['font-medium text-accent-content' => $sort === $value, 'text-zinc-600 hover:underline dark:text-zinc-400' => $sort !== $value])
                                @if ($sort === $value) aria-current="true" @endif>{{ $label }}</a>
                        @endforeach
                    </nav>
                </div>
                <x-product-list :products="$products" :show-downloads="$sort === 'downloads'" />
                {{ $products->links() }}
            </section>
        @elseif ($subfolders->isEmpty())
            <p class="text-zinc-600 dark:text-zinc-400">{{ __('There are no files in this folder yet.') }}</p>
        @endif
    </div>
</x-layouts::site>
