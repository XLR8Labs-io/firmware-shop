<?php

use App\Models\Reseller;

test('active agents are listed with ways to contact them; inactive ones are hidden', function () {
    Reseller::factory()->create([
        'name' => 'Dhaka Mobile Care',
        'country' => 'Bangladesh',
        'whatsapp' => '+880 1711-000000',
        'telegram' => '@dhakamobile',
    ]);
    Reseller::factory()->inactive()->create(['name' => 'Closed Shop']);

    $this->get(route('agents.index'))
        ->assertOk()
        ->assertSee('Dhaka Mobile Care')
        ->assertSee('https://wa.me/8801711000000')
        ->assertSee('https://t.me/dhakamobile')
        ->assertDontSee('Closed Shop');
});

test('a Telegram entry that is not a username is shown as text, not as a link', function () {
    Reseller::factory()->create(['telegram' => 'javascript:alert(1)']);

    $this->get(route('agents.index'))
        ->assertOk()
        ->assertDontSee('href="javascript', escape: false)
        ->assertDontSee('https://t.me/', escape: false);
});
