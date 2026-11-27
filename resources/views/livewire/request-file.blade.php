<div class="space-y-10">
    @if ($notice)
        <flux:callout variant="success" icon="check-circle" :heading="$notice" />
    @endif

    <form wire:submit="submit" class="space-y-5">
        <div class="grid gap-5 sm:grid-cols-2">
            <flux:input wire:model="name" :label="__('Your name')" maxlength="100" required />
            <flux:input wire:model="contact" :label="__('Email, Telegram or WhatsApp')" maxlength="150" required />
            <flux:input wire:model="brand" :label="__('Brand')" placeholder="Samsung" maxlength="100" required />
            <flux:input wire:model="model" :label="__('Model or model number')" placeholder="SM-A156E" maxlength="150" required />
        </div>
        <flux:textarea wire:model="details" :label="__('What do you need?')" :placeholder="__('Firmware version, region, or the problem you are fixing')" rows="4" maxlength="2000" />

        {{-- Left empty by people; bots fill it in. --}}
        <div class="absolute -left-[9999px]" aria-hidden="true">
            <label for="request-website">Website</label>
            <input id="request-website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
        </div>

        <flux:button type="submit" variant="primary">{{ __('Send request') }}</flux:button>
    </form>

    @if ($myRequests->isNotEmpty())
        <section aria-labelledby="my-requests-heading" class="space-y-3">
            <h2 id="my-requests-heading" class="text-lg font-semibold">{{ __('Your requests') }}</h2>
            <ul class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($myRequests as $request)
                    <li class="space-y-1 py-3 text-sm">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <span class="font-medium break-words">{{ $request->brand }} {{ $request->model }}</span>
                            <flux:badge size="sm" :color="match ($request->status) { App\Enums\FileRequestStatus::Done => 'green', App\Enums\FileRequestStatus::Rejected => 'zinc', default => 'amber' }">{{ $request->status->getLabel() }}</flux:badge>
                        </div>
                        <p class="text-zinc-500 dark:text-zinc-400"><x-local-time :at="$request->created_at" /></p>
                        @if ($request->admin_reply)
                            <p class="whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $request->admin_reply }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
