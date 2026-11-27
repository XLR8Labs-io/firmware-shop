<section class="w-full max-w-2xl space-y-8">
    <div class="space-y-1">
        <flux:heading size="xl" level="1">{{ __('Add funds') }}</flux:heading>
        <flux:text>{{ __('Your balance: :amount', ['amount' => '$'.$balance]) }}</flux:text>
    </div>

    <div class="inline-flex rounded-lg border border-zinc-200 p-1 dark:border-zinc-700" role="tablist" aria-label="{{ __('Payment method') }}">
        @foreach (App\Enums\MfsMethod::cases() as $option)
            <button type="button" role="tab" aria-selected="{{ $currentMethod === $option ? 'true' : 'false' }}"
                wire:click="$set('method', '{{ $option->value }}')"
                @class(['rounded-md px-4 py-1.5 text-sm font-medium', 'bg-accent text-accent-foreground' => $currentMethod === $option, 'text-zinc-600 dark:text-zinc-300' => $currentMethod !== $option])>
                {{ $option->getLabel() }}
            </button>
        @endforeach
    </div>

    @if ($rate === null || blank($account['number']))
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__(':method deposits are not available right now. Please contact support.', ['method' => $currentMethod->getLabel()])" />
    @else
        <ol class="list-decimal space-y-2 pl-5">
            <li>{!! __('In the :method app, choose <strong>:instruction</strong> to <strong class="font-mono">:number</strong> (:type account).', ['method' => e($currentMethod->getLabel()), 'instruction' => e($instruction), 'number' => e($account['number']), 'type' => e(__($account['type']))]) !!}</li>
            <li>{{ __('Send the amount in taka. Today’s rate is 1 USD = :rate BDT; the smallest deposit is :minimum BDT.', ['rate' => rtrim(rtrim($rate, '0'), '.'), 'minimum' => $minimumBdt]) }}</li>
            <li>{{ __('Enter what you sent below, with the TrxID from the confirmation SMS.') }}</li>
        </ol>

        @if ($notice)
            <flux:callout variant="success" icon="check-circle" :heading="$notice" />
        @endif

        <form wire:submit="submit" class="space-y-5">
            <flux:input wire:model.live.debounce.400ms="amountBdt" :label="__('Amount sent (BDT)')" inputmode="decimal" required />
            @if ($this->previewUsd !== null)
                <flux:text>{{ __('You will receive :amount in your wallet.', ['amount' => '$'.$this->previewUsd]) }}</flux:text>
            @endif
            <flux:input wire:model="senderNumber" :label="__('Your :method number', ['method' => $currentMethod->getLabel()])" placeholder="01XXXXXXXXX" inputmode="numeric" maxlength="11" required />
            <flux:input wire:model="trxId" :label="__('TrxID')" maxlength="20" required />
            <flux:button type="submit" variant="primary">{{ __('Submit deposit') }}</flux:button>
        </form>
    @endif

    @if ($deposits->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg" level="2">{{ __('Your deposits') }}</flux:heading>
            <ul class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($deposits as $deposit)
                    <li class="space-y-1 py-3 text-sm">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <span class="font-medium">{{ $deposit->method->getLabel() }} · {{ $deposit->amount_bdt }} BDT → ${{ $deposit->amount_usd }}</span>
                            <flux:badge size="sm" :color="match ($deposit->status) { App\Enums\DepositStatus::Approved => 'green', App\Enums\DepositStatus::Rejected => 'red', default => 'amber' }">{{ $deposit->status->getLabel() }}</flux:badge>
                        </div>
                        <p class="text-zinc-500 dark:text-zinc-400">TrxID <span class="font-mono">{{ $deposit->trx_id }}</span> · <x-local-time :at="$deposit->created_at" format="j M Y, g:i A" /></p>
                        @if ($deposit->status === App\Enums\DepositStatus::Rejected && $deposit->admin_note)
                            <p class="text-zinc-600 dark:text-zinc-400">{{ __('Reason: :reason', ['reason' => $deposit->admin_note]) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
