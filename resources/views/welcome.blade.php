<x-layouts::site>
    <div class="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6">
        <h1 class="text-2xl font-semibold">{{ app(App\Services\SiteSettings::class)->siteName() }}</h1>
    </div>
</x-layouts::site>
