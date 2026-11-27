@props(['at', 'format' => 'j M Y, g:i A'])

{{-- Shows a stored UTC time in the display timezone; the machine-readable value stays in UTC. --}}
<time datetime="{{ $at->toIso8601String() }}" {{ $attributes }}>{{ $at->setTimezone(config('app.display_timezone'))->format($format) }}</time>
