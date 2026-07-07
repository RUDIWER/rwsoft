@if (! empty($block['url']) && ! empty($block['label']))
    <a
        class="rw-public-button rw-public-button--block rw-public-block--button"
        href="{{ $block['url'] }}"
        target="{{ $block['target'] ?? '_self' }}"
        @if (! empty($block['rel'])) rel="{{ $block['rel'] }}" @endif
    >{{ $block['label'] }}</a>
@endif
