<article class="rw-public-block rw-public-block--feature-card">
    <div class="rw-public-feature-card__accent" aria-hidden="true"></div>

    @if (! empty($block['slots']['media']))
        @include('public.system.partials.block-slot', [
            'slot' => $block['slots']['media'],
            'section' => $section ?? [],
            'contentItem' => $contentItem ?? null,
        ])
    @endif

    <div class="rw-public-block__body">
        @if (! empty($block['title']))
            <h2 class="rw-public-block__title rw-public-feature-card__title">{{ $block['title'] }}</h2>
        @endif
        @if (! empty($block['text']))
            <p class="rw-public-block__text rw-public-feature-card__text">{{ $block['text'] }}</p>
        @endif
    </div>

    @if (! empty($block['slots']['actions']))
        @include('public.system.partials.block-slot', [
            'slot' => $block['slots']['actions'],
            'section' => $section ?? [],
            'contentItem' => $contentItem ?? null,
        ])
    @endif
</article>
