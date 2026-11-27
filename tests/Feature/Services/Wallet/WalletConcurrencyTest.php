<?php

use App\Enums\LedgerEntryType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CommitsDatabaseWrites;

// Other connections and processes only see committed rows.
uses(CommitsDatabaseWrites::class);

afterEach(function () {
    Schema::disableForeignKeyConstraints();
    foreach (['purchases', 'wallet_ledger', 'products', 'folders', 'users'] as $table) {
        DB::table($table)->truncate();
    }
    Schema::enableForeignKeyConstraints();
});

test('a debit holds the wallet lock while it checks the balance', function () {
    $user = User::factory()->create();
    app(WalletService::class)->credit($user, '15.00', LedgerEntryType::AdminCredit, 'test:seed');

    config(['database.connections.other_request' => config('database.connections.'.config('database.default'))]);
    $otherRequest = DB::connection('other_request');
    $walletWasLockedDuringBalanceCheck = null;

    // Right after the debit reads the balance, another request tries to take the same wallet without waiting.
    DB::listen(function (QueryExecuted $query) use ($otherRequest, $user, &$walletWasLockedDuringBalanceCheck): void {
        if ($query->connectionName === 'other_request' || ! str_contains($query->sql, 'SUM(amount)')) {
            return;
        }

        $otherRequest->beginTransaction();

        try {
            $otherRequest->select('select id from users where id = ? for update nowait', [$user->id]);
            $walletWasLockedDuringBalanceCheck = false;
        } catch (QueryException) {
            $walletWasLockedDuringBalanceCheck = true;
        } finally {
            $otherRequest->rollBack();
        }
    });

    app(WalletService::class)->debit($user, '10.00', LedgerEntryType::AdminDebit, 'test:debit');

    expect($walletWasLockedDuringBalanceCheck)->toBeTrue();
});

test('two purchases at the same moment can never overdraw the wallet', function () {
    $user = User::factory()->create();
    app(WalletService::class)->credit($user, '15.00', LedgerEntryType::AdminCredit, 'test:seed');
    [$first, $second] = Product::factory()->count(2)->published()->paid('10.00')->create()->modelKeys();
    // Each purchase runs in its own PHP process (sharing the test's environment), exactly as two requests would.
    $buyIn = fn (int $productId): array => ['php', '-r', sprintf(
        'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();'
        .' try { app(App\\Services\\Wallet\\PurchaseService::class)->buy(App\\Models\\User::findOrFail(%d), App\\Models\\Product::findOrFail(%d), "10.00"); echo "bought"; }'
        .' catch (App\\Services\\Wallet\\InsufficientFunds) { echo "not enough money"; }',
        $user->id,
        $productId,
    )];

    $results = Process::pool(fn (Pool $pool) => [
        $pool->path(base_path())->command($buyIn($first)),
        $pool->path(base_path())->command($buyIn($second)),
    ])->start()->wait()->collect()->map(fn ($result): string => trim($result->output()) ?: $result->errorOutput())->all();

    expect($results)->toEqualCanonicalizing(['bought', 'not enough money'])
        ->and(Purchase::query()->count())->toBe(1)
        ->and(app(WalletService::class)->balance($user))->toBe('5.00');
});
