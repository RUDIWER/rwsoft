@if (! empty($block['media']))
    @php
        $srcset = collect($block['media']['responsive_variants'] ?? [])
            ->filter(fn ($variant) => is_array($variant) && ! empty($variant['url']) && ! empty($variant['width']))
            ->map(fn ($variant) => $variant['url'].' '.$variant['width'].'w')
            ->implode(', ');
    @endphp
    <article class="rw-public-block--image">
        <figure>
            <picture>
                @if ($srcset !== '')
                    <source type="image/webp" srcset="{{ $srcset }}" sizes="100vw">
                @endif
                <img
                    class="rw-public-image"
                    src="{{ $block['media']['url'] }}"
                    alt="{{ $block['media']['alt_text'] ?: ($contentItem['title'] ?? '') }}"
                    @if (! empty($block['media']['width'])) width="{{ $block['media']['width'] }}" @endif
                    @if (! empty($block['media']['height'])) height="{{ $block['media']['height'] }}" @endif
                    loading="lazy"
                >
            </picture>
            @if (! empty($block['caption']) || ! empty($block['media']['caption']))
                <figcaption class="rw-public-image-caption">{{ $block['caption'] ?: $block['media']['caption'] }}</figcaption>
            @endif
        </figure>
    </article>
@endif
