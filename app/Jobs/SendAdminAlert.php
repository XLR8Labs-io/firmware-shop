<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

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

    /**
     * Error monitoring: tell the admins about an unexpected error in production, at
     * most once an hour for the same error. It is still logged as usual.
     */
    public static function aboutError(Throwable $error): void
    {
        if (! app()->isProduction()) {
            return;
        }

        try {
            $place = Str::after($error->getFile(), base_path().'/').':'.$error->getLine();

            if (! Cache::add('error-alert:'.md5($error::class.$place), true, now()->addHour())) {
                return;
            }

            $where = app()->runningInConsole() ? 'in a scheduled task or job' : 'on '.request()->method().' /'.request()->path();

            self::dispatch('Server error '.$where.': '.class_basename($error).': '.Str::limit($error->getMessage(), 300)." ({$place})");
        } catch (Throwable) {
            // The database may be the thing that failed; the log still has the error.
        }
    }

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
