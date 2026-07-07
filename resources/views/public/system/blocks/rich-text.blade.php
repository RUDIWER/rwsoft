<article class="rw-public-block--rich-text">
    <div class="rw-public-block__body rw-public-prose">
        @if (! empty($block['title']))
            <h2 class="rw-public-block__title">{{ $block['title'] }}</h2>
        @endif

        @if (! empty($block['html']))
            {!! $block['html'] !!}
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
