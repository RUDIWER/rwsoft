@if (! empty($sections))
    @foreach ($sections as $section)
        @php
            $layoutNormalizer = app(App\Support\Cms\CmsResponsiveLayoutNormalizer::class);
            $settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
            $layoutType = in_array($settings['layout_type'] ?? null, ['standard', 'hero', 'two_columns', 'grid'], true)
                ? $settings['layout_type']
                : 'standard';
            $widthMode = in_array($settings['width_mode'] ?? null, ['content', 'display'], true)
                ? $settings['width_mode']
                : 'content';
            $spacing = in_array($settings['spacing'] ?? null, ['compact', 'normal', 'spacious'], true)
                ? $settings['spacing']
                : 'none';
            $zone = preg_replace('/[^a-z0-9_-]/', '', (string) ($section['zone'] ?? 'content')) ?: 'content';
            $scrollBehavior = in_array($settings['scroll_behavior'] ?? null, ['normal', 'sticky', 'auto_hide'], true)
                ? $settings['scroll_behavior']
                : 'normal';
            $sectionVisibility = match (implode('', [
                ! empty($section['visible_mobile']) ? '1' : '0',
                ! empty($section['visible_tablet']) ? '1' : '0',
                ! empty($section['visible_desktop']) ? '1' : '0',
            ])) {
                '111' => 'rw-public-section--visible-all',
                '011' => 'rw-public-section--hidden-mobile',
                '101' => 'rw-public-section--hidden-tablet',
                '110' => 'rw-public-section--hidden-desktop',
                '001' => 'rw-public-section--visible-desktop',
                '010' => 'rw-public-section--visible-tablet',
                '100' => 'rw-public-section--visible-mobile',
                default => 'rw-public-section--hidden-all',
            };
            $sectionBoxVariables = $layoutNormalizer->boxSpacingCssVariables(
                is_array($settings['box'] ?? null) ? $settings['box'] : null,
                'section'
            );
            $sectionBackground = $layoutNormalizer->normalizeBackground(is_array($settings['background'] ?? null) ? $settings['background'] : null);
            $sectionBackgroundColor = $sectionBackground['color'];
            $sectionBackgroundMedia = is_array($section['background_media'] ?? null) ? $section['background_media'] : [];
            $sectionResponsiveMedia = is_array($sectionBackgroundMedia['responsive_variants'] ?? null) ? $sectionBackgroundMedia['responsive_variants'] : [];
            $sectionBackgroundUrls = [
                'mobile' => data_get($sectionResponsiveMedia, 'mobile.url') ?: data_get($sectionBackgroundMedia, 'url'),
                'tablet' => data_get($sectionResponsiveMedia, 'tablet.url') ?: data_get($sectionResponsiveMedia, 'mobile.url') ?: data_get($sectionBackgroundMedia, 'url'),
                'desktop' => data_get($sectionResponsiveMedia, 'desktop.url') ?: data_get($sectionResponsiveMedia, 'tablet.url') ?: data_get($sectionBackgroundMedia, 'url'),
                'display' => data_get($sectionResponsiveMedia, 'display.url') ?: data_get($sectionResponsiveMedia, 'desktop.url') ?: data_get($sectionBackgroundMedia, 'url'),
            ];
            $sectionBackgroundVariables = [];

            if ($sectionBackgroundColor !== null) {
                $sectionBackgroundVariables['--rw-public-section-background-color'] = $sectionBackgroundColor;
            }

            foreach ($sectionBackgroundUrls as $device => $url) {
                if (is_string($url) && $url !== '') {
                    $escapedUrl = str_replace(["\\", "'", "\n", "\r"], ["\\\\", "\\'", '', ''], $url);
                    $sectionBackgroundVariables["--rw-public-section-background-image-{$device}"] = "url('{$escapedUrl}')";
                }
            }

            if (! empty($sectionBackgroundVariables['--rw-public-section-background-image-mobile'])) {
                $sectionBackgroundVariables['--rw-public-section-background-position'] = $sectionBackground['position'];
                $sectionBackgroundVariables['--rw-public-section-background-image-opacity'] = number_format(((int) $sectionBackground['image_opacity']) / 100, 2, '.', '');
            }

            $sectionStyleVariables = array_merge($sectionBackgroundVariables, $sectionBoxVariables);
            $sectionBoxClass = $sectionBoxVariables !== [] ? 'rw-public-section--box-spacing' : '';
            $sectionBackgroundClass = $sectionBackgroundColor !== null ? 'rw-public-section--background-custom' : '';
            $sectionBackgroundImageClass = ! empty($sectionBackgroundVariables['--rw-public-section-background-image-mobile']) ? 'rw-public-section--background-image rw-public-section--background-mode-'.$sectionBackground['mode'] : '';
            $sectionScrollClass = in_array($zone, ['header', 'footer'], true) ? 'rw-public-section--scroll-'.str_replace('_', '-', $scrollBehavior) : '';
            $sectionAnchor = is_string($settings['html_anchor'] ?? null) && preg_match('/^[a-z][a-z0-9-]{1,63}$/', $settings['html_anchor']) === 1
                ? $settings['html_anchor']
                : null;
            $sectionPlacements = $layoutNormalizer->resolvePlacementLayoutCollisions(
                is_array($section['placements'] ?? null) ? $section['placements'] : []
            );
        @endphp

        <section
            @if ($sectionAnchor)
                id="{{ $sectionAnchor }}"
                data-cms-anchor="{{ $sectionAnchor }}"
            @endif
            class="rw-public-section rw-public-section--zone-{{ $zone }} rw-public-section--layout-{{ $layoutType }} rw-public-section--width-{{ $widthMode }} rw-public-section--spacing-{{ $spacing }} {{ $sectionVisibility }} {{ $sectionBoxClass }} {{ $sectionBackgroundClass }} {{ $sectionBackgroundImageClass }} {{ $sectionScrollClass }}"
            data-cms-section-id="{{ $section['id'] }}"
            data-zone="{{ $zone }}"
            data-layout-type="{{ $layoutType }}"
            data-width-mode="{{ $widthMode }}"
            data-spacing="{{ $spacing }}"
            @if ($scrollBehavior === 'auto_hide' && in_array($zone, ['header', 'footer'], true))
                data-cms-behavior="auto-hide-edge"
                data-cms-behavior-options="{{ json_encode(['edge' => $zone], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
            @endif
            @if ($sectionStyleVariables !== [])
                style="@foreach ($sectionStyleVariables as $cssVariableName => $cssVariableValue) {{ $cssVariableName }}: {{ $cssVariableValue }}; @endforeach"
            @endif
        >
            <div class="rw-public-section__grid">
                @foreach ($sectionPlacements as $placement)
                    @include('public.system.partials.placement', [
                        'placement' => $placement,
                        'section' => $section,
                        'contentItem' => $contentItem,
                    ])
                @endforeach
            </div>
        </section>
    @endforeach
@elseif ($showEmpty ?? true)
    <section class="rw-public-block">
        <p class="rw-public-block__text">{{ public_text('content.empty_blocks', 'Deze pagina heeft nog geen content blocks.', $site['current_locale'] ?? null) }}</p>
    </section>
@endif
