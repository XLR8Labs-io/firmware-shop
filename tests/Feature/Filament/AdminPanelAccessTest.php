<?php

use App\Models\User;

test('guests are sent to the site login page', function () {
    $this->get(route('filament.admin.pages.dashboard'))
        ->assertRedirect(route('login'));
});

test('the panel has no login page of its own, so the two-factor challenge cannot be skipped', function () {
    $this->get('/admin/login')->assertNotFound();
});

test('customers are refused', function () {
    $customer = User::factory()->withTwoFactor()->create();

    $this->actingAs($customer)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertForbidden();
});

test('admins without two-factor authentication are sent to the security settings', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertRedirect(route('security.edit'));
});

test('admins who have not confirmed their two-factor setup are sent to the security settings', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create([
        'two_factor_confirmed_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertRedirect(route('security.edit'));
});

test('admins with an unverified email address are sent to verify it', function () {
    $admin = User::factory()->admin()->withTwoFactor()->unverified()->create();

    $this->actingAs($admin)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('admins with confirmed two-factor authentication can open the panel', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create();

    $this->actingAs($admin)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertOk();
});

test('the security settings tell admins without two-factor authentication why they were sent there', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Admin accounts need two-factor authentication');
});

test('the security settings do not show the admin notice to customers', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertDontSee('Admin accounts need two-factor authentication');
});
