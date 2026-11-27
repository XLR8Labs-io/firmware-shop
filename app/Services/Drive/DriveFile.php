<?php

namespace App\Services\Drive;

/**
 * The fields of a Drive file resource the store uses.
 */
final readonly class DriveFile
{
    public const string FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';

    public function __construct(
        public string $id,
        public string $name,
        public string $mimeType,
        public int $size,
        public ?string $md5,
    ) {}

    /**
     * Drive sends `size` as a string, and leaves out `size` and `md5Checksum`
     * for folders and other files without binary content.
     *
     * @param  array<string, mixed>  $file
     */
    public static function fromApi(array $file): self
    {
        return new self(
            id: (string) ($file['id'] ?? ''),
            name: (string) ($file['name'] ?? ''),
            mimeType: (string) ($file['mimeType'] ?? ''),
            size: (int) ($file['size'] ?? 0),
            md5: isset($file['md5Checksum']) ? (string) $file['md5Checksum'] : null,
        );
    }

    public function isFolder(): bool
    {
        return $this->mimeType === self::FOLDER_MIME_TYPE;
    }

    /**
     * Google Docs, Sheets, shortcuts and other Google-native items have no file to download.
     */
    public function isDownloadable(): bool
    {
        return ! str_starts_with($this->mimeType, 'application/vnd.google-apps.');
    }

    /**
     * The file name without its extension, used as a starting title.
     */
    public function title(): string
    {
        $title = pathinfo($this->name, PATHINFO_FILENAME);

        return $title === '' ? $this->name : $title;
    }
}
