<article class="rw-public-block rw-public-block--content-slot">
    @if (! empty($block['title']))
        <h2 class="rw-public-block__title">{{ $block['title'] }}</h2>
    @endif

    @if (! empty($block['sections']))
        @include('public.system.partials.sections', [
            'sections' => $block['sections'],
            'contentItem' => $contentItem,
            'showEmpty' => false,
        ])
    @else
        @include('public.system.partials.blocks', [
            'blocks' => $block['blocks'] ?? [],
            'contentItem' => $contentItem,
        ])
    @endif
</article>
