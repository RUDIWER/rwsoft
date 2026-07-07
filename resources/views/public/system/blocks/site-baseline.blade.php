@php
    $baselineText = $block['text'] ?? ($site['tagline'] ?? null);
@endphp

@if (! empty($baselineText))
    <p class="rw-public-baseline">{{ $baselineText }}</p>
@endif
