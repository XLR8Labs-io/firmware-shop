<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ResellerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A local agent customers can pay in their own currency. Listed on the World agents
 * page; an active agent linked to an account can send wallet balance to customers.
 *
 * @property int $id
 * @property string $name
 * @property string $country
 * @property string|null $whatsapp
 * @property string|null $telegram
 * @property string|null $payment_methods
 * @property int $sort_order
 * @property bool $is_active
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'country', 'whatsapp', 'telegram', 'payment_methods', 'sort_order', 'is_active', 'user_id'])]
class Reseller extends Model
{
    /** @use HasFactory<ResellerFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function whatsappUrl(): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $this->whatsapp);

        return $digits === '' ? null : "https://wa.me/{$digits}";
    }

    /**
     * Only a valid Telegram username becomes a link; anything else is shown as text.
     */
    public function telegramUrl(): ?string
    {
        $handle = ltrim(trim((string) $this->telegram), '@');

        return preg_match('/^[A-Za-z0-9_]{5,32}$/', $handle) === 1 ? "https://t.me/{$handle}" : null;
    }
}
