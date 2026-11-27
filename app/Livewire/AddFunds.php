<?php

namespace App\Livewire;

use App\Enums\CryptoNetwork;
use App\Enums\MfsMethod;
use App\Models\User;
use App\Services\Crypto\CryptoException;
use App\Services\Crypto\InvoiceService;
use App\Services\Deposits\DepositNotAllowed;
use App\Services\Deposits\MfsDepositService;
use App\Services\Deposits\PipraPayGateway;
use App\Services\SiteSettings;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Add funds')]
class AddFunds extends Component
{
    #[Url]
    public string $method = 'bkash';

    public string $amountBdt = '';

    public string $senderNumber = '';

    public string $trxId = '';

    public ?string $notice = null;

    public string $usdAmount = '';

    /**
     * Pre-fill the taka amount from a dollar shortfall, e.g. coming from the Buy box.
     */
    public function mount(SiteSettings $settings): void
    {
        $notice = session('deposit_notice');
        $this->notice = is_string($notice) ? $notice : null;

        $usd = request()->query('usd');
        $rate = $settings->bdtPerUsd();

        if (is_string($usd) && is_numeric($usd) && bccomp($usd, '0', 2) > 0 && $rate !== null) {
            $this->amountBdt = (string) (int) ceil((float) bcmul($usd, $rate, 2));
        }
    }

    /**
     * The dollars the current amount would add, for the live preview.
     */
    #[Computed]
    public function previewUsd(): ?string
    {
        $rate = app(SiteSettings::class)->bdtPerUsd();

        if ($rate === null || ! is_numeric($this->amountBdt) || preg_match('/^\d{1,9}(\.\d{1,2})?$/', $this->amountBdt) !== 1) {
            return null;
        }

        return MfsDepositService::toUsd($this->amountBdt, $rate);
    }

    public function submit(MfsDepositService $deposits): void
    {
        $user = $this->customer();
        abort_unless($user->hasVerifiedEmail(), 403);

        $this->validateDeposit(withTrxId: true);

        $rateLimitKey = "mfs-deposit:{$user->id}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $this->addError('trxId', __('You have submitted five deposits in the last hour. Please try again in :minutes minutes.', [
                'minutes' => (int) ceil(RateLimiter::availableIn($rateLimitKey) / 60),
            ]));

            return;
        }

        RateLimiter::hit($rateLimitKey, 3600);

        try {
            $deposit = $deposits->submit($user, MfsMethod::from($this->method), $this->amountBdt, $this->senderNumber, $this->trxId);
        } catch (DepositNotAllowed $exception) {
            $this->addError($exception->field, $exception->getMessage());

            return;
        }

        $this->reset('amountBdt', 'senderNumber', 'trxId');
        $this->notice = __('Thanks. We will check TrxID :trx and add $:amount to your wallet, usually within a few hours.', [
            'trx' => $deposit->trx_id,
            'amount' => $deposit->amount_usd,
        ]);
    }

    /**
     * bKash or Nagad through PipraPay: open a deposit and go to PipraPay to pay it.
     * The wallet is credited once PipraPay confirms the payment.
     */
    public function payOnline(PipraPayGateway $gateway): void
    {
        $user = $this->customer();
        abort_unless($user->hasVerifiedEmail(), 403);

        $this->validateDeposit(withTrxId: false);

        $rateLimitKey = "mfs-online:{$user->id}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->addError('amountBdt', __('You have started ten payments in the last hour. Please try again later.'));

            return;
        }

        RateLimiter::hit($rateLimitKey, 3600);

        try {
            $checkoutUrl = $gateway->checkout($user, MfsMethod::from($this->method), $this->amountBdt, $this->senderNumber);
        } catch (DepositNotAllowed $exception) {
            $this->addError('amountBdt', $exception->getMessage());

            return;
        }

        $this->redirect($checkoutUrl);
    }

    private function validateDeposit(bool $withTrxId): void
    {
        $rules = [
            'method' => ['required', Rule::enum(MfsMethod::class)],
            'amountBdt' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'senderNumber' => ['required', 'regex:/^01\d{9}$/'],
        ];

        if ($withTrxId) {
            $rules['trxId'] = ['required', 'alpha_num:ascii', 'between:6,20'];
        }

        $this->validate($rules, [
            'amountBdt.regex' => __('Enter the amount in taka, such as 1250.'),
            'senderNumber.regex' => __('Enter the 11-digit number you pay from, starting with 01.'),
        ], [
            'amountBdt' => __('amount'),
            'senderNumber' => __('number'),
            'trxId' => __('TrxID'),
        ]);
    }

    /**
     * Crypto: reserve an exact USDT amount and go to its payment page.
     */
    public function createInvoice(InvoiceService $invoices): void
    {
        $user = $this->customer();
        abort_unless($user->hasVerifiedEmail(), 403);

        $network = CryptoNetwork::tryFrom($this->method);
        abort_if($network === null, 404);

        $this->validate(['usdAmount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/']], [
            'usdAmount.regex' => __('Enter an amount in dollars, such as 25 or 12.50.'),
        ], ['usdAmount' => __('amount')]);

        try {
            $invoice = $invoices->create($user, $network, $this->usdAmount);
        } catch (CryptoException $exception) {
            $this->addError('usdAmount', $exception->getMessage());

            return;
        }

        $this->redirectRoute('account.crypto', $invoice);
    }

    public function render(SiteSettings $settings, WalletService $wallet): View
    {
        $network = CryptoNetwork::tryFrom($this->method);
        $method = $network === null ? (MfsMethod::tryFrom($this->method) ?? MfsMethod::Bkash) : null;
        $account = $settings->mfsAccount($method ?? MfsMethod::Bkash);

        return view('livewire.add-funds', [
            'currentMethod' => $method,
            'cryptoNetwork' => $network,
            'cryptoMinimum' => InvoiceService::minimumDeposit(),
            'invoices' => $this->customer()->cryptoInvoices()->latest('id')->limit(10)->get(),
            'account' => $account,
            'payOnline' => PipraPayGateway::isEnabled(),
            'instruction' => MfsMethod::instructionFor($account['type']),
            'rate' => $settings->bdtPerUsd(),
            'minimumBdt' => $settings->mfsMinBdt(),
            'balance' => $wallet->balance($this->customer()),
            'deposits' => $this->customer()->mfsDeposits()->latest('id')->limit(10)->get(),
        ]);
    }

    private function customer(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
