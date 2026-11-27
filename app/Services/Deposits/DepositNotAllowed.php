<?php

namespace App\Services\Deposits;

use RuntimeException;

class DepositNotAllowed extends RuntimeException
{
    /**
     * @param  string  $field  The form field the problem belongs to.
     */
    public function __construct(string $message, public readonly string $field = 'amountBdt')
    {
        parent::__construct($message);
    }

    public static function closed(string $method): self
    {
        return new self("{$method} deposits are not available right now. Please contact support.");
    }

    public static function belowMinimum(string $minimumBdt): self
    {
        return new self("The smallest deposit is {$minimumBdt} BDT.");
    }

    public static function alreadySubmitted(): self
    {
        return new self('This TrxID has already been submitted.', 'trxId');
    }

    public static function alreadyReviewed(): self
    {
        return new self('This deposit has already been reviewed.');
    }

    public static function gatewayUnavailable(): self
    {
        return new self('Online payment is not available right now. Please try again shortly, or pay manually below.');
    }
}
