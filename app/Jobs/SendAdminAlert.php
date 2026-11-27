<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sends a message to the admins' Telegram chat. Queued, so a slow or failing
 * Telegram never holds up the request that raised the alert.
 */
class SendAdminAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public string $message) {}

    public function handle(): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.admin_chat_id');

        if (blank($botToken) || blank($chatId)) {
            return;
        }

        Http::asForm()
            ->timeout(15)
            ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => Str::limit($this->message, 4000),
            ])
            ->throw();
    }
}
