@foreach (($styles ?? []) as $style)
    @php
        $styleType = (string) ($style['type'] ?? '');
        $cssSource = trim((string) ($style['css_source'] ?? ''));
    @endphp

    @if ($cssSource !== '' && $styleType === 'placeable_block_revision')
        <style data-cms-placeable-block="{{ (int) ($style['cms_placeable_block_id'] ?? 0) }}" data-cms-placeable-block-revision="{{ (int) ($style['revision_id'] ?? 0) }}">{!! $cssSource !!}</style>
    @elseif ($cssSource !== '' && $styleType === 'placement_style_revision')
        <style data-cms-placement-style-revision="{{ (int) ($style['revision_id'] ?? 0) }}">{!! $cssSource !!}</style>
    @endif
@endforeach
