@php
    $linkUrl = $block['link_url'] ?? '/';
    $media = is_array($block['media'] ?? null) ? $block['media'] : null;
    $logoUrl = $media['url'] ?? null;
    $label = $block['alt_text'] ?: ($media['alt_text'] ?? ($site['name'] ?? config('app.name')));
@endphp

<a
    class="rw-public-logo"
    href="{{ $linkUrl ?: '/' }}"
    target="{{ $block['target'] ?? '_self' }}"
    @if (! empty($block['rel'])) rel="{{ $block['rel'] }}" @endif
>
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $label }}">
    @else
        <span>{{ $label }}</span>
    @endif
</a>
