<article class="rw-public-block rw-public-block--text">
    <div class="rw-public-block__body">
        @if (! empty($block['title']))
            <h2 class="rw-public-block__title">{{ $block['title'] }}</h2>
        @endif
        @if (! empty($block['text']))
            <p class="rw-public-block__text">{{ $block['text'] }}</p>
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
