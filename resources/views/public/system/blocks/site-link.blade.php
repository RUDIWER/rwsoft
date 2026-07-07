@if (! empty($block['url']) && ! empty($block['label']))
    <a
        class="rw-public-link rw-public-site-link"
        href="{{ $block['url'] }}"
        target="{{ $block['target'] ?? '_self' }}"
        @if (! empty($block['rel'])) rel="{{ $block['rel'] }}" @endif
    >{{ $block['label'] }}</a>
@endif
