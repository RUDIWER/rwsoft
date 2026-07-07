@extends('public.system.layouts.sitebuilder')

@section('content')
    @php
        $layoutNormalizer = app(App\Support\Cms\CmsResponsiveLayoutNormalizer::class);
        $pageSettings = is_array($composition['page']['settings'] ?? null) ? $composition['page']['settings'] : [];
        $pageStyle = is_array($pageSettings['page_style'] ?? null) ? $pageSettings['page_style'] : [];
        $pageWidthMode = in_array($pageStyle['width_mode'] ?? null, ['content', 'display'], true)
            ? $pageStyle['width_mode']
            : 'content';
        $pageContentGap = in_array($pageStyle['content_gap'] ?? null, ['none', 'compact', 'normal', 'spacious'], true)
            ? $pageStyle['content_gap']
            : 'normal';
        $pageAnchor = is_string($pageStyle['html_anchor'] ?? null) && preg_match('/^[a-z][a-z0-9-]{1,63}$/', $pageStyle['html_anchor']) === 1
            ? $pageStyle['html_anchor']
            : null;
        $pageCssClass = is_string($pageStyle['css_class'] ?? null) && preg_match('/^[A-Za-z_][A-Za-z0-9_-]*(?:\s+[A-Za-z_][A-Za-z0-9_-]*)*$/', $pageStyle['css_class']) === 1
            ? $pageStyle['css_class']
            : '';
        $pageBoxVariables = $layoutNormalizer->boxSpacingCssVariables(
            is_array($pageStyle['box'] ?? null) ? $pageStyle['box'] : null,
            'page'
        );
        $pageBackground = $layoutNormalizer->normalizeBackground(is_array($pageStyle['background'] ?? null) ? $pageStyle['background'] : null);
        $pageBackgroundMedia = is_array($composition['page']['background_media'] ?? null) ? $composition['page']['background_media'] : [];
        $pageResponsiveMedia = is_array($pageBackgroundMedia['responsive_variants'] ?? null) ? $pageBackgroundMedia['responsive_variants'] : [];
        $pageBackgroundUrls = [
            'mobile' => data_get($pageResponsiveMedia, 'mobile.url') ?: data_get($pageBackgroundMedia, 'url'),
            'tablet' => data_get($pageResponsiveMedia, 'tablet.url') ?: data_get($pageResponsiveMedia, 'mobile.url') ?: data_get($pageBackgroundMedia, 'url'),
            'desktop' => data_get($pageResponsiveMedia, 'desktop.url') ?: data_get($pageResponsiveMedia, 'tablet.url') ?: data_get($pageBackgroundMedia, 'url'),
            'display' => data_get($pageResponsiveMedia, 'display.url') ?: data_get($pageResponsiveMedia, 'desktop.url') ?: data_get($pageBackgroundMedia, 'url'),
        ];
        $pageStyleVariables = [];

        if (is_string($pageStyle['foreground_color'] ?? null) && $layoutNormalizer->normalizeHexColor($pageStyle['foreground_color']) !== null) {
            $pageStyleVariables['--rw-public-page-foreground-color'] = $layoutNormalizer->normalizeHexColor($pageStyle['foreground_color']);
        }

        if ($pageBackground['color'] !== null) {
            $pageStyleVariables['--rw-public-page-background-color'] = $pageBackground['color'];
        }

        foreach ($pageBackgroundUrls as $device => $url) {
            if (is_string($url) && $url !== '') {
                $escapedUrl = str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", '', ''], $url);
                $pageStyleVariables["--rw-public-page-background-image-{$device}"] = "url('{$escapedUrl}')";
            }
        }

        if (! empty($pageStyleVariables['--rw-public-page-background-image-mobile'])) {
            $pageStyleVariables['--rw-public-page-background-position'] = $pageBackground['position'];
            $pageStyleVariables['--rw-public-page-background-image-opacity'] = number_format(((int) $pageBackground['image_opacity']) / 100, 2, '.', '');
        }

        $pageStyleVariables = array_merge($pageStyleVariables, $pageBoxVariables);
        $pageForegroundClass = ! empty($pageStyleVariables['--rw-public-page-foreground-color']) ? 'rw-public-page--foreground-custom' : '';
        $pageBoxClass = $pageBoxVariables !== [] ? 'rw-public-page--box-spacing' : '';
        $pageBackgroundClass = $pageBackground['color'] !== null ? 'rw-public-page--background-custom' : '';
        $pageBackgroundImageClass = ! empty($pageStyleVariables['--rw-public-page-background-image-mobile']) ? 'rw-public-page--background-image rw-public-page--background-mode-'.$pageBackground['mode'] : '';
    @endphp

    <div
        @if ($pageAnchor)
            id="{{ $pageAnchor }}"
            data-cms-anchor="{{ $pageAnchor }}"
        @endif
        class="rw-public-page rw-public-page--width-{{ $pageWidthMode }} rw-public-page--gap-{{ $pageContentGap }} {{ $pageForegroundClass }} {{ $pageBoxClass }} {{ $pageBackgroundClass }} {{ $pageBackgroundImageClass }} {{ $pageCssClass }}"
        data-cms-page-id="{{ (int) ($composition['page']['id'] ?? 0) }}"
        @if ($pageStyleVariables !== [])
            style="@foreach ($pageStyleVariables as $cssVariableName => $cssVariableValue) {{ $cssVariableName }}: {{ $cssVariableValue }}; @endforeach"
        @endif
    >
        @include('public.system.partials.sections', [
            'sections' => $composition['sections']['content'] ?? [],
            'contentItem' => $pageItem,
        ])
    </div>
@endsection
