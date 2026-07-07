@php
    $slot = is_array($slot ?? null) ? $slot : [];
    $slotKey = is_string($slot['key'] ?? null) && preg_match('/^[a-z][a-z0-9_]{0,79}$/', $slot['key']) === 1
        ? $slot['key']
        : 'slot';
    $slotLayout = in_array($slot['layout'] ?? null, ['stack', 'inline', 'grid'], true) ? $slot['layout'] : 'stack';
    $slotResponsive = in_array($slot['responsive'] ?? null, ['same', 'wrap_mobile', 'stack_mobile'], true) ? $slot['responsive'] : 'same';
    $slotPlacements = collect(is_array($slot['placements'] ?? null) ? $slot['placements'] : [])
        ->filter(fn (mixed $placement): bool => is_array($placement))
        ->values()
        ->all();
@endphp

@if ($slotPlacements !== [])
    <div
        class="rw-public-block-slot rw-public-block-slot--{{ $slotKey }} rw-public-block-slot--{{ $slotLayout }} rw-public-block-slot--{{ $slotResponsive }}"
        data-cms-block-slot="{{ $slotKey }}"
    >
        @foreach ($slotPlacements as $slotPlacement)
            @include('public.system.partials.placement', [
                'placement' => $slotPlacement,
                'section' => $section ?? [],
                'contentItem' => $contentItem ?? null,
            ])
        @endforeach
    </div>
@endif
