@props(['text'])

{{-- Raw HTML in the text is escaped and unsafe links are dropped. --}}
<div {{ $attributes->class('max-w-3xl space-y-4 leading-relaxed break-words [&_a]:text-accent-content [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-zinc-200 [&_blockquote]:pl-4 [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:font-semibold [&_img]:rounded-lg [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5') }}>
    {!! Str::markdown($text, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
</div>
