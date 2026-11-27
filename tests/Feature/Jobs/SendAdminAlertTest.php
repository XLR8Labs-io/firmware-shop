<?php

use App\Jobs\SendAdminAlert;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('an alert is sent to the admin chat through the Telegram Bot API', function () {
    config(['services.telegram.bot_token' => '123:ABC', 'services.telegram.admin_chat_id' => '-100200']);
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

    (new SendAdminAlert('Quota reached for "Popular Firmware"'))->handle();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.telegram.org/bot123:ABC/sendMessage'
        && $request['chat_id'] === '-100200'
        && $request['text'] === 'Quota reached for "Popular Firmware"');
});

test('nothing is sent when Telegram is not set up', function () {
    config(['services.telegram.bot_token' => null, 'services.telegram.admin_chat_id' => null]);
    Http::preventStrayRequests();
    Http::fake();

    (new SendAdminAlert('Anything'))->handle();

    Http::assertNothingSent();
});
