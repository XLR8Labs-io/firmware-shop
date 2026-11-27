@props(['alt' => null])

@php
    $alt ??= app(App\Services\SiteSettings::class)->siteName();
@endphp

{{-- The dark-mode artwork has white lettering; the theme is a class on <html>, so swap by class, not by media query. --}}
<img
    src="{{ asset('logos/logo-light-400.webp') }}"
    srcset="{{ asset('logos/logo-light-400.webp') }} 1x, {{ asset('logos/logo-light-800.webp') }} 2x"
    width="400"
    height="100"
    alt="{{ $alt }}"
    {{ $attributes->class('w-auto dark:hidden') }}
>
<img
    src="{{ asset('logos/logo-dark-400.webp') }}"
    srcset="{{ asset('logos/logo-dark-400.webp') }} 1x, {{ asset('logos/logo-dark-800.webp') }} 2x"
    width="400"
    height="100"
    alt="{{ $alt }}"
    {{ $attributes->class('hidden w-auto dark:block') }}
>
