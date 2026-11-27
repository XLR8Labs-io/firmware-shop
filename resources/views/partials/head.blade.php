@inject('siteSettings', App\Services\SiteSettings::class)

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.$siteSettings->siteName() : $siteSettings->siteName() }}
</title>

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" href="{{ asset('favicon-32x32.png') }}" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#071a35">
<meta property="og:site_name" content="{{ $siteSettings->siteName() }}">
<meta property="og:image" content="{{ filled($image ?? null) ? $image : asset('social/og-light.png') }}">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ filled($title ?? null) ? $title : $siteSettings->siteName() }}">
<meta property="og:url" content="{{ url()->current() }}">
@if (filled($description ?? null))
    <meta name="description" content="{{ $description }}">
    <meta property="og:description" content="{{ $description }}">
@endif
<link rel="alternate" type="application/rss+xml" title="{{ __('Recent files') }}" href="{{ route('rss') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
