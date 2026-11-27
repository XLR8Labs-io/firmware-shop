<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Sign in as an admin who can open the panel (verified email, confirmed two-factor).
 */
function actingAsAdmin(): User
{
    $admin = User::factory()->admin()->withTwoFactor()->create();

    test()->actingAs($admin);

    return $admin;
}

/**
 * Point the Drive client at a throwaway service account key and fake Google's token
 * endpoint, plus any Drive responses given. Any other outgoing request fails the test.
 *
 * @param  array<string, mixed>  $driveResponses
 */
function fakeGoogleDrive(array $driveResponses = []): void
{
    static $keyFile = null;

    if ($keyFile === null) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);

        $keyFile = tempnam(sys_get_temp_dir(), 'google-sa-');
        file_put_contents($keyFile, json_encode([
            'type' => 'service_account',
            'client_email' => 'store@test-project.iam.gserviceaccount.com',
            'client_id' => '1234567890',
            'private_key_id' => 'test-key',
            'private_key' => $privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
    }

    config([
        'services.google_drive.credentials' => $keyFile,
        'services.google_drive.shared_drive_id' => 'shared-drive-id',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token', 'expires_in' => 3599, 'token_type' => 'Bearer']),
        ...$driveResponses,
    ]);
}
