<?php

namespace App\Models;

use App\Enums\FileRequestStatus;
use Carbon\CarbonImmutable;
use Database\Factories\FileRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A visitor asking for a file the store doesn't have yet.
 *
 * @property int $id
 * @property int|null $user_id Set when the visitor was signed in.
 * @property string $name
 * @property string $contact Email, Telegram or WhatsApp.
 * @property string $brand
 * @property string $model
 * @property string|null $details
 * @property FileRequestStatus $status
 * @property string|null $admin_reply Shown to the customer on the request page.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'name', 'contact', 'brand', 'model', 'details', 'status', 'admin_reply'])]
class FileRequest extends Model
{
    /** @use HasFactory<FileRequestFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FileRequestStatus::class,
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
