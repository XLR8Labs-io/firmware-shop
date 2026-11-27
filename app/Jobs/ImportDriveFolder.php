<?php

namespace App\Jobs;

use App\Enums\DriveImportStatus;
use App\Enums\ProductStatus;
use App\Models\DriveImport;
use App\Models\Folder;
use App\Models\Product;
use App\Services\Drive\DriveClient;
use App\Services\Drive\DriveFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Brings the contents of a Drive folder into the catalogue: Drive folders become
 * site folders and files become draft products.
 *
 * Safe to run again: folders and files already imported (matched by their Drive ID)
 * are left exactly as admins have edited them.
 */
class ImportDriveFolder implements ShouldQueue
{
    use Queueable;

    /**
     * A large Drive tree takes a while to walk.
     */
    public int $timeout = 3600;

    /**
     * No automatic retry: the admin sees the error and can start the import again.
     */
    public int $tries = 1;

    public function __construct(public DriveImport $import) {}

    public function handle(DriveClient $drive): void
    {
        $this->import->update([
            'status' => DriveImportStatus::Running,
            'started_at' => now(),
            'error' => null,
        ]);

        /** @var list<array{string, int|null}> $pending Drive folder ID and the site folder its contents go into. */
        $pending = [[$this->import->drive_folder_id, $this->import->parent_folder_id]];

        while ($pending !== []) {
            [$driveFolderId, $siteFolderId] = array_pop($pending);

            $files = [];

            foreach ($drive->listChildren($driveFolderId) as $item) {
                if ($item->isFolder()) {
                    $pending[] = [$item->id, $this->siteFolderFor($item, $siteFolderId)->id];
                } elseif ($item->isDownloadable() && $siteFolderId !== null) {
                    $files[] = $item;
                } else {
                    $this->import->files_skipped++;
                }
            }

            if ($siteFolderId !== null) {
                $this->importFiles($files, $siteFolderId);
            }

            // Save the counts after each Drive folder so the admin panel shows progress.
            $this->import->save();
        }

        $this->import->update([
            'status' => DriveImportStatus::Finished,
            'finished_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->update([
            'status' => DriveImportStatus::Failed,
            'error' => $exception?->getMessage() ?? 'The import stopped without an error message.',
            'finished_at' => now(),
        ]);
    }

    private function siteFolderFor(DriveFile $driveFolder, ?int $parentId): Folder
    {
        $folder = Folder::query()->createOrFirst(
            ['drive_folder_id' => $driveFolder->id],
            ['name' => $driveFolder->name, 'parent_id' => $parentId],
        );

        if ($folder->wasRecentlyCreated) {
            $this->import->folders_created++;
        }

        return $folder;
    }

    /**
     * @param  list<DriveFile>  $files
     */
    private function importFiles(array $files, int $siteFolderId): void
    {
        if ($files === []) {
            return;
        }

        // One query per Drive folder; deleted products count, so a deleted file is not brought back.
        $alreadyImported = Product::withTrashed()
            ->whereIn('drive_file_id', array_map(fn (DriveFile $file): string => $file->id, $files))
            ->pluck('drive_file_id')
            ->flip();

        foreach ($files as $file) {
            if ($alreadyImported->has($file->id)) {
                $this->import->products_skipped++;

                continue;
            }

            $product = Product::query()->createOrFirst(['drive_file_id' => $file->id], [
                'folder_id' => $siteFolderId,
                'title' => $file->title(),
                'price' => $this->import->default_price,
                'status' => ProductStatus::Draft,
                'file_name' => $file->name,
                'file_size' => $file->size,
                'md5' => $file->md5,
                'mime_type' => $file->mimeType,
            ]);

            $product->wasRecentlyCreated
                ? $this->import->products_created++
                : $this->import->products_skipped++;
        }
    }
}
