<?php

namespace App\Services\Wallet;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * World agents sell balance locally and send it to their customers' wallets.
 */
class TransferService
{
    public function __construct(private WalletService $wallet) {}

    /**
     * Move money from an agent's wallet to a customer's. Both entries share one reference
     * and are written together, or not at all.
     *
     * @return LedgerEntry The agent's outgoing entry.
     *
     * @throws InsufficientFunds
     * @throws TransferNotAllowed
     */
    public function send(User $agent, User $recipient, string $amount): LedgerEntry
    {
        $amount = WalletService::positiveAmount($amount);

        if ($agent->is($recipient)) {
            throw TransferNotAllowed::toSelf();
        }

        return DB::transaction(function () use ($agent, $recipient, $amount): LedgerEntry {
            $reseller = Reseller::query()->where('user_id', $agent->id)->where('is_active', true)->first();

            if ($reseller === null) {
                throw TransferNotAllowed::notAnAgent();
            }

            $reference = 'transfer:'.Str::uuid();

            // debit() locks the agent's row and checks the balance before anything is written.
            $sent = $this->wallet->debit($agent, $amount, LedgerEntryType::TransferOut, $reference, "To {$recipient->email}");
            $this->wallet->credit($recipient, $amount, LedgerEntryType::TransferIn, $reference, "From {$reseller->name}");

            return $sent;
        });
    }
}
