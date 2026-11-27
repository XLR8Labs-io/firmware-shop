@props(['folders'])

<nav aria-label="{{ __('Breadcrumb') }}" class="text-sm text-zinc-500 dark:text-zinc-400">
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <li><a href="{{ route('home') }}" class="hover:underline">{{ __('Home') }}</a></li>
        @foreach ($folders as $crumb)
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('folders.show', $crumb) }}" class="hover:underline">{{ $crumb->name }}</a></li>
        @endforeach
    </ol>
</nav>
