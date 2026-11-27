<?php

namespace App\Livewire;

use App\Enums\LedgerEntryType;
use App\Models\User;
use App\Services\Wallet\InsufficientFunds;
use App\Services\Wallet\TransferNotAllowed;
use App\Services\Wallet\TransferService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * For World agents: send wallet balance to a customer, after checking who receives it.
 */
#[Title('Send balance')]
class TransferBalance extends Component
{
    public string $email = '';

    public string $amount = '';

    /**
     * The recipient's name, shown for a final check before sending.
     */
    #[Locked]
    public ?string $recipientName = null;

    /**
     * What the agent checked; sending anything else needs a new check.
     *
     * @var array{email: string, amount: string}|null
     */
    #[Locked]
    public ?array $reviewed = null;

    public ?string $notice = null;

    public function mount(): void
    {
        abort_unless($this->agent()->canSendBalance(), 403);
    }

    public function review(): void
    {
        $this->notice = null;
        $recipient = $this->recipient();

        $this->recipientName = $recipient?->name;
        $this->reviewed = $recipient === null ? null : ['email' => $this->email, 'amount' => $this->amount];
    }

    public function cancelReview(): void
    {
        $this->reset('recipientName', 'reviewed');
    }

    public function send(TransferService $transfers): void
    {
        if ($this->reviewed !== ['email' => $this->email, 'amount' => $this->amount]) {
            $this->review();

            return;
        }

        $recipient = $this->recipient();

        if ($recipient === null) {
            $this->cancelReview();

            return;
        }

        try {
            $transfers->send($this->agent(), $recipient, $this->amount);
        } catch (InsufficientFunds $exception) {
            $this->cancelReview();
            $this->addError('amount', __('Your balance is $:balance.', ['balance' => $exception->balance]));

            return;
        } catch (TransferNotAllowed $exception) {
            $this->cancelReview();
            $this->addError('email', $exception->getMessage());

            return;
        }

        $this->notice = __('Sent $:amount to :email.', ['amount' => WalletService::positiveAmount($this->amount), 'email' => $recipient->email]);
        $this->reset('email', 'amount', 'recipientName', 'reviewed');
    }

    public function render(WalletService $wallet): View
    {
        $agent = $this->agent();

        return view('livewire.transfer-balance', [
            'balance' => $wallet->balance($agent),
            'transfers' => $agent->ledgerEntries()->where('type', LedgerEntryType::TransferOut)->latest('id')->limit(10)->get(),
        ]);
    }

    /**
     * Validate the form and find the customer, or null with the errors shown.
     */
    private function recipient(): ?User
    {
        $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/', 'not_regex:/^0*(\.0*)?$/'],
        ], [
            'amount.regex' => __('Enter an amount in dollars, such as 25 or 12.50.'),
            'amount.not_regex' => __('Enter an amount in dollars, such as 25 or 12.50.'),
        ]);

        $recipient = User::query()->where('email', $this->email)->first();

        if ($recipient === null) {
            $this->addError('email', __('No customer has this email address. Ask them to sign up first.'));
        } elseif ($recipient->is($this->agent())) {
            $this->addError('email', __('You cannot send balance to your own account.'));
            $recipient = null;
        }

        return $recipient;
    }

    private function agent(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
