<x-layouts::app :title="__('Dashboard')">
    <div class="space-y-10">
        @if (auth()->user()->is_banned)
            <flux:callout variant="danger" icon="no-symbol" :heading="__('Your account cannot buy or download files.')">
                @if (auth()->user()->ban_reason)
                    <flux:callout.text>{{ __('Reason: :reason', ['reason' => auth()->user()->ban_reason]) }}</flux:callout.text>
                @endif
                <flux:callout.text>{{ __('Contact support if you think this is a mistake.') }}</flux:callout.text>
            </flux:callout>
        @endif

        <section class="space-y-2">
            <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
            <div class="rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text>{{ __('Wallet balance') }}</flux:text>
                <p class="mt-1 text-3xl font-semibold tabular-nums">${{ $balance }}</p>
                <a href="{{ route('account.wallet') }}" class="mt-3 inline-block text-sm font-medium text-accent-content hover:underline" wire:navigate>{{ __('Wallet history') }}</a>
            </div>
        </section>

        <section class="space-y-3">
            <div class="flex items-baseline justify-between gap-4">
                <flux:heading size="lg" level="2">{{ __('Recent purchases') }}</flux:heading>
                <a href="{{ route('account.purchases') }}" class="text-sm font-medium text-accent-content hover:underline" wire:navigate>{{ __('All purchases') }}</a>
            </div>
            @if ($recentPurchases->isEmpty())
                <flux:text>{{ __('You have not bought any files yet.') }}</flux:text>
            @else
                <ul class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach ($recentPurchases as $purchase)
                        <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3">
                            <span class="min-w-0 font-medium break-words">{{ $purchase->product->title }}</span>
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">${{ $purchase->price_paid }} · {{ $purchase->status->getLabel() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts::app>
