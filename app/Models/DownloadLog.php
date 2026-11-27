<?php

namespace App\Models;

use App\Enums\DownloadStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One click on Download, and what became of it.
 *
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property int|null $download_token_id
 * @property string $ip
 * @property string|null $user_agent
 * @property string $drive_file_used "primary" or "mirror"
 * @property DownloadStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'product_id', 'download_token_id', 'ip', 'user_agent', 'drive_file_used', 'status'])]
class DownloadLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DownloadStatus::class,
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<DownloadToken, $this>
     */
    public function downloadToken(): BelongsTo
    {
        return $this->belongsTo(DownloadToken::class);
    }
}
