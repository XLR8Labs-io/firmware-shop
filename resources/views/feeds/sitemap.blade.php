{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('home') }}</loc></url>
    <url><loc>{{ route('recent') }}</loc></url>
    <url><loc>{{ route('top') }}</loc></url>
    @foreach ($folders as $folder)
        <url>
            <loc>{{ route('folders.show', $folder) }}</loc>
            @if ($folder->updated_at)
                <lastmod>{{ $folder->updated_at->toAtomString() }}</lastmod>
            @endif
        </url>
    @endforeach
    @foreach ($products as $product)
        <url>
            <loc>{{ route('products.show', $product) }}</loc>
            @if ($product->updated_at)
                <lastmod>{{ $product->updated_at->toAtomString() }}</lastmod>
            @endif
        </url>
    @endforeach
</urlset>
