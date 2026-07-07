<article class="rw-public-block rw-public-block--quote">
    <blockquote class="rw-public-quote">
        <p class="rw-public-quote__text">{{ $block['text'] ?? '' }}</p>
        @if (! empty($block['source']))
            <footer class="rw-public-quote__source">{{ $block['source'] }}</footer>
        @endif
    </blockquote>

    @if (! empty($block['slots']['actions']))
        @include('public.system.partials.block-slot', [
            'slot' => $block['slots']['actions'],
            'section' => $section ?? [],
            'contentItem' => $contentItem ?? null,
        ])
    @endif
</article>
