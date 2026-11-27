<?php

namespace App\Services\Crypto;

use InvalidArgumentException;

final class Hex
{
    /**
     * Convert a hex number of any size (uint256 token amounts don't fit PHP integers) to decimal.
     *
     * @return numeric-string
     */
    public static function toDec(string $hex): string
    {
        $digits = str_starts_with($hex, '0x') ? substr($hex, 2) : $hex;

        if (preg_match('/^[0-9a-fA-F]*$/', $digits) !== 1) {
            throw new InvalidArgumentException("\"{$hex}\" is not a hex number.");
        }

        $decimal = '0';

        foreach (str_split(ltrim($digits, '0')) as $digit) {
            $decimal = bcadd(bcmul($decimal, '16'), (string) intval($digit, 16));
        }

        return $decimal;
    }
}
