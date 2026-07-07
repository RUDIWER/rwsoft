<article class="rw-public-block rw-public-block--logo-strip">
    @if (! empty($block['title']))
        <h2 class="rw-public-block__title rw-public-logo-strip__title">{{ $block['title'] }}</h2>
    @endif

    @if (! empty($block['media']))
        <ul class="rw-public-logo-strip__list">
            @foreach ($block['media'] as $logo)
                @if (! empty($logo['url']))
                    <li class="rw-public-logo-strip__item">
                        <img
                            class="rw-public-logo-strip__image"
                            src="{{ $logo['url'] }}"
                            alt="{{ $logo['alt_text'] ?? '' }}"
                            loading="lazy"
                            @if (! empty($logo['width'])) width="{{ $logo['width'] }}" @endif
                            @if (! empty($logo['height'])) height="{{ $logo['height'] }}" @endif
                        >
                    </li>
                @endif
            @endforeach
        </ul>
    @endif
</article>
