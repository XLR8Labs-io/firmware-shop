<?php

namespace App\Livewire;

use App\Enums\MfsMethod;
use App\Models\User;
use App\Services\Deposits\DepositNotAllowed;
use App\Services\Deposits\MfsDepositService;
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

    /**
     * Pre-fill the taka amount from a dollar shortfall, e.g. coming from the Buy box.
     */
    public function mount(SiteSettings $settings): void
    {
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

        $this->validate([
            'method' => ['required', Rule::enum(MfsMethod::class)],
            'amountBdt' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'senderNumber' => ['required', 'regex:/^01\d{9}$/'],
            'trxId' => ['required', 'alpha_num:ascii', 'between:6,20'],
        ], [
            'amountBdt.regex' => __('Enter the amount in taka, such as 1250.'),
            'senderNumber.regex' => __('Enter the 11-digit number you paid from, starting with 01.'),
        ], [
            'amountBdt' => __('amount'),
            'senderNumber' => __('number'),
            'trxId' => __('TrxID'),
        ]);

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

    public function render(SiteSettings $settings, WalletService $wallet): View
    {
        $method = MfsMethod::tryFrom($this->method) ?? MfsMethod::Bkash;
        $account = $settings->mfsAccount($method);

        return view('livewire.add-funds', [
            'currentMethod' => $method,
            'account' => $account,
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
