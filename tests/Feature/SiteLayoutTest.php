<?php

use App\Models\User;
use App\Services\SiteSettings;

test('text from the settings is escaped', function (string $setting) {
    app(SiteSettings::class)->save([
        $setting => '<script>alert("x")</script>',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('&lt;script&gt;', escape: false)
        ->assertDontSee('<script>alert("x")</script>', escape: false);
})->with(['site_name', 'ticker_text', 'support_contact_text']);

test('the header logo is named after the site', function () {
    app(SiteSettings::class)->save(['site_name' => 'Firmware Hub']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('alt="Firmware Hub"', escape: false)
        ->assertSee(asset('logos/logo-light-400.webp'));
});

test('the ticker is left out when no notice is set', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('aria-label="Notices"', escape: false);
});

test('only community links that are filled in are shown', function () {
    app(SiteSettings::class)->save([
        'telegram_channel_url' => 'https://t.me/firmwarehub',
        'facebook_url' => null,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Telegram channel')
        ->assertDontSee('Facebook');
});

test('guests are offered log in and register', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('login'))
        ->assertSee(route('register'));
});

test('admins see a link to the admin panel', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('filament.admin.pages.dashboard'));
});

test('customers do not see a link to the admin panel', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee(route('filament.admin.pages.dashboard'));
});
