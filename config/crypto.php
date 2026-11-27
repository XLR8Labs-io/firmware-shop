<?php

/*
 * USDT deposits paid straight to the store's own wallets. Only public receiving
 * addresses live here: never put a private key or seed phrase on the server.
 * Check both contract addresses against tether.to, tronscan.org and bscscan.com
 * before launch; fake tokens also call themselves "USDT".
 */
return [
    'min_deposit' => env('CRYPTO_MIN_USD', 5),

    // How long the payment page shows the invoice as open.
    'invoice_ttl_minutes' => 60,

    // How long a pay amount stays reserved; late payments in this window still match.
    'reserve_hours' => 24,

    'networks' => [
        'trc20' => [
            'label' => 'USDT (TRC20 / TRON)',
            'address' => env('CRYPTO_TRON_ADDRESS'),
            'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'decimals' => 6,
            'api' => 'https://api.trongrid.io',
            'api_key' => env('TRONGRID_API_KEY'),
        ],
        'bep20' => [
            'label' => 'USDT (BEP20 / BNB Smart Chain)',
            'address' => strtolower((string) env('CRYPTO_BSC_ADDRESS')),
            'contract' => '0x55d398326f99059ff775485246999027b3197955',
            'decimals' => 18,
            'rpc' => env('BSC_RPC_URL', 'https://bsc-dataseed.bnbchain.org'),
            'confirmations' => 15,
        ],
    ],
];
