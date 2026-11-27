<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Fills an empty `slug` from {@see slugSource()} when the model is created,
 * adding -2, -3… if the slug is already taken (soft-deleted rows included).
 */
trait HasUniqueSlug
{
    public static function bootHasUniqueSlug(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->slug)) {
                $model->slug = static::uniqueSlug($model->slugSource());
            }
        });
    }

    abstract protected function slugSource(): string;

    public static function uniqueSlug(string $source): string
    {
        $base = Str::limit(Str::slug($source), 150, '');

        if ($base === '') {
            $base = Str::slug(class_basename(static::class));
        }

        // Slugs only contain [a-z0-9-], so the LIKE pattern needs no escaping.
        $taken = static::query()
            ->withoutGlobalScopes()
            ->where(fn ($query) => $query->where('slug', $base)->orWhere('slug', 'like', $base.'-%'))
            ->pluck('slug')
            ->flip();

        $slug = $base;

        for ($suffix = 2; $taken->has($slug); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
