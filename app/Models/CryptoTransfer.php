<?php

namespace App\Models;

use App\Enums\CryptoNetwork;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every incoming USDT transfer seen. One that matches no invoice waits for an admin.
 *
 * @property int $id
 * @property CryptoNetwork $network
 * @property string $tx_hash
 * @property string|null $from_address
 * @property numeric-string $amount
 * @property CarbonImmutable $block_time
 * @property int|null $crypto_invoice_id
 * @property int|null $assigned_user_id
 * @property int|null $assigned_by
 * @property string|null $admin_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['network', 'tx_hash', 'from_address', 'amount', 'block_time', 'crypto_invoice_id', 'assigned_user_id', 'assigned_by', 'admin_note'])]
class CryptoTransfer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'network' => CryptoNetwork::class,
            'amount' => 'decimal:6',
            'block_time' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CryptoInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CryptoInvoice::class, 'crypto_invoice_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
