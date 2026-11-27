<section class="w-full max-w-2xl space-y-8">
    <div class="space-y-1">
        <flux:heading size="xl" level="1">{{ __('Add funds') }}</flux:heading>
        <flux:text>{{ __('Your balance: :amount', ['amount' => '$'.$balance]) }}</flux:text>
    </div>

    <div class="inline-flex rounded-lg border border-zinc-200 p-1 dark:border-zinc-700" role="tablist" aria-label="{{ __('Payment method') }}">
        @foreach ([...App\Enums\MfsMethod::cases(), ...App\Enums\CryptoNetwork::cases()] as $option)
            @php($selected = $this->method === $option->value)
            <button type="button" role="tab" aria-selected="{{ $selected ? 'true' : 'false' }}"
                wire:click="$set('method', '{{ $option->value }}')"
                @class(['rounded-md px-4 py-1.5 text-sm font-medium', 'bg-accent text-accent-foreground' => $selected, 'text-zinc-600 dark:text-zinc-300' => ! $selected])>
                {{ $option instanceof App\Enums\CryptoNetwork ? 'USDT '.strtoupper($option->value) : $option->getLabel() }}
            </button>
        @endforeach
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" :heading="$notice" />
    @endif

    @if ($currentMethod !== null)
        @if ($rate === null || (blank($account['number']) && ! $payOnline))
            <flux:callout variant="warning" icon="exclamation-triangle" :heading="__(':method deposits are not available right now. Please contact support.', ['method' => $currentMethod->getLabel()])" />
        @else
            <flux:text>{{ __('Today’s rate is 1 USD = :rate BDT; the smallest deposit is :minimum BDT.', ['rate' => rtrim(rtrim($rate, '0'), '.'), 'minimum' => $minimumBdt]) }}</flux:text>

            @if ($payOnline)
                <div class="space-y-4">
                    <flux:heading size="lg" level="2">{{ __('Pay online') }}</flux:heading>
                    <flux:text>{{ __('Pay on the next page with bKash or Nagad. Your wallet is topped up as soon as the payment is confirmed.') }}</flux:text>
                    <form wire:submit="payOnline" class="space-y-5">
                        <flux:input wire:model.live.debounce.400ms="amountBdt" :label="__('Amount (BDT)')" inputmode="decimal" required />
                        @if ($this->previewUsd !== null)
                            <flux:text>{{ __('You will receive :amount in your wallet.', ['amount' => '$'.$this->previewUsd]) }}</flux:text>
                        @endif
                        <flux:input wire:model="senderNumber" :label="__('Your :method number', ['method' => $currentMethod->getLabel()])" placeholder="01XXXXXXXXX" inputmode="numeric" maxlength="11" required />
                        <flux:button type="submit" variant="primary">{{ __('Continue to payment') }}</flux:button>
                    </form>
                </div>
            @endif

            @if (filled($account['number']))
                {{-- With online payment on, sending it yourself is the fallback and starts folded away. --}}
                <details wire:ignore.self @if (! $payOnline) open @endif>
                    <summary @class(['cursor-pointer font-medium text-accent-content hover:underline', 'hidden' => ! $payOnline])>{{ __('Or send it yourself and enter the TrxID') }}</summary>
                    <ol class="mt-4 list-decimal space-y-2 pl-5">
                        <li>{!! __('In the :method app, choose <strong>:instruction</strong> to <strong class="font-mono">:number</strong> (:type account).', ['method' => e($currentMethod->getLabel()), 'instruction' => e($instruction), 'number' => e($account['number']), 'type' => e(__($account['type']))]) !!}</li>
                        <li>{{ __('Send the amount in taka.') }}</li>
                        <li>{{ __('Enter what you sent below, with the TrxID from the confirmation SMS.') }}</li>
                    </ol>

                    <form wire:submit="submit" class="mt-5 space-y-5">
                        <flux:input wire:model.live.debounce.400ms="amountBdt" :label="__('Amount sent (BDT)')" inputmode="decimal" required />
                        @if (! $payOnline && $this->previewUsd !== null)
                            <flux:text>{{ __('You will receive :amount in your wallet.', ['amount' => '$'.$this->previewUsd]) }}</flux:text>
                        @endif
                        <flux:input wire:model="senderNumber" :label="__('Your :method number', ['method' => $currentMethod->getLabel()])" placeholder="01XXXXXXXXX" inputmode="numeric" maxlength="11" required />
                        <flux:input wire:model="trxId" :label="__('TrxID')" maxlength="20" required />
                        <flux:button type="submit" :variant="$payOnline ? 'filled' : 'primary'">{{ __('Submit deposit') }}</flux:button>
                    </form>
                </details>
            @endif
        @endif

    @else
        @if ($cryptoNetwork->address() === null)
            <flux:callout variant="warning" icon="exclamation-triangle" :heading="__(':network deposits are not available right now. Please contact support.', ['network' => $cryptoNetwork->getLabel()])" />
        @else
            <flux:text>{{ __('Pay with :network. We give you an exact amount to send so we can tell your payment apart; it is added to your wallet in full. The smallest deposit is $:minimum.', ['network' => $cryptoNetwork->getLabel(), 'minimum' => $cryptoMinimum]) }}</flux:text>
            <form wire:submit="createInvoice" class="space-y-4">
                <flux:input wire:model="usdAmount" :label="__('Amount to add (USD)')" inputmode="decimal" required />
                <flux:button type="submit" variant="primary">{{ __('Continue to payment') }}</flux:button>
            </form>
        @endif

        @if ($invoices->isNotEmpty())
            <div class="space-y-3">
                <flux:heading size="lg" level="2">{{ __('Your crypto deposits') }}</flux:heading>
                <ul class="divide-y divide-zinc-200 border-y border-zinc-200 text-sm dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach ($invoices as $invoice)
                        <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3">
                            <a href="{{ route('account.crypto', $invoice) }}" class="font-medium hover:underline" wire:navigate>{{ $invoice->pay_amount }} USDT · {{ strtoupper($invoice->network->value) }}</a>
                            <span class="text-zinc-500 dark:text-zinc-400">{{ $invoice->status->getLabel() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
    @if ($deposits->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg" level="2">{{ __('Your deposits') }}</flux:heading>
            <ul class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($deposits as $deposit)
                    <li class="space-y-1 py-3 text-sm">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <span class="font-medium">{{ $deposit->method->getLabel() }} · {{ $deposit->amount_bdt }} BDT → ${{ $deposit->amount_usd }}</span>
                            <flux:badge size="sm" :color="match ($deposit->status) { App\Enums\DepositStatus::Approved => 'green', App\Enums\DepositStatus::Rejected => 'red', default => 'amber' }">{{ $deposit->source === 'piprapay' && $deposit->status === App\Enums\DepositStatus::Pending ? __('Waiting for payment') : $deposit->status->getLabel() }}</flux:badge>
                        </div>
                        <p class="text-zinc-500 dark:text-zinc-400">
                            @if ($deposit->trx_id)
                                TrxID <span class="font-mono">{{ $deposit->trx_id }}</span> ·
                            @else
                                {{ __('Paid online') }} ·
                            @endif
                            <x-local-time :at="$deposit->created_at" format="j M Y, g:i A" />
                        </p>
                        @if ($deposit->status === App\Enums\DepositStatus::Rejected && $deposit->admin_note)
                            <p class="text-zinc-600 dark:text-zinc-400">{{ __('Reason: :reason', ['reason' => $deposit->admin_note]) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
