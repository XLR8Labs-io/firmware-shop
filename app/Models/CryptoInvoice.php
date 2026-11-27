<?php

namespace App\Models;

use App\Enums\CryptoInvoiceStatus;
use App\Enums\CryptoNetwork;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to pay an exact, reserved USDT amount; the amount identifies who paid.
 *
 * @property int $id
 * @property int $user_id
 * @property CryptoNetwork $network
 * @property numeric-string $base_amount
 * @property numeric-string $pay_amount The amount that must arrive, and the amount credited.
 * @property CryptoInvoiceStatus $status
 * @property string|null $tx_hash
 * @property numeric-string|null $received_amount
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'network', 'base_amount', 'pay_amount', 'status', 'tx_hash', 'received_amount', 'expires_at', 'paid_at'])]
class CryptoInvoice extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'network' => CryptoNetwork::class,
            'base_amount' => 'decimal:2',
            'pay_amount' => 'decimal:2',
            'status' => CryptoInvoiceStatus::class,
            'received_amount' => 'decimal:6',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
