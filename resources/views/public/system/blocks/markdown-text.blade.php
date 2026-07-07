<article class="rw-public-block--markdown-text">
    <div class="rw-public-block__body rw-public-prose">
        @if (! empty($block['title']))
            <h2 class="rw-public-block__title">{{ $block['title'] }}</h2>
        @endif

        @if (! empty($block['markdown']))
            {!! \Illuminate\Support\Str::markdown((string) $block['markdown'], [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]) !!}
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
