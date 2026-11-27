@props([
    'sidebar' => false,
])

<a {{ $attributes->class(['flex h-10 shrink-0 items-center', 'px-2' => $sidebar]) }}>
    <x-brand-logo class="h-8" />
</a>
