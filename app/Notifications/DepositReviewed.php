<?php

namespace App\Notifications;

use App\Enums\DepositStatus;
use App\Models\MfsDeposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the customer whether their bKash or Nagad deposit was added to their wallet.
 */
class DepositReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MfsDeposit $deposit) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $method = $this->deposit->method->getLabel();
        $trxId = $this->deposit->trx_id;

        if ($this->deposit->status === DepositStatus::Approved) {
            return (new MailMessage)
                ->subject(__('Your :method deposit was added to your wallet', ['method' => $method]))
                ->line(__('We added $:amount to your wallet for :method TrxID :trx.', ['amount' => $this->deposit->amount_usd, 'method' => $method, 'trx' => $trxId]))
                ->action(__('See your wallet'), route('account.wallet'));
        }

        return (new MailMessage)
            ->subject(__('Your :method deposit was not approved', ['method' => $method]))
            ->line(__('We could not approve your :method deposit with TrxID :trx.', ['method' => $method, 'trx' => $trxId]))
            ->line(__('Reason: :reason', ['reason' => (string) $this->deposit->admin_note]))
            ->line(__('If you think this is a mistake, reply to this email or contact support with your TrxID.'));
    }
}
