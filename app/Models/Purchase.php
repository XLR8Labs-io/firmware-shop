<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's right to a paid file. One row per user and file: buying again after a
 * refund reactivates the row. Create and refund purchases through PurchaseService.
 *
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property numeric-string $price_paid
 * @property int $ledger_entry_id The debit that paid for the current (or last) purchase.
 * @property PurchaseStatus $status
 * @property CarbonImmutable|null $refunded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'product_id', 'price_paid', 'ledger_entry_id', 'status', 'refunded_at'])]
class Purchase extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_paid' => 'decimal:2',
            'status' => PurchaseStatus::class,
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Deleted products stay attached so the purchase history remains complete.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<LedgerEntry, $this>
     */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }
}
