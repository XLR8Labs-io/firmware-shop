<x-layouts::site :title="__('File temporarily busy')">
    <div class="mx-auto w-full max-w-2xl space-y-4 px-4 py-16 sm:px-6">
        <h1 class="text-2xl font-semibold">{{ __('This file is temporarily busy') }}</h1>
        <p class="text-zinc-600 dark:text-zinc-400">{{ __('Our storage has paused downloads of this file for a while because it was downloaded many times. Please try again later. Your purchase is safe and nothing was charged for this attempt.') }}</p>
        @if (filled($supportContact))
            <p class="whitespace-pre-line text-zinc-600 dark:text-zinc-400">{{ $supportContact }}</p>
        @endif
    </div>
</x-layouts::site>
