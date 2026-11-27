<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\Cache;

/**
 * Site-wide settings that admins edit in the panel.
 *
 * All settings are cached together as one entry, and each request (or queued job)
 * loads that entry at most once.
 */
#[Scoped]
class SiteSettings
{
    private const string CACHE_KEY = 'site-settings';

    /**
     * @var array<string, string|null>|null
     */
    private ?array $values = null;

    public function get(string $name): ?string
    {
        return $this->all()[$name] ?? null;
    }

    public function siteName(): string
    {
        return $this->get('site_name') ?? config()->string('app.name');
    }

    /**
     * Community links that have been filled in, keyed by their visible label.
     *
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        $whatsappNumber = $this->get('whatsapp_number');

        return array_filter([
            __('Telegram channel') => $this->get('telegram_channel_url'),
            __('Telegram group') => $this->get('telegram_group_url'),
            __('WhatsApp') => $whatsappNumber === null ? null : 'https://wa.me/'.ltrim($whatsappNumber, '+'),
            __('Facebook') => $this->get('facebook_url'),
            __('YouTube') => $this->get('youtube_url'),
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        /** @var array<string, string|null> */
        return $this->values ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'name')->all(),
        );
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function save(array $values): void
    {
        $rows = [];

        foreach ($values as $name => $value) {
            $rows[] = ['name' => $name, 'value' => $value];
        }

        Setting::query()->upsert($rows, uniqueBy: ['name'], update: ['value']);

        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }
}
