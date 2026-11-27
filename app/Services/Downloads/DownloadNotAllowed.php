<?php

namespace App\Services\Downloads;

use RuntimeException;

class DownloadNotAllowed extends RuntimeException
{
    public static function notAvailable(): self
    {
        return new self('This file is not available.');
    }

    public static function banned(?string $reason): self
    {
        return new self(filled($reason) ? "Your account cannot download files: {$reason}" : 'Your account cannot download files. Please contact support.');
    }

    public static function notOwned(): self
    {
        return new self('Buy this file to download it.');
    }

    public static function dailyLimitReached(int $limit): self
    {
        return new self("You have reached today's limit of {$limit} downloads. Please try again tomorrow.");
    }
}
