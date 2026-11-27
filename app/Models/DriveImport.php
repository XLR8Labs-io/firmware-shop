<?php

namespace App\Models;

use App\Enums\DriveImportStatus;
use Carbon\CarbonImmutable;
use Database\Factories\DriveImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One run of the Drive import: the contents of a Drive folder brought into a site folder.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $drive_folder_id
 * @property int|null $parent_folder_id
 * @property numeric-string $default_price
 * @property DriveImportStatus $status
 * @property int $folders_created
 * @property int $products_created
 * @property int $products_skipped Already imported earlier, so left alone.
 * @property int $files_skipped Google Docs, shortcuts, or files with no site folder to go into.
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'drive_folder_id', 'parent_folder_id', 'default_price', 'status', 'folders_created',
    'products_created', 'products_skipped', 'files_skipped', 'error', 'started_at', 'finished_at',
])]
class DriveImport extends Model
{
    /** @use HasFactory<DriveImportFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'default_price' => '0.00',
        'status' => 'queued',
        'folders_created' => 0,
        'products_created' => 0,
        'products_skipped' => 0,
        'files_skipped' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'status' => DriveImportStatus::class,
            'folders_created' => 'integer',
            'products_created' => 'integer',
            'products_skipped' => 'integer',
            'files_skipped' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
     * @return BelongsTo<Folder, $this>
     */
    public function parentFolder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_folder_id');
    }
}
