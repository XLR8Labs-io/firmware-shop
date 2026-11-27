<?php

namespace App\Services\Drive;

final class DriveId
{
    /**
     * Take a Drive file or folder ID out of a share link, or accept a bare ID.
     *
     * Handles /file/d/{id}/view, /drive/folders/{id}, /drive/u/0/folders/{id} and ?id={id} links.
     */
    public static function fromInput(string $input): ?string
    {
        $input = trim($input);

        $patterns = [
            '~/d/([A-Za-z0-9_-]{10,})~',
            '~/folders/([A-Za-z0-9_-]{10,})~',
            '~[?&]id=([A-Za-z0-9_-]{10,})~',
            '~^([A-Za-z0-9_-]{10,})$~',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }
}
