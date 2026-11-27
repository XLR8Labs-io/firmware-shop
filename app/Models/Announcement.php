<?php

namespace App\Models;

use App\Models\Concerns\Publishable;
use Carbon\CarbonImmutable;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A notice from the store, such as new rules or a holiday. Ticker announcements also
 * scroll in the bar at the top of every page.
 *
 * @property int $id
 * @property string $title
 * @property string $body Markdown
 * @property bool $show_in_ticker
 * @property bool $is_published
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'body', 'show_in_ticker', 'is_published', 'published_at'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, Publishable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'show_in_ticker' => false,
        'is_published' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_in_ticker' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
