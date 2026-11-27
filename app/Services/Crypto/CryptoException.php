<?php

namespace App\Services\Crypto;

use RuntimeException;

class CryptoException extends RuntimeException
{
    public static function closed(string $network): self
    {
        return new self("{$network} deposits are not available right now. Please contact support.");
    }

    public static function belowMinimum(string $minimum): self
    {
        return new self("The smallest crypto deposit is \${$minimum}.");
    }

    public static function noFreeAmount(): self
    {
        return new self('Too many open deposits for this amount. Please try a slightly different amount.');
    }
}
