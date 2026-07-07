<!doctype html>
<html class="rw-public-root" lang="{{ $site['current_locale'] ?? $site['default_locale'] ?? app()->getLocale() }}">
    <head>
        @php
            $headSections = $composition['sections']['head'] ?? [];
            $hasExplicitSystemHead = collect($headSections)
                ->flatMap(fn (array $section): array => $section['placements'] ?? [])
                ->contains(fn (array $placement): bool => in_array($placement['block']['renderer_key'] ?? null, [
                    'site_head',
                    'site_head_meta',
                    'site_head_favicons',
                    'site_head_system_assets',
                    'site_head_theme',
                ], true));
        @endphp

        @unless ($hasExplicitSystemHead)
            @include('public.system.partials.head')
        @endunless

        @include('public.system.partials.code-sections', [
            'sections' => $headSections,
            'contentItem' => $pageItem,
        ])

        @include('public.system.partials.composition-styles', [
            'styles' => $composition['styles'] ?? [],
        ])

        @php
            $pageDeveloper = is_array($composition['page']['settings']['developer'] ?? null) ? $composition['page']['settings']['developer'] : [];
            $pageCssSource = trim((string) ($pageDeveloper['css_source'] ?? ''));
            $pageHeadCode = trim((string) ($pageDeveloper['head_code'] ?? ''));
        @endphp

        @if ($pageCssSource !== '')
            <style data-cms-page-css="{{ (int) ($composition['page']['id'] ?? 0) }}">{!! $pageCssSource !!}</style>
        @endif

        @if ($pageHeadCode !== '')
            {!! $pageHeadCode !!}
        @endif
    </head>
    <body class="rw-public">
        @php
            $scrollHeaderSections = $composition['sections']['header_scroll'] ?? [];
            $stickyHeaderSections = $composition['sections']['header_sticky'] ?? [];
            $layoutHeaderSections = $composition['sections']['header'] ?? [];
            $scrollFooterSections = $composition['sections']['footer_scroll'] ?? [];
            $stickyFooterSections = $composition['sections']['footer_sticky'] ?? [];
            $hasStickyHeader = empty($layoutHeaderSections) || ! empty($stickyHeaderSections);
            $hasStickyFooter = ! empty($stickyFooterSections);
            $scrollMode = $composition['layout']['settings']['scroll_mode'] ?? 'browser';
            $usesInternalScroll = $scrollMode === 'internal';
            $hasFixedEdge = $usesInternalScroll && ($hasStickyHeader || $hasStickyFooter);
            $layoutNormalizer = app(App\Support\Cms\CmsResponsiveLayoutNormalizer::class);
            $layoutSettings = is_array($composition['layout']['settings'] ?? null) ? $composition['layout']['settings'] : [];
            $layoutBackground = $layoutNormalizer->normalizeBackground(is_array($layoutSettings['background'] ?? null) ? $layoutSettings['background'] : null);
            $layoutBackgroundMedia = is_array($composition['layout']['background_media'] ?? null) ? $composition['layout']['background_media'] : [];
            $layoutResponsiveMedia = is_array($layoutBackgroundMedia['responsive_variants'] ?? null) ? $layoutBackgroundMedia['responsive_variants'] : [];
            $layoutBackgroundUrls = [
                'mobile' => data_get($layoutResponsiveMedia, 'mobile.url') ?: data_get($layoutBackgroundMedia, 'url'),
                'tablet' => data_get($layoutResponsiveMedia, 'tablet.url') ?: data_get($layoutResponsiveMedia, 'mobile.url') ?: data_get($layoutBackgroundMedia, 'url'),
                'desktop' => data_get($layoutResponsiveMedia, 'desktop.url') ?: data_get($layoutResponsiveMedia, 'tablet.url') ?: data_get($layoutBackgroundMedia, 'url'),
                'display' => data_get($layoutResponsiveMedia, 'display.url') ?: data_get($layoutResponsiveMedia, 'desktop.url') ?: data_get($layoutBackgroundMedia, 'url'),
            ];
            $layoutBackgroundVariables = [];

            if ($layoutBackground['color'] !== null) {
                $layoutBackgroundVariables['--rw-public-layout-background-color'] = $layoutBackground['color'];
            }

            foreach ($layoutBackgroundUrls as $device => $url) {
                if (is_string($url) && $url !== '') {
                    $escapedUrl = str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", '', ''], $url);
                    $layoutBackgroundVariables["--rw-public-layout-background-image-{$device}"] = "url('{$escapedUrl}')";
                }
            }

            if (! empty($layoutBackgroundVariables['--rw-public-layout-background-image-mobile'])) {
                $layoutBackgroundVariables['--rw-public-layout-background-position'] = $layoutBackground['position'];
                $layoutBackgroundVariables['--rw-public-layout-background-image-opacity'] = number_format(((int) $layoutBackground['image_opacity']) / 100, 2, '.', '');
            }

            $layoutBackgroundColorClass = $layoutBackground['color'] !== null ? 'rw-public-shell--background-custom' : '';
            $layoutBackgroundImageClass = ! empty($layoutBackgroundVariables['--rw-public-layout-background-image-mobile']) ? 'rw-public-shell--background-image rw-public-shell--background-mode-'.$layoutBackground['mode'] : '';
            $layoutAnchor = is_string($layoutSettings['html_anchor'] ?? null) && preg_match('/^[a-z][a-z0-9-]{1,63}$/', $layoutSettings['html_anchor']) === 1
                ? $layoutSettings['html_anchor']
                : null;
        @endphp

        <div
            @if ($layoutAnchor)
                id="{{ $layoutAnchor }}"
                data-cms-anchor="{{ $layoutAnchor }}"
            @endif
            class="rw-public-shell {{ $hasFixedEdge ? 'rw-public-shell--fixed-edges' : '' }} {{ $usesInternalScroll && $hasStickyHeader ? 'rw-public-shell--sticky-header' : '' }} {{ $usesInternalScroll && $hasStickyFooter ? 'rw-public-shell--sticky-footer' : '' }} {{ $layoutBackgroundColorClass }} {{ $layoutBackgroundImageClass }}"
            @if ($layoutBackgroundVariables !== [])
                style="@foreach ($layoutBackgroundVariables as $cssVariableName => $cssVariableValue) {{ $cssVariableName }}: {{ $cssVariableValue }}; @endforeach"
            @endif
        >
            <div class="rw-public-header-stack {{ $hasStickyHeader ? 'rw-public-header-stack--sticky' : '' }}">
                @include('public.system.partials.sections', [
                    'sections' => $stickyHeaderSections,
                    'contentItem' => $pageItem,
                    'showEmpty' => false,
                ])

                @if (empty($layoutHeaderSections))
                    @include('public.system.partials.header')
                @endif
            </div>

            <main class="rw-public-main rw-public-container">
                @include('public.system.partials.sections', [
                    'sections' => $scrollHeaderSections,
                    'contentItem' => $pageItem,
                    'showEmpty' => false,
                ])

                @include('public.system.partials.flash')
                @yield('content')

                @if ($usesInternalScroll)
                    @include('public.system.partials.sections', [
                        'sections' => $scrollFooterSections,
                        'contentItem' => $pageItem,
                        'showEmpty' => false,
                    ])
                @endif
            </main>

            @unless ($usesInternalScroll)
                @include('public.system.partials.sections', [
                    'sections' => $scrollFooterSections,
                    'contentItem' => $pageItem,
                    'showEmpty' => false,
                ])
            @endunless

            @if ($hasStickyFooter)
                <div class="rw-public-footer-stack rw-public-footer-stack--sticky">
                    @include('public.system.partials.sections', [
                        'sections' => $stickyFooterSections,
                        'contentItem' => $pageItem,
                        'showEmpty' => false,
                    ])
                </div>
            @endif
        </div>

        @livewireScripts
        @include('public.system.partials.code-sections', [
            'sections' => $composition['sections']['body_end'] ?? [],
            'contentItem' => $pageItem,
        ])

        @php
            $pageBodyEndCode = trim((string) ($pageDeveloper['body_end_code'] ?? ''));
        @endphp

        @if ($pageBodyEndCode !== '')
            {!! $pageBodyEndCode !!}
        @endif
    </body>
</html>
