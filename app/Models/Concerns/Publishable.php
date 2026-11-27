<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Content with an `is_published` switch and a `published_at` time. It is public once
 * switched on and its time has come, so a post can be scheduled for later.
 */
trait Publishable
{
    public static function bootPublishable(): void
    {
        static::saving(function (self $model): void {
            if ($model->is_published && $model->published_at === null) {
                $model->published_at = now();
            }
        });
    }

    public function isPublic(): bool
    {
        return $this->is_published && $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true)->where('published_at', '<=', now());
    }
}
