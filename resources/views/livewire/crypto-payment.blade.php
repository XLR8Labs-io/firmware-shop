<section class="w-full max-w-2xl space-y-6" @if ($invoice->status !== App\Enums\CryptoInvoiceStatus::Paid) wire:poll.15s @endif>
    <div class="space-y-1">
        <flux:heading size="xl" level="1">{{ $invoice->network->getLabel() }}</flux:heading>
        <flux:text>{{ __('Deposit #:id', ['id' => $invoice->id]) }}</flux:text>
    </div>

    @if ($invoice->status === App\Enums\CryptoInvoiceStatus::Paid)
        <flux:callout variant="success" icon="check-circle" :heading="__('Payment received. $:amount was added to your wallet.', ['amount' => $invoice->pay_amount])">
            <flux:callout.text>{{ __('Your balance is now :balance.', ['balance' => '$'.$balance]) }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="space-y-4 rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
            <div>
                <flux:text>{{ __('Amount that must arrive') }}</flux:text>
                <p class="text-3xl font-semibold tabular-nums">{{ $invoice->pay_amount }} USDT</p>
            </div>
            <div class="space-y-2">
                <flux:text>{{ __('Send it to') }}</flux:text>
                <p class="font-mono text-sm break-all">{{ $address }}</p>
                <div class="w-48 rounded bg-white p-2" aria-hidden="true">{!! $qrCode !!}</div>
            </div>
            <flux:text>
                @if ($invoice->expires_at->isFuture())
                    {{ __('Open until') }} <x-local-time :at="$invoice->expires_at" format="g:i A" />.
                @else
                    {{ __('This deposit has expired. If you already paid, enter your transaction hash below.') }}
                @endif
            </flux:text>
        </div>

        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Before you send')">
            <flux:callout.text>
                <ul class="list-disc space-y-1 pl-5">
                    <li>{{ __('Send USDT on :network only. Another network or token will be lost.', ['network' => $invoice->network->getLabel()]) }}</li>
                    <li>{{ __('Sending from an exchange? Add its withdrawal fee so that exactly :amount USDT arrives.', ['amount' => $invoice->pay_amount]) }}</li>
                    <li>{{ __('Paying less is not detected automatically. Contact support with your transaction hash.') }}</li>
                </ul>
            </flux:callout.text>
        </flux:callout>

        <form wire:submit="claim" class="space-y-3">
            <flux:input wire:model="txHash" :label="__('I have paid: transaction hash')" :description="$invoice->network === App\Enums\CryptoNetwork::Bep20 ? __('Required for BEP20.') : __('Optional for TRC20; payments are detected automatically.')" maxlength="80" />
            @if ($claimMessage)
                <flux:text>{{ $claimMessage }}</flux:text>
            @endif
            <flux:button type="submit">{{ __('Check payment') }}</flux:button>
        </form>
    @endif
</section>
