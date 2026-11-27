<x-layouts::site :title="__('Request a file')" :description="__('Ask for a firmware or flash file that is not on the site yet.')">
    <div class="mx-auto w-full max-w-2xl space-y-6 px-4 py-10 sm:px-6">
        <div class="space-y-2">
            <h1 class="text-2xl font-semibold">{{ __('Request a file') }}</h1>
            <p class="text-zinc-600 dark:text-zinc-400">{{ __('Can’t find the file you need? Tell us the phone and what you are looking for, and we will try to add it.') }}</p>
        </div>

        <livewire:request-file />
    </div>
</x-layouts::site>
