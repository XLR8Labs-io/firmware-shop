<?php

namespace App\Services\Wallet;

use RuntimeException;

class PurchaseNotAllowed extends RuntimeException
{
    public static function notForSale(): self
    {
        return new self('This file is not for sale.');
    }

    public static function priceChanged(string $currentPrice): self
    {
        return new self("The price has changed to \${$currentPrice}. Check it and confirm again.");
    }

    public static function notRefundable(): self
    {
        return new self('Only an active purchase can be refunded.');
    }
}
