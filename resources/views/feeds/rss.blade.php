{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ app(App\Services\SiteSettings::class)->siteName() }}: {{ __('Recent files') }}</title>
        <link>{{ route('home') }}</link>
        <description>{{ __('The latest files published on :site.', ['site' => app(App\Services\SiteSettings::class)->siteName()]) }}</description>
        <atom:link href="{{ route('rss') }}" rel="self" type="application/rss+xml" />
        @foreach ($products as $product)
            <item>
                <title>{{ $product->title }}</title>
                <link>{{ route('products.show', $product) }}</link>
                <guid isPermaLink="true">{{ route('products.show', $product) }}</guid>
                @if ($product->published_at)
                    <pubDate>{{ $product->published_at->toRssString() }}</pubDate>
                @endif
                @if ($product->short_description)
                    <description>{{ $product->short_description }}</description>
                @endif
            </item>
        @endforeach
    </channel>
</rss>
