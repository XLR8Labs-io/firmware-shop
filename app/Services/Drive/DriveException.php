<?php

namespace App\Services\Drive;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A Google Drive failure, with a message an admin can act on.
 */
class DriveException extends RuntimeException
{
    public static function fromResponse(Response $response): self
    {
        if ($response->status() === 404) {
            return new self('Google Drive could not find that file or folder. Check the link, and that the service account is a member of the Shared Drive.', 404);
        }

        $detail = $response->json('error.message');

        return new self(
            sprintf('Google Drive returned an error (%d): %s', $response->status(), is_string($detail) ? $detail : 'no details given.'),
            $response->status(),
        );
    }
}
