<article class="rw-public-block rw-public-block--testimonial">
    @if (! empty($block['text']))
        <blockquote class="rw-public-testimonial__quote">
            <p class="rw-public-testimonial__text">{{ $block['text'] }}</p>
            @if (! empty($block['source']))
                <footer class="rw-public-testimonial__source">{{ $block['source'] }}</footer>
            @endif
        </blockquote>
    @endif

    @if (! empty($block['slots']['actions']))
        @include('public.system.partials.block-slot', [
            'slot' => $block['slots']['actions'],
            'section' => $section ?? [],
            'contentItem' => $contentItem ?? null,
        ])
    @endif
</article>
