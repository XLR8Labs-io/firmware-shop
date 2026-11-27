<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\HasUniqueSlug;
use App\Services\FolderTree;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One downloadable file with its own price. A price of 0.00 means free (login required).
 *
 * @property int $id
 * @property int $folder_id
 * @property string $title
 * @property string $slug
 * @property string|null $model_names
 * @property string|null $short_description
 * @property string|null $description
 * @property numeric-string $price
 * @property bool $is_featured
 * @property ProductStatus $status
 * @property string $drive_file_id
 * @property string|null $mirror_drive_file_id
 * @property string $file_name
 * @property int $file_size
 * @property string|null $md5
 * @property string|null $mime_type
 * @property string|null $file_password
 * @property int $download_count
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'folder_id', 'title', 'slug', 'model_names', 'short_description', 'description', 'price', 'is_featured',
    'status', 'drive_file_id', 'mirror_drive_file_id', 'file_name', 'file_size', 'md5', 'mime_type',
    'file_password', 'published_at',
])]
#[Hidden(['drive_file_id', 'mirror_drive_file_id', 'file_password'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUniqueSlug, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'price' => '0.00',
        'is_featured' => false,
        'status' => 'draft',
        'file_size' => 0,
        'download_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_featured' => 'boolean',
            'status' => ProductStatus::class,
            'file_size' => 'integer',
            'file_password' => 'encrypted',
            'download_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if ($product->status === ProductStatus::Published && $product->published_at === null) {
                $product->published_at = now();
            }
        });
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * Published products whose folder (and every folder above it) is visible.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('status', ProductStatus::Published)
            ->whereIn('folder_id', app(FolderTree::class)->visibleIds());
    }

    /**
     * Match every word of the search against the title and model names.
     *
     * Plain words of three or more letters use the FULLTEXT index (prefix match, so
     * "garn" finds "garnet"). Shorter words and anything with punctuation, such as
     * "s9" or "SM-A155F", fall back to LIKE because FULLTEXT splits or ignores them.
     * Only letters and digits reach the FULLTEXT query, so search operators can't be injected.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $words = array_slice(preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 8);
        $fullTextWords = [];

        foreach ($words as $word) {
            if (preg_match('/^[\p{L}\p{N}]{3,}$/u', $word) === 1) {
                $fullTextWords[] = '+'.$word.'*';

                continue;
            }

            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word).'%';

            $query->where(fn (Builder $query) => $query
                ->where('title', 'like', $pattern)
                ->orWhere('model_names', 'like', $pattern));
        }

        if ($fullTextWords !== []) {
            $query->whereFullText(['title', 'model_names'], implode(' ', $fullTextWords), ['mode' => 'boolean']);
        }
    }

    /**
     * A free file shows its password to any signed-in user; a paid file only to people who own it.
     */
    public function passwordVisibleTo(?User $user): bool
    {
        return $user !== null
            && filled($this->file_password)
            && ($this->isFree() || $user->ownsProduct($this));
    }

    /**
     * "Free" or the price in dollars, such as "$40.00".
     */
    public function displayPrice(): string
    {
        return $this->isFree() ? __('Free') : '$'.$this->price;
    }

    public function isFree(): bool
    {
        return bccomp($this->price, '0', 2) === 0;
    }

    protected function slugSource(): string
    {
        return $this->title;
    }
}
