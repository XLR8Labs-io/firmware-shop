@props(['folders'])

<ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
    @foreach ($folders as $folder)
        <li>
            <a href="{{ route('folders.show', $folder) }}" class="flex h-full items-center gap-3 rounded-lg border border-zinc-200 p-3 hover:border-accent dark:border-zinc-700">
                @if ($folder->image_path)
                    <img src="{{ $folder->imageUrl() }}" alt="" class="size-10 shrink-0 rounded object-contain" loading="lazy">
                @endif
                <span class="min-w-0 font-medium break-words">{{ $folder->name }}</span>
            </a>
        </li>
    @endforeach
</ul>
