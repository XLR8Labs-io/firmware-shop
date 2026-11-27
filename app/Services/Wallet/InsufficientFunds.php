<?php

namespace App\Services\Wallet;

use RuntimeException;

class InsufficientFunds extends RuntimeException
{
    /**
     * @param  numeric-string  $balance
     * @param  numeric-string  $required
     */
    public function __construct(public readonly string $balance, public readonly string $required)
    {
        parent::__construct("The balance of \${$balance} is less than \${$required}.");
    }

    /**
     * @return numeric-string
     */
    public function shortfall(): string
    {
        return bcsub($this->required, $this->balance, 2);
    }
}
