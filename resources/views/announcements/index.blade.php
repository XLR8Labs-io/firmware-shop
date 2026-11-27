<x-layouts::site :title="__('Announcements')">
    <div class="mx-auto w-full max-w-3xl space-y-6 px-4 py-10 sm:px-6">
        <h1 class="text-2xl font-semibold">{{ __('Announcements') }}</h1>

        @if ($announcements->isEmpty())
            <p class="text-zinc-600 dark:text-zinc-400">{{ __('There are no announcements yet.') }}</p>
        @else
            <ul class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($announcements as $announcement)
                    <li class="space-y-1 py-4">
                        <a href="{{ route('announcements.show', $announcement) }}" class="font-medium break-words hover:text-accent-content hover:underline">{{ $announcement->title }}</a>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400"><x-local-time :at="$announcement->published_at" /></p>
                    </li>
                @endforeach
            </ul>
            {{ $announcements->links() }}
        @endif
    </div>
</x-layouts::site>
