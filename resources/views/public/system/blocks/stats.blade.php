<article class="rw-public-block rw-public-block--stats">
    @if (! empty($block['value']) || ! empty($block['suffix']))
        <p class="rw-public-stats__value">
            @if (! empty($block['value']))
                <span>{{ $block['value'] }}</span>
            @endif
            @if (! empty($block['suffix']))
                <span class="rw-public-stats__suffix">{{ $block['suffix'] }}</span>
            @endif
        </p>
    @endif
    @if (! empty($block['label']))
        <p class="rw-public-stats__label">{{ $block['label'] }}</p>
    @endif
</article>
