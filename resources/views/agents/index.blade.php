<x-layouts::site :title="__('World agents')" :description="__('Local agents who sell wallet balance in your own currency.')">
    <div class="mx-auto w-full max-w-6xl space-y-6 px-4 py-10 sm:px-6">
        <div class="max-w-3xl space-y-2">
            <h1 class="text-2xl font-semibold">{{ __('World agents') }}</h1>
            <p class="text-zinc-600 dark:text-zinc-400">{{ __('Pay a local agent in your own currency and they add the balance to your account. Tell them the email address you signed up with.') }}</p>
        </div>

        @if ($agents->isEmpty())
            <p class="text-zinc-600 dark:text-zinc-400">{{ __('There are no agents listed yet.') }}</p>
        @else
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($agents as $agent)
                    <li class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div>
                            <p class="font-medium break-words">{{ $agent->name }}</p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $agent->country }}</p>
                        </div>
                        @if ($agent->payment_methods)
                            <p class="text-sm whitespace-pre-line">{{ __('Accepts: :methods', ['methods' => $agent->payment_methods]) }}</p>
                        @endif
                        <p class="flex flex-wrap gap-x-4 gap-y-1 text-sm font-medium">
                            @if ($agent->whatsappUrl())
                                <a href="{{ $agent->whatsappUrl() }}" target="_blank" rel="noopener noreferrer" class="text-accent-content hover:underline">{{ __('WhatsApp') }} {{ $agent->whatsapp }}</a>
                            @endif
                            @if ($agent->telegramUrl())
                                <a href="{{ $agent->telegramUrl() }}" target="_blank" rel="noopener noreferrer" class="text-accent-content hover:underline">{{ __('Telegram') }} {{ '@'.ltrim($agent->telegram, '@') }}</a>
                            @elseif ($agent->telegram)
                                <span>{{ __('Telegram') }} {{ $agent->telegram }}</span>
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts::site>
