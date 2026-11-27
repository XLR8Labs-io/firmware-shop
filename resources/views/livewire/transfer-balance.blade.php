<section class="w-full max-w-2xl space-y-8">
    <div class="space-y-1">
        <flux:heading size="xl" level="1">{{ __('Send balance') }}</flux:heading>
        <flux:text>{{ __('Your balance: :amount', ['amount' => '$'.$balance]) }}</flux:text>
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" :heading="$notice" />
    @endif

    <form wire:submit="{{ $recipientName === null ? 'review' : 'send' }}" class="space-y-5">
        <flux:input wire:model="email" type="email" :label="__('Customer’s email address')" :disabled="$recipientName !== null" required />
        <flux:input wire:model="amount" :label="__('Amount (USD)')" inputmode="decimal" :disabled="$recipientName !== null" required />

        @if ($recipientName === null)
            <flux:button type="submit" variant="primary">{{ __('Continue') }}</flux:button>
        @else
            <flux:callout icon="information-circle" :heading="__('Send $:amount to :name (:email)?', ['amount' => $amount, 'name' => $recipientName, 'email' => $email])">
                <flux:callout.text>{{ __('You cannot take it back yourself. Check the name before you send.') }}</flux:callout.text>
            </flux:callout>
            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary">{{ __('Send now') }}</flux:button>
                <flux:button type="button" variant="ghost" wire:click="cancelReview">{{ __('Change') }}</flux:button>
            </div>
        @endif
    </form>

    @if ($transfers->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg" level="2">{{ __('Recent transfers') }}</flux:heading>
            <ul class="divide-y divide-zinc-200 border-y border-zinc-200 text-sm dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($transfers as $transfer)
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3">
                        <span class="min-w-0 break-words">{{ $transfer->note }}</span>
                        <span class="text-zinc-500 tabular-nums dark:text-zinc-400">${{ ltrim($transfer->amount, '-') }} · <x-local-time :at="$transfer->created_at" format="j M Y, g:i A" /></span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
