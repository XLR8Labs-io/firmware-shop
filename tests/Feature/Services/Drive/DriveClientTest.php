<?php

use App\Services\Drive\DriveClient;
use App\Services\Drive\DriveException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * @return array<string, string>
 */
function queryOf(Request $request): array
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return $query;
}

test("a file's details are read from the Shared Drive as the service account", function () {
    fakeGoogleDrive([
        'www.googleapis.com/drive/v3/files/*' => Http::response([
            'id' => '1AbCdEfGhIjK',
            'name' => 'A155F_COMBINATION.zip',
            'mimeType' => 'application/zip',
            'size' => '2147483648',
            'md5Checksum' => '0cc175b9c0f1b6a831c399e269772661',
        ]),
    ]);

    $file = app(DriveClient::class)->metadata('1AbCdEfGhIjK');

    expect($file->name)->toBe('A155F_COMBINATION.zip')
        ->and($file->size)->toBe(2147483648)
        ->and($file->md5)->toBe('0cc175b9c0f1b6a831c399e269772661')
        ->and($file->isDownloadable())->toBeTrue();

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://www.googleapis.com/drive/v3/files/1AbCdEfGhIjK?')
        && $request->hasHeader('Authorization', 'Bearer test-access-token')
        && queryOf($request)['supportsAllDrives'] === 'true'
        && queryOf($request)['fields'] === 'id,name,mimeType,size,md5Checksum');
});

test('the service account signs in with a signed JWT once and reuses the token', function () {
    fakeGoogleDrive([
        'www.googleapis.com/drive/v3/files/*' => Http::response(['id' => '1AbCdEfGhIjK', 'name' => 'a.zip', 'mimeType' => 'application/zip']),
    ]);

    app(DriveClient::class)->metadata('1AbCdEfGhIjK');
    app(DriveClient::class)->metadata('1AbCdEfGhIjK');

    $tokenRequests = Http::recorded(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token');

    expect($tokenRequests)->toHaveCount(1)
        ->and($tokenRequests->first()[0]->body())->toContain('grant_type=urn%3Aietf%3Aparams%3Aoauth%3Agrant-type%3Ajwt-bearer');
});

test('a folder is listed page by page from the Shared Drive', function () {
    fakeGoogleDrive([
        'www.googleapis.com/drive/v3/files?*' => Http::sequence()
            ->push(['files' => [['id' => 'file-1', 'name' => 'one.zip', 'mimeType' => 'application/zip']], 'nextPageToken' => 'page-2'])
            ->push(['files' => [['id' => 'file-2', 'name' => 'two.zip', 'mimeType' => 'application/zip']]]),
    ]);

    $names = collect(app(DriveClient::class)->listChildren('folder-123'))->map->name->all();

    expect($names)->toBe(['one.zip', 'two.zip']);

    $listRequests = Http::recorded(fn (Request $request): bool => str_starts_with($request->url(), 'https://www.googleapis.com/drive/v3/files?'))
        ->map(fn (array $pair): array => queryOf($pair[0]))
        ->values();

    expect($listRequests[0])->toMatchArray([
        'q' => "'folder-123' in parents and trashed = false",
        'corpora' => 'drive',
        'driveId' => 'shared-drive-id',
        'supportsAllDrives' => 'true',
        'includeItemsFromAllDrives' => 'true',
        'pageSize' => '1000',
    ])->not->toHaveKey('pageToken')
        ->and($listRequests[1]['pageToken'])->toBe('page-2');
});

test('a missing key file stops with a message saying what to set', function () {
    config(['services.google_drive.credentials' => '/no/such/google-sa.json']);
    Http::preventStrayRequests();

    expect(fn () => app(DriveClient::class)->metadata('1AbCdEfGhIjK'))
        ->toThrow(DriveException::class, 'GOOGLE_SA_JSON');
});

test('a file Google cannot find gives a message an admin can act on', function () {
    fakeGoogleDrive([
        'www.googleapis.com/drive/v3/files/*' => Http::response(['error' => ['code' => 404, 'message' => 'File not found: 1AbCdEfGhIjK.']], 404),
    ]);

    expect(fn () => app(DriveClient::class)->metadata('1AbCdEfGhIjK'))
        ->toThrow(DriveException::class, 'Google Drive could not find that file or folder.');
});
