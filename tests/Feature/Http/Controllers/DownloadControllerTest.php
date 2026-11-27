<?php

use App\Enums\DownloadStatus;
use App\Jobs\SendAdminAlert;
use App\Models\DownloadLog;
use App\Models\DownloadToken;
use App\Models\Product;
use App\Models\User;
use App\Services\SiteSettings;
use App\Services\Wallet\PurchaseService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Serve fake Drive file contents: file ID => [status, body, headers].
 *
 * @param  array<string, array{0: int, 1: string, 2?: array<string, string>}>  $files
 */
function fakeDriveFiles(array $files): void
{
    fakeGoogleDrive([
        'www.googleapis.com/drive/v3/files/*' => function (Request $request) use ($files) {
            $fileId = explode('?', basename($request->url()))[0];
            [$status, $body, $headers] = $files[$fileId] + [2 => []];

            return Http::response($body, $status, $headers);
        },
    ]);
}

function quotaExceededBody(): string
{
    return (string) json_encode(['error' => ['code' => 403, 'errors' => [['reason' => 'downloadQuotaExceeded']], 'message' => 'The download quota for this file has been exceeded.']]);
}

function downloadLinkFor(User $user, Product $product): string
{
    test()->actingAs($user)->post(route('downloads.store', $product))->assertRedirect();

    return route('downloads.stream', ['token' => DownloadToken::query()->latest('id')->firstOrFail()->token, 'fileName' => $product->file_name]);
}

test('only signed-in, verified customers who may have the file get a download link', function () {
    $paid = Product::factory()->published()->paid('40.00')->create();
    $free = Product::factory()->published()->create();

    $this->post(route('downloads.store', $free))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->post(route('downloads.store', $free))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs(User::factory()->create())
        ->from(route('products.show', $paid))
        ->post(route('downloads.store', $paid))
        ->assertRedirect(route('products.show', $paid))
        ->assertSessionHas('download_error', 'Buy this file to download it.');

    $this->actingAs(User::factory()->create())
        ->post(route('downloads.store', $free))
        ->assertRedirectContains('/dl/');

    $owner = customerWithBalance('40.00');
    app(PurchaseService::class)->buy($owner, $paid, '40.00');
    $this->actingAs($owner)->post(route('downloads.store', $paid))->assertRedirectContains('/dl/');

    expect(DownloadToken::query()->count())->toBe(2)
        ->and(DownloadLog::query()->count())->toBe(2);
});

test('the daily download limit is enforced', function () {
    app(SiteSettings::class)->save(['download_daily_limit_per_user' => '2']);
    $customer = User::factory()->create();
    $product = Product::factory()->published()->create();

    $this->actingAs($customer)->post(route('downloads.store', $product))->assertRedirectContains('/dl/');
    $this->actingAs($customer)->post(route('downloads.store', $product))->assertRedirectContains('/dl/');
    $this->actingAs($customer)->post(route('downloads.store', $product))
        ->assertSessionHas('download_error', "You have reached today's limit of 2 downloads. Please try again tomorrow.");

    expect(DownloadToken::query()->count())->toBe(2);
});

test('a file streams from Drive as an attachment and the bytes are counted', function () {
    $product = Product::factory()->published()->create(['drive_file_id' => 'primary-id', 'file_name' => 'A155F_COMBINATION.zip']);
    fakeDriveFiles(['primary-id' => [200, 'FIRMWARE-BYTES', ['Content-Length' => '14']]]);
    $link = downloadLinkFor(User::factory()->create(), $product);

    $response = $this->get($link);

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Length', '14')
        ->assertDownload('A155F_COMBINATION.zip');

    expect($response->streamedContent())->toBe('FIRMWARE-BYTES')
        ->and(DownloadToken::query()->sole()->bytes_served)->toBe(14)
        ->and($product->refresh()->download_count)->toBe(1);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'files/primary-id?') && str_contains($request->url(), 'alt=media'));
});

test('a resumed download passes the byte range to Drive and answers 206', function () {
    $product = Product::factory()->published()->create(['drive_file_id' => 'primary-id']);
    fakeDriveFiles(['primary-id' => [206, 'BYTES', ['Content-Length' => '5', 'Content-Range' => 'bytes 9-13/14']]]);
    $link = downloadLinkFor(User::factory()->create(), $product);

    $response = $this->withHeaders(['Range' => 'bytes=9-'])->get($link);

    $response->assertStatus(206)->assertHeader('Content-Range', 'bytes 9-13/14');
    expect($response->streamedContent())->toBe('BYTES');
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Range', 'bytes=9-'));
});

test('a download counts once per customer and file each day, however many times they click', function () {
    $customer = User::factory()->create();
    $product = Product::factory()->published()->create(['drive_file_id' => 'primary-id']);
    fakeDriveFiles(['primary-id' => [200, 'X']]);

    $this->get(downloadLinkFor($customer, $product))->streamedContent();
    $this->get(downloadLinkFor($customer, $product))->streamedContent();

    expect($product->refresh()->download_count)->toBe(1);
});

test('an expired link is refused', function () {
    $product = Product::factory()->published()->create();
    $link = downloadLinkFor(User::factory()->create(), $product);
    $this->travel(7)->hours();

    $this->get($link)->assertNotFound();
});

test('a link used from too many places is refused and the admins are alerted', function () {
    Queue::fake();
    app(SiteSettings::class)->save(['download_max_ips_per_token' => '2']);
    $product = Product::factory()->published()->create(['drive_file_id' => 'primary-id']);
    fakeDriveFiles(['primary-id' => [200, 'X']]);
    $link = downloadLinkFor(User::factory()->create(), $product);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->get($link)->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->get($link)->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->get($link)->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])->get($link)->assertForbidden();

    Queue::assertPushed(SendAdminAlert::class, fn (SendAdminAlert $job): bool => str_contains($job->message, 'too many IPs'));
});

test('when Google limits the main file, the mirror is used', function () {
    $product = Product::factory()->published()->create(['drive_file_id' => 'primary-id', 'mirror_drive_file_id' => 'mirror-id']);
    fakeDriveFiles([
        'primary-id' => [403, quotaExceededBody(), ['Content-Type' => 'application/json']],
        'mirror-id' => [200, 'FROM-MIRROR'],
    ]);

    $response = $this->get(downloadLinkFor(User::factory()->create(), $product));

    expect($response->streamedContent())->toBe('FROM-MIRROR')
        ->and(DownloadLog::query()->sole()->drive_file_used)->toBe('mirror');
});

test('when no copy can be downloaded the customer sees a busy page and the admins are alerted', function () {
    Queue::fake();
    $product = Product::factory()->published()->create(['drive_file_id' => 'primary-id', 'title' => 'Popular Firmware']);
    fakeDriveFiles(['primary-id' => [403, quotaExceededBody(), ['Content-Type' => 'application/json']]]);

    $this->get(downloadLinkFor(User::factory()->create(), $product))
        ->assertStatus(503)
        ->assertSee('This file is temporarily busy');

    expect(DownloadLog::query()->sole()->status)->toBe(DownloadStatus::QuotaExceeded)
        ->and($product->refresh()->download_count)->toBe(0);
    Queue::assertPushed(SendAdminAlert::class, fn (SendAdminAlert $job): bool => str_contains($job->message, 'Popular Firmware'));
});
