<?php

namespace App\Services\Drive;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A Google Drive failure, with a message an admin can act on.
 */
class DriveException extends RuntimeException
{
    /**
     * Google locks a file for a while after many downloads ("downloadQuotaExceeded"),
     * service accounts included. 429 is its general rate limit.
     */
    public static function isQuotaLimit(Response $response): bool
    {
        if ($response->status() === 429) {
            return true;
        }

        return $response->status() === 403
            && $response->json('error.errors.0.reason') === 'downloadQuotaExceeded';
    }

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
