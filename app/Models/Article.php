<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use App\Models\Concerns\Publishable;
use Carbon\CarbonImmutable;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A blog post.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $body Markdown
 * @property string|null $cover_image Path on the public disk.
 * @property bool $is_published
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'slug', 'excerpt', 'body', 'cover_image', 'is_published', 'published_at'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, HasUniqueSlug, Publishable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_published' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (Article $article): void {
            $previousImage = $article->getOriginal('cover_image');

            if ($article->wasChanged('cover_image') && is_string($previousImage)) {
                Storage::disk('public')->delete($previousImage);
            }
        });

        static::deleted(function (Article $article): void {
            if ($article->cover_image !== null) {
                Storage::disk('public')->delete($article->cover_image);
            }
        });
    }

    public function coverImageUrl(): ?string
    {
        return $this->cover_image === null ? null : Storage::disk('public')->url($this->cover_image);
    }

    protected function slugSource(): string
    {
        return $this->title;
    }
}
