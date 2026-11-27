<?php

use App\Enums\DriveImportStatus;
use App\Enums\ProductStatus;
use App\Jobs\ImportDriveFolder;
use App\Models\DriveImport;
use App\Models\Folder;
use App\Models\Product;
use App\Services\Drive\DriveException;
use App\Services\Drive\DriveFile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Serve a fake Drive tree: Drive folder ID => the items directly inside it.
 * The tree is read by reference, so a test can change Drive between imports.
 *
 * @param  array<string, list<array<string, string>>>  $tree
 */
function fakeDriveTree(array &$tree): void
{
    fakeGoogleDrive([
        'www.googleapis.com/drive/v3/files?*' => function (Request $request) use (&$tree) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            preg_match("/^'([^']+)' in parents/", (string) ($query['q'] ?? ''), $matches);

            return Http::response(['files' => $tree[$matches[1] ?? ''] ?? []]);
        },
    ]);
}

function driveFolder(string $id, string $name): array
{
    return ['id' => $id, 'name' => $name, 'mimeType' => DriveFile::FOLDER_MIME_TYPE];
}

function driveFile(string $id, string $name, string $mimeType = 'application/zip'): array
{
    return ['id' => $id, 'name' => $name, 'mimeType' => $mimeType, 'size' => '1048576', 'md5Checksum' => md5($id)];
}

function samsungTree(): array
{
    return [
        'drive-root' => [driveFolder('drive-samsung', 'Samsung'), driveFile('file-readme', 'README.zip')],
        'drive-samsung' => [driveFolder('drive-a15', 'Galaxy A15'), driveFile('file-tool', 'SAMSUNG_TOOL.zip')],
        'drive-a15' => [
            driveFile('file-a15', 'A155F_COMBINATION.zip'),
            driveFile('file-notes', 'Flashing notes', 'application/vnd.google-apps.document'),
        ],
    ];
}

test('the Drive tree becomes site folders and draft products', function () {
    $tree = samsungTree();
    fakeDriveTree($tree);
    $import = DriveImport::factory()->create(['drive_folder_id' => 'drive-root', 'default_price' => '15.00']);

    ImportDriveFolder::dispatchSync($import);

    $samsung = Folder::query()->where('drive_folder_id', 'drive-samsung')->sole();
    $galaxyA15 = Folder::query()->where('drive_folder_id', 'drive-a15')->sole();
    $combination = Product::query()->where('drive_file_id', 'file-a15')->sole();

    expect($samsung->parent_id)->toBeNull()
        ->and($samsung->name)->toBe('Samsung')
        ->and($galaxyA15->parent_id)->toBe($samsung->id)
        ->and($combination->folder_id)->toBe($galaxyA15->id)
        ->and($combination->title)->toBe('A155F_COMBINATION')
        ->and($combination->file_name)->toBe('A155F_COMBINATION.zip')
        ->and($combination->file_size)->toBe(1048576)
        ->and($combination->md5)->toBe(md5('file-a15'))
        ->and($combination->status)->toBe(ProductStatus::Draft)
        ->and($combination->price)->toBe('15.00')
        ->and(Product::query()->pluck('drive_file_id')->sort()->values()->all())->toBe(['file-a15', 'file-tool']);

    $import->refresh();

    expect($import->status)->toBe(DriveImportStatus::Finished)
        ->and($import->folders_created)->toBe(2)
        ->and($import->products_created)->toBe(2)
        ->and($import->files_skipped)->toBe(2)
        ->and($import->finished_at)->not->toBeNull();
});

test('files directly in the Drive folder go into the chosen site folder', function () {
    $tree = ['drive-tools' => [driveFile('file-setup', 'Flash64_Setup.zip')]];
    fakeDriveTree($tree);
    $tools = Folder::factory()->create(['name' => 'Box and Dongle Setups']);
    $import = DriveImport::factory()->create(['drive_folder_id' => 'drive-tools', 'parent_folder_id' => $tools->id]);

    ImportDriveFolder::dispatchSync($import);

    expect(Product::query()->where('drive_file_id', 'file-setup')->sole()->folder_id)->toBe($tools->id)
        ->and($import->refresh()->files_skipped)->toBe(0);
});

test('importing again only adds what is new and keeps admin changes', function () {
    $tree = samsungTree();
    fakeDriveTree($tree);
    ImportDriveFolder::dispatchSync(DriveImport::factory()->create(['drive_folder_id' => 'drive-root']));

    Folder::query()->where('drive_folder_id', 'drive-samsung')->sole()->update(['name' => 'Samsung Galaxy']);
    Product::query()->where('drive_file_id', 'file-tool')->sole()->delete();

    $tree['drive-a15'][] = driveFile('file-a15-new', 'A155F_U3_FIRMWARE.zip');
    $secondImport = DriveImport::factory()->create(['drive_folder_id' => 'drive-root']);

    ImportDriveFolder::dispatchSync($secondImport);

    $secondImport->refresh();

    expect($secondImport->folders_created)->toBe(0)
        ->and($secondImport->products_created)->toBe(1)
        ->and($secondImport->products_skipped)->toBe(2)
        ->and(Folder::query()->where('drive_folder_id', 'drive-samsung')->sole()->name)->toBe('Samsung Galaxy')
        ->and(Product::query()->where('drive_file_id', 'file-tool')->exists())->toBeFalse()
        ->and(Product::withTrashed()->where('drive_file_id', 'file-tool')->count())->toBe(1);
});

test('a Drive error marks the import as failed with the reason', function () {
    fakeGoogleDrive([
        'www.googleapis.com/drive/v3/files?*' => Http::response(['error' => ['code' => 404, 'message' => 'File not found.']], 404),
    ]);
    $import = DriveImport::factory()->create(['drive_folder_id' => 'drive-missing']);

    expect(fn () => ImportDriveFolder::dispatchSync($import))->toThrow(DriveException::class);

    $import->refresh();

    expect($import->status)->toBe(DriveImportStatus::Failed)
        ->and($import->error)->toContain('Google Drive could not find that file or folder.');
});
