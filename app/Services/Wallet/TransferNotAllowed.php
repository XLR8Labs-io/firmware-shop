<?php

namespace App\Services\Wallet;

use RuntimeException;

class TransferNotAllowed extends RuntimeException
{
    public static function notAnAgent(): self
    {
        return new self('Only active agents can send balance.');
    }

    public static function toSelf(): self
    {
        return new self('You cannot send balance to your own account.');
    }
}
