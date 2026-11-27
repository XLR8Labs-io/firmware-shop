<?php

namespace App\Services\Wallet;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The only way money enters or leaves a wallet. Amounts are decimal strings and
 * all arithmetic uses bcmath; floats never touch money.
 */
class WalletService
{
    /**
     * @return numeric-string
     */
    public function balance(User $user): string
    {
        // SUM of a DECIMAL column comes back as an exact decimal string; a float would lose cents.
        $sum = LedgerEntry::query()
            ->where('user_id', $user->id)
            ->value(DB::raw('COALESCE(SUM(amount), 0)'));

        if (! is_string($sum) || ! is_numeric($sum)) {
            throw new LogicException('The wallet balance came back from the database in an unexpected form.');
        }

        return bcadd($sum, '0', 2);
    }

    /**
     * Add money. Recording the same type and reference again returns the first entry,
     * so a repeated webhook or a double click can't pay twice.
     */
    public function credit(User $user, string $amount, LedgerEntryType $type, string $reference, ?string $note = null, ?User $by = null): LedgerEntry
    {
        $amount = self::positiveAmount($amount);

        $entry = LedgerEntry::query()->createOrFirst(
            ['type' => $type, 'reference' => $reference],
            ['user_id' => $user->id, 'amount' => $amount, 'note' => $note, 'created_by' => $by?->id],
        );

        if (! $entry->wasRecentlyCreated && ($entry->user_id !== $user->id || bccomp($entry->amount, $amount, 2) !== 0)) {
            throw new LogicException("The ledger reference {$reference} was already used for a different credit.");
        }

        return $entry;
    }

    /**
     * Take money out, refusing if the balance is too low.
     *
     * @throws InsufficientFunds
     */
    public function debit(User $user, string $amount, LedgerEntryType $type, string $reference, ?string $note = null, ?User $by = null): LedgerEntry
    {
        $amount = self::positiveAmount($amount);

        return DB::transaction(function () use ($user, $amount, $type, $reference, $note, $by): LedgerEntry {
            // Every debit for this user waits here, so two debits can never both see the same balance.
            User::query()->whereKey($user->id)->lockForUpdate()->sole();

            $balance = $this->balance($user);

            if (bccomp($balance, $amount, 2) < 0) {
                throw new InsufficientFunds($balance, $amount);
            }

            return LedgerEntry::query()->create([
                'user_id' => $user->id,
                'amount' => bcsub('0', $amount, 2),
                'type' => $type,
                'reference' => $reference,
                'note' => $note,
                'created_by' => $by?->id,
            ]);
        });
    }

    /**
     * @return numeric-string
     */
    public static function positiveAmount(string $amount): string
    {
        if (! is_numeric($amount) || preg_match('/^\d{1,16}(\.\d{1,2})?$/', $amount) !== 1 || bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException("An amount must be more than zero with at most two decimals; got \"{$amount}\".");
        }

        return bcadd($amount, '0', 2);
    }
}
