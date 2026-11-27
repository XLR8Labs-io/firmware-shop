<?php

namespace App\Models;

use App\Enums\DepositStatus;
use App\Enums\MfsMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bKash or Nagad payment a customer reports, waiting for an admin to check it.
 * Change it through MfsDepositService.
 *
 * @property int $id
 * @property int $user_id
 * @property MfsMethod $method
 * @property numeric-string $amount_bdt
 * @property numeric-string $rate_bdt_per_usd The rate when the deposit was submitted.
 * @property numeric-string $amount_usd
 * @property string|null $sender_number
 * @property string|null $trx_id
 * @property DepositStatus $status
 * @property string $source "manual" now, "piprapay" later
 * @property string|null $gateway_payment_id
 * @property string|null $admin_note
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'method', 'amount_bdt', 'rate_bdt_per_usd', 'amount_usd', 'sender_number', 'trx_id',
    'status', 'source', 'gateway_payment_id', 'admin_note', 'reviewed_by', 'reviewed_at',
])]
class MfsDeposit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => MfsMethod::class,
            'amount_bdt' => 'decimal:2',
            'rate_bdt_per_usd' => 'decimal:4',
            'amount_usd' => 'decimal:2',
            'status' => DepositStatus::class,
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
