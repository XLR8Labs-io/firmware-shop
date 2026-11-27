<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One movement of money in a user's wallet: positive adds, negative removes.
 * A balance is the sum of a user's entries. Entries are never changed or deleted;
 * a mistake is corrected with a new entry. Write them through WalletService.
 *
 * @property int $id
 * @property int $user_id
 * @property numeric-string $amount
 * @property LedgerEntryType $type
 * @property string $reference Unique per type, so the same money can't be recorded twice.
 * @property string|null $note
 * @property int|null $created_by The admin who made the entry, if one did.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'amount', 'type', 'reference', 'note', 'created_by'])]
class LedgerEntry extends Model
{
    protected $table = 'wallet_ledger';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'type' => LedgerEntryType::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Wallet ledger entries are never changed. Add a correcting entry instead.'));
        static::deleting(fn () => throw new LogicException('Wallet ledger entries are never deleted. Add a correcting entry instead.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
