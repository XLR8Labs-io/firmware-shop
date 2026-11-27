<div>
    <flux:modal.trigger name="buy-product">
        <flux:button variant="primary" class="w-full">{{ __('Buy for $:price', ['price' => $shownPrice]) }}</flux:button>
    </flux:modal.trigger>

    <flux:modal name="buy-product" class="md:w-96">
        <div class="space-y-5">
            <div class="space-y-1">
                <flux:heading size="lg">{{ __('Buy this file') }}</flux:heading>
                <flux:text class="break-words">{{ $product->title }}</flux:text>
            </div>

            <dl class="grid grid-cols-[1fr_auto] gap-x-6 gap-y-1 text-sm">
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Price') }}</dt>
                <dd class="text-right font-medium">${{ $shownPrice }}</dd>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Your balance') }}</dt>
                <dd class="text-right">${{ $this->balance }}</dd>
                @if ($this->shortfall === '0.00')
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Balance after buying') }}</dt>
                    <dd class="text-right">${{ bcsub($this->balance, $shownPrice, 2) }}</dd>
                @endif
            </dl>

            @if ($problem)
                <flux:callout variant="danger" icon="x-circle" :heading="$problem" />
            @endif

            @if ($this->shortfall === '0.00')
                <flux:button wire:click="buy" variant="primary" class="w-full">{{ __('Confirm and pay $:price', ['price' => $shownPrice]) }}</flux:button>
            @else
                <flux:callout variant="warning" icon="wallet" :heading="__('You need $:amount more.', ['amount' => $this->shortfall])">
                    <flux:callout.text>
                        <a href="{{ route('account.add-funds', ['usd' => $this->shortfall]) }}" class="font-medium underline">{{ __('Add funds to your wallet') }}</a>,
                        {{ __('then come back to buy this file.') }}
                        @if (filled($supportContact))
                            <span class="mt-2 block whitespace-pre-line">{{ $supportContact }}</span>
                        @endif
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>
    </flux:modal>
</div>
