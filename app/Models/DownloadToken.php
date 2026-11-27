<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * A short-lived download link. It works without a session, so download managers
 * and other devices can use it, but only from a few IP addresses.
 *
 * @property int $id
 * @property string $token
 * @property int $user_id
 * @property int $product_id
 * @property CarbonImmutable $expires_at
 * @property list<string> $ip_addresses
 * @property int $uses
 * @property int $bytes_served
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['token', 'user_id', 'product_id', 'expires_at', 'ip_addresses'])]
class DownloadToken extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'ip_addresses' => 'array',
            'uses' => 'integer',
            'bytes_served' => 'integer',
        ];
    }

    /**
     * Record that the link was used from this IP. Returns false, recording nothing,
     * when that would make more distinct IPs than allowed.
     */
    public function admitIp(string $ip, int $maxIps): bool
    {
        return DB::transaction(function () use ($ip, $maxIps): bool {
            $token = self::query()->lockForUpdate()->findOrFail($this->id);
            $ips = $token->ip_addresses;

            if (! in_array($ip, $ips, true)) {
                if (count($ips) >= $maxIps) {
                    return false;
                }

                $token->update(['ip_addresses' => [...$ips, $ip]]);
            }

            return true;
        });
    }

    /**
     * Count the use, and count a download for the file only the first time this user
     * downloads it in 24 hours: every click creates a new link.
     */
    public function recordUse(): void
    {
        DB::transaction(function (): void {
            $token = self::query()->lockForUpdate()->findOrFail($this->id);

            $alreadyCounted = $token->uses > 0 || self::query()
                ->where('user_id', $token->user_id)
                ->where('product_id', $token->product_id)
                ->whereKeyNot($token->id)
                ->where('created_at', '>=', now()->subDay())
                ->where('uses', '>', 0)
                ->exists();

            $token->increment('uses');

            if (! $alreadyCounted) {
                Product::query()->whereKey($token->product_id)->increment('download_count');
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return HasOne<DownloadLog, $this>
     */
    public function log(): HasOne
    {
        return $this->hasOne(DownloadLog::class);
    }
}
