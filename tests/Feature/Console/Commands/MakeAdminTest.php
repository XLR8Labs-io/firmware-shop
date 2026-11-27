<?php

use App\Models\User;

test('an existing user is made an admin', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('app:make-admin', ['email' => 'owner@example.com'])
        ->expectsOutputToContain('owner@example.com is now an admin.')
        ->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeTrue();
});

test('an unknown email address fails without changing anyone', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('app:make-admin', ['email' => 'nobody@example.com'])
        ->expectsOutputToContain('No user has that email address.')
        ->assertFailed();

    expect($user->refresh()->is_admin)->toBeFalse();
});
