<article class="rw-public-block rw-public-block--video">
    @if (! empty($block['title']))
        <h2 class="rw-public-block__title rw-public-video__title">{{ $block['title'] }}</h2>
    @endif

    @if (! empty($block['embed_url']))
        <div class="rw-public-video__frame">
            <iframe
                src="{{ $block['embed_url'] }}"
                @if (! empty($block['title'])) title="{{ $block['title'] }}" @endif
                loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"
            ></iframe>
        </div>
    @endif

    @if (! empty($block['slots']['actions']))
        @include('public.system.partials.block-slot', [
            'slot' => $block['slots']['actions'],
            'section' => $section ?? [],
            'contentItem' => $contentItem ?? null,
        ])
    @endif
</article>
