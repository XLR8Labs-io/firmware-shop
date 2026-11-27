<?php

use App\Services\Crypto\Hex;

test('hex numbers of any size become exact decimals', function (string $hex, string $decimal) {
    expect(Hex::toDec($hex))->toBe($decimal);
})->with([
    'zero' => ['0x0', '0'],
    'empty data' => ['0x', '0'],
    'leading zeros' => ['0x000000000000000000000000000000000000000000000000000000000000000f', '15'],
    '10 USDT with 18 decimals' => ['0x8ac7230489e80000', '10000000000000000000'],
    'largest uint256' => ['0x'.str_repeat('f', 64), '115792089237316195423570985008687907853269984665640564039457584007913129639935'],
]);

test('something that is not hex is refused', function () {
    expect(fn () => Hex::toDec('0xnothex'))->toThrow(InvalidArgumentException::class);
});
