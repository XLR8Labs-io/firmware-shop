<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use App\Services\FolderTree;
use Carbon\CarbonImmutable;
use Database\Factories\FolderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A node in the catalogue tree: brand → series or model → files.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $image_path
 * @property int $sort_order
 * @property bool $is_visible
 * @property string|null $drive_folder_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['parent_id', 'name', 'slug', 'description', 'image_path', 'sort_order', 'is_visible', 'drive_folder_id'])]
class Folder extends Model
{
    /** @use HasFactory<FolderFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
        'is_visible' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(FolderTree::class)->forget());

        static::updated(function (Folder $folder): void {
            $previousImage = $folder->getOriginal('image_path');

            if ($folder->wasChanged('image_path') && is_string($previousImage)) {
                Storage::disk('public')->delete($previousImage);
            }
        });

        static::deleted(function (Folder $folder): void {
            app(FolderTree::class)->forget();

            if ($folder->image_path !== null) {
                Storage::disk('public')->delete($folder->image_path);
            }
        });
    }

    /**
     * @return BelongsTo<Folder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The folders above this one, top level first.
     *
     * @return Collection<int, Folder>
     */
    public function ancestors(): Collection
    {
        $ids = app(FolderTree::class)->ancestorIds($this->id);

        return Folder::query()
            ->findMany($ids)
            ->sortBy(fn (Folder $folder): int|false => array_search($folder->id, $ids, true))
            ->values();
    }

    public function imageUrl(): ?string
    {
        return $this->image_path === null ? null : Storage::disk('public')->url($this->image_path);
    }

    protected function slugSource(): string
    {
        return $this->name;
    }
}
