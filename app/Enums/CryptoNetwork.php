<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CryptoNetwork: string implements HasLabel
{
    case Trc20 = 'trc20';

    case Bep20 = 'bep20';

    public function getLabel(): string
    {
        return (string) config("crypto.networks.{$this->value}.label");
    }

    public function address(): ?string
    {
        $address = config("crypto.networks.{$this->value}.address");

        return is_string($address) && $address !== '' ? $address : null;
    }
}
