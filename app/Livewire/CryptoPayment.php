<?php

namespace App\Livewire;

use App\Enums\CryptoInvoiceStatus;
use App\Enums\CryptoNetwork;
use App\Models\CryptoInvoice;
use App\Models\CryptoTransfer;
use App\Models\User;
use App\Services\Crypto\BscVerifier;
use App\Services\Crypto\InvoiceMatcher;
use App\Services\Crypto\TronScanner;
use App\Services\Wallet\WalletService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * The page for paying one crypto invoice. It refreshes on its own; customers can also
 * report their transaction hash (required on BEP20, a speed-up on TRC20).
 */
#[Title('Crypto deposit')]
class CryptoPayment extends Component
{
    #[Locked]
    public CryptoInvoice $invoice;

    public string $txHash = '';

    public ?string $claimMessage = null;

    public function mount(CryptoInvoice $invoice): void
    {
        abort_unless($invoice->user_id === Auth::id(), 404);

        $this->invoice = $invoice;
    }

    public function claim(BscVerifier $bsc, TronScanner $tron, InvoiceMatcher $matcher): void
    {
        abort_unless($this->invoice->user_id === Auth::id(), 404);
        $this->validate(['txHash' => ['required', 'string', 'max:80']]);

        $rateLimitKey = 'crypto-claim:'.Auth::id();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 6)) {
            $this->claimMessage = __('Please wait a minute before checking again.');

            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        if ($this->invoice->status === CryptoInvoiceStatus::Paid) {
            return;
        }

        $hash = strtolower(trim($this->txHash));
        $network = $this->invoice->network;

        try {
            if ($network === CryptoNetwork::Bep20) {
                $result = $bsc->verify($hash);

                if ($result['status'] !== 'ok') {
                    $this->claimMessage = $result['status'] === 'pending'
                        ? __('Waiting for network confirmations. Please try again in a minute.')
                        : __('This is not a USDT BEP20 payment to our address.');

                    return;
                }

                $transfer = CryptoTransfer::query()->firstOrCreate(['tx_hash' => $hash], [
                    'network' => $network,
                    'from_address' => $result['from'] ?? null,
                    'amount' => $result['amount'] ?? '0',
                    'block_time' => $result['time'] ?? now(),
                ]);
            } else {
                $tron->scan();
                $transfer = CryptoTransfer::query()->where('network', $network)->where('tx_hash', $hash)->first();
            }
        } catch (RuntimeException) {
            $this->claimMessage = __('We could not check the network just now. Please try again shortly.');

            return;
        }

        if ($transfer === null) {
            $this->claimMessage = __('We cannot see this transaction yet. Please try again in a minute.');

            return;
        }

        if ($transfer->crypto_invoice_id === null) {
            // Restricted to this invoice: reporting someone else's hash credits nobody.
            $matcher->match($network, $hash, $transfer->amount, $transfer->block_time, $this->invoice->id);
        }

        $this->invoice->refresh();
        $this->claimMessage = null;

        if ($this->invoice->status !== CryptoInvoiceStatus::Paid) {
            $this->claimMessage = __('The amount or time does not match this deposit. Please contact support with your transaction hash.');
        }
    }

    public function render(WalletService $wallet): View
    {
        $address = (string) $this->invoice->network->address();
        $qrCode = (new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd)))->writeString($address);

        return view('livewire.crypto-payment', [
            'address' => $address,
            'qrCode' => $qrCode,
            'balance' => $wallet->balance($this->customer()),
        ]);
    }

    private function customer(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
