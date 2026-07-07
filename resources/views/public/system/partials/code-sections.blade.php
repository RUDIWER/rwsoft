@foreach (($sections ?? []) as $section)
    @foreach (($section['placements'] ?? []) as $placement)
        @php($block = $placement['block'] ?? ['renderer_key' => ''])
        @include(app(App\Support\PublicSite\PublicViewResolver::class)->block((string) ($block['renderer_key'] ?? '')), [
            'block' => $block,
            'contentItem' => $contentItem ?? null,
        ])
    @endforeach
@endforeach
