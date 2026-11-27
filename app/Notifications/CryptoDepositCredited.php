<?php

namespace App\Notifications;

use App\Models\CryptoInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CryptoDepositCredited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CryptoInvoice $invoice) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your crypto deposit was added to your wallet'))
            ->line(__('We received :amount USDT on :network and added $:amount to your wallet.', [
                'amount' => $this->invoice->pay_amount,
                'network' => $this->invoice->network->getLabel(),
            ]))
            ->action(__('See your wallet'), route('account.wallet'));
    }
}
