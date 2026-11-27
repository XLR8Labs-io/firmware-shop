<?php

use App\Filament\Pages\ManageSiteSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\SiteSettings;
use Livewire\Livewire;

test('customers cannot open the settings page', function () {
    $customer = User::factory()->withTwoFactor()->create();

    $this->actingAs($customer)
        ->get(ManageSiteSettings::getUrl())
        ->assertForbidden();
});

test('saved settings are stored and shown on the site straight away', function () {
    $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

    // Render the site first so the settings cache is warm before saving.
    $this->get(route('home'))->assertDontSee('Firmware Hub');

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_name' => 'Firmware Hub',
            'ticker_text' => 'Back up the Security partition before flashing.',
            'support_contact_text' => 'Support replies within 24 hours.',
            'telegram_channel_url' => 'https://t.me/firmwarehub',
            'whatsapp_number' => '+8801712345678',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Settings saved');

    expect(Setting::query()->pluck('value', 'name')->all())->toMatchArray([
        'site_name' => 'Firmware Hub',
        'ticker_text' => 'Back up the Security partition before flashing.',
        'support_contact_text' => 'Support replies within 24 hours.',
        'telegram_channel_url' => 'https://t.me/firmwarehub',
        'whatsapp_number' => '+8801712345678',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Firmware Hub')
        ->assertSee('Back up the Security partition before flashing.')
        ->assertSee('Support replies within 24 hours.')
        ->assertSee('https://t.me/firmwarehub')
        ->assertSee('https://wa.me/8801712345678');
});

test('the form opens with the saved settings', function () {
    app(SiteSettings::class)->save([
        'site_name' => 'Firmware Hub',
        'youtube_url' => 'https://youtube.com/@firmwarehub',
    ]);

    $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

    Livewire::test(ManageSiteSettings::class)
        ->assertSchemaStateSet([
            'site_name' => 'Firmware Hub',
            'youtube_url' => 'https://youtube.com/@firmwarehub',
        ]);
});

test('the site name starts as the name the site already shows', function () {
    config(['app.name' => 'IT-Firmware.com']);

    $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

    Livewire::test(ManageSiteSettings::class)
        ->assertSchemaStateSet(['site_name' => 'IT-Firmware.com']);
});

test('invalid settings are rejected and nothing is saved', function (array $data, array $errors) {
    $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

    Livewire::test(ManageSiteSettings::class)
        ->fillForm([
            'site_name' => 'Firmware Hub',
            ...$data,
        ])
        ->call('save')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();

    expect(Setting::query()->count())->toBe(0);
})->with([
    'site name is required' => [['site_name' => null], ['site_name' => 'required']],
    'links cannot run scripts' => [['facebook_url' => 'javascript:alert(1)'], ['facebook_url']],
    'links must be web addresses' => [['telegram_group_url' => 'ftp://example.com/files'], ['telegram_group_url']],
    'WhatsApp number must be digits with the country code' => [['whatsapp_number' => '01712-345678'], ['whatsapp_number' => 'regex']],
]);
