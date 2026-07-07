@php
    $mobileSpan = (int) ($placement['mobile_span'] ?? 12);
    $tabletSpan = (int) ($placement['tablet_span'] ?? 12);
    $desktopSpan = (int) ($placement['desktop_span'] ?? 12);
    $layoutConfig = is_array($placement['layout_config'] ?? null) ? $placement['layout_config'] : [];

    $mobileSpan = min(max($mobileSpan, 1), 12);
    $tabletSpan = min(max($tabletSpan, 1), 12);
    $desktopSpan = min(max($desktopSpan, 1), 12);

    $visibilityClass = match (implode('', [
        ! empty($placement['visible_mobile']) ? '1' : '0',
        ! empty($placement['visible_tablet']) ? '1' : '0',
        ! empty($placement['visible_desktop']) ? '1' : '0',
    ])) {
        '111' => 'rw-public-placement--visible-all',
        '011' => 'rw-public-placement--hidden-mobile',
        '101' => 'rw-public-placement--hidden-tablet',
        '110' => 'rw-public-placement--hidden-desktop',
        '001' => 'rw-public-placement--visible-desktop',
        '010' => 'rw-public-placement--visible-tablet',
        '100' => 'rw-public-placement--visible-mobile',
        default => 'rw-public-placement--hidden-all',
    };
    $settings = is_array($placement['settings'] ?? null) ? $placement['settings'] : [];
    $styleConfig = is_array($placement['style_config'] ?? null) ? $placement['style_config'] : [];
    $block = $placement['block'] ?? ['renderer_key' => ''];
    if (is_array($block)) {
        $block['slots'] = is_array($placement['slots'] ?? null) ? $placement['slots'] : [];
    }
    $rendererKey = (string) ($block['renderer_key'] ?? '');
    $layoutNormalizer = app(App\Support\Cms\CmsResponsiveLayoutNormalizer::class);
    $normalizedStyleConfig = $layoutNormalizer->normalizeStyle($styleConfig);
    $appearanceContainer = is_array($normalizedStyleConfig['appearance_container'] ?? null) ? $normalizedStyleConfig['appearance_container'] : [];
    $appearanceContainerEnabled = ($appearanceContainer['enabled'] ?? false) === true;
    $deviceStyles = is_array($normalizedStyleConfig['devices'] ?? null) ? $normalizedStyleConfig['devices'] : [];
    $boxVariables = $layoutNormalizer->boxSpacingCssVariables(
        is_array($normalizedStyleConfig['box'] ?? null) ? $normalizedStyleConfig['box'] : null,
        'placement'
    );
    $styleVariables = [];
    $hasBackgroundStyle = false;
    $hasForegroundStyle = false;
    $hasTypographyPresetStyle = false;
    $hasFontFamilyStyle = false;
    $hasFontSizeStyle = false;
    $hasFontWeightStyle = false;
    $hasPaddingStyle = false;
    $hasRadiusStyle = false;
    $hasBorderStyle = false;
    $hasShadowStyle = false;
    $hasContentAlignmentStyle = false;
    $hasContentVerticalAlignmentStyle = false;
    $hasLogoSizeStyle = false;
    $hasZIndexStyle = false;
    $hasFormStyle = false;
    $paddingValues = [
        'sm' => '0.75rem',
        'md' => '1.25rem',
        'lg' => 'clamp(1.5rem, 4vw, 2.5rem)',
    ];
    $radiusValues = [
        'none' => '0',
        'sm' => 'var(--rw-public-radius-sm)',
        'md' => 'var(--rw-public-radius-md)',
        'lg' => 'var(--rw-public-radius-lg)',
    ];
    $borderValues = [
        'subtle' => 'var(--rw-public-color-border)',
        'strong' => 'color-mix(in srgb, var(--rw-public-color-border) 45%, var(--rw-public-color-text) 55%)',
        'primary' => 'var(--rw-public-color-primary)',
    ];
    $shadowValues = [
        'sm' => '0 10px 25px rgb(15 23 42 / 8%)',
        'md' => 'var(--rw-public-shadow-card)',
        'lg' => '0 24px 70px rgb(15 23 42 / 18%)',
    ];
    $fontWeightValues = [
        'normal' => '400',
        'medium' => '500',
        'semibold' => '600',
        'bold' => '700',
    ];
    $contentAlignmentJustify = [
        'left' => 'flex-start',
        'center' => 'center',
        'right' => 'flex-end',
    ];
    $contentVerticalAlignmentJustify = [
        'top' => 'flex-start',
        'middle' => 'center',
        'bottom' => 'flex-end',
    ];
    $logoSizeValues = [
        'small' => '2.25rem',
        'default' => '3rem',
        'large' => '4rem',
    ];
    $formSpacingValues = [
        'compact' => '0.65rem',
        'normal' => '1rem',
        'spacious' => '1.45rem',
    ];
    $formInputRadiusValues = [
        'none' => '0',
        'sm' => 'var(--rw-public-radius-sm)',
        'md' => 'var(--rw-public-radius-md)',
        'lg' => 'var(--rw-public-radius-lg)',
        'pill' => '999px',
    ];
    $formInputBorderValues = [
        'none' => 'transparent',
        'subtle' => 'var(--rw-public-color-border)',
        'strong' => 'color-mix(in srgb, var(--rw-public-color-border) 45%, var(--rw-public-color-text) 55%)',
        'primary' => 'var(--rw-public-color-primary)',
    ];
    $formSubmitAlignmentValues = [
        'left' => 'flex-start',
        'center' => 'center',
        'right' => 'flex-end',
        'stretch' => 'flex-start',
    ];

    foreach (['mobile', 'tablet', 'desktop'] as $device) {
        $deviceStyle = is_array($deviceStyles[$device] ?? null) ? $deviceStyles[$device] : [];
        $appearance = is_array($deviceStyle['appearance'] ?? null) ? $deviceStyle['appearance'] : [];
        $backgroundColor = $layoutNormalizer->normalizeHexColor($appearance['background_color'] ?? null);
        $foregroundColor = $layoutNormalizer->normalizeHexColor($appearance['foreground_color'] ?? null);
        $backgroundColorToken = is_string($appearance['background_color_token'] ?? null) && preg_match('/^[a-z0-9_-]+$/', $appearance['background_color_token']) === 1
            ? (string) $appearance['background_color_token']
            : null;
        $foregroundColorToken = is_string($appearance['foreground_color_token'] ?? null) && preg_match('/^[a-z0-9_-]+$/', $appearance['foreground_color_token']) === 1
            ? (string) $appearance['foreground_color_token']
            : null;
        $fontFamilyToken = is_string($appearance['font_family_token'] ?? null) && $appearance['font_family_token'] !== 'inherit'
            ? (string) $appearance['font_family_token']
            : null;
        $typographyPreset = is_string($appearance['typography_preset'] ?? null) && $appearance['typography_preset'] !== 'inherit' && preg_match('/^[a-z0-9_-]+$/', $appearance['typography_preset']) === 1
            ? (string) $appearance['typography_preset']
            : null;
        $fontSizeToken = is_string($appearance['font_size_token'] ?? null) && $appearance['font_size_token'] !== 'inherit'
            ? (string) $appearance['font_size_token']
            : null;
        $fontWeightToken = is_string($appearance['font_weight'] ?? null) && $appearance['font_weight'] !== 'inherit'
            ? (string) $appearance['font_weight']
            : null;
        $logoSize = (string) ($appearance['logo_size'] ?? '');
        $contentAlignment = (string) ($deviceStyle['content_alignment'] ?? '');
        $contentVerticalAlignment = (string) ($deviceStyle['content_vertical_alignment'] ?? '');
        $zIndex = (string) ($deviceStyle['z_index'] ?? 'auto');

        if ($appearanceContainerEnabled && $backgroundColor !== null) {
            $hasBackgroundStyle = true;
            $styleVariables["--rw-public-placement-{$device}-background-color"] = $backgroundColor;
        } elseif ($appearanceContainerEnabled && $backgroundColorToken !== null) {
            $hasBackgroundStyle = true;
            $styleVariables["--rw-public-placement-{$device}-background-color"] = "var(--rw-public-color-{$backgroundColorToken})";
        }

        if ($foregroundColor !== null) {
            $hasForegroundStyle = true;
            $styleVariables["--rw-public-placement-{$device}-foreground-color"] = $foregroundColor;
        } elseif ($foregroundColorToken !== null) {
            $hasForegroundStyle = true;
            $styleVariables["--rw-public-placement-{$device}-foreground-color"] = "var(--rw-public-color-{$foregroundColorToken})";
        }

        if ($typographyPreset !== null) {
            $hasTypographyPresetStyle = true;
            $hasFontFamilyStyle = true;
            $hasFontSizeStyle = true;
            $hasFontWeightStyle = true;
            $styleVariables["--rw-public-placement-{$device}-font-family"] = "var(--rw-public-typo-{$typographyPreset}-font-family)";
            $styleVariables["--rw-public-placement-{$device}-font-size"] = "var(--rw-public-typo-{$typographyPreset}-font-size)";
            $styleVariables["--rw-public-placement-{$device}-font-weight"] = "var(--rw-public-typo-{$typographyPreset}-font-weight)";
            $styleVariables["--rw-public-placement-{$device}-line-height"] = "var(--rw-public-typo-{$typographyPreset}-line-height)";
            $styleVariables["--rw-public-placement-{$device}-letter-spacing"] = "var(--rw-public-typo-{$typographyPreset}-letter-spacing)";
        }

        if ($fontFamilyToken !== null && preg_match('/^[a-z0-9_-]+$/', $fontFamilyToken) === 1) {
            $hasFontFamilyStyle = true;
            $styleVariables["--rw-public-placement-{$device}-font-family"] = "var(--rw-public-font-{$fontFamilyToken})";
        }

        if ($fontSizeToken !== null && preg_match('/^[a-z0-9_-]+$/', $fontSizeToken) === 1) {
            $hasFontSizeStyle = true;
            $styleVariables["--rw-public-placement-{$device}-font-size"] = "var(--rw-public-font-size-{$fontSizeToken})";
        }

        if ($fontWeightToken !== null && isset($fontWeightValues[$fontWeightToken])) {
            $hasFontWeightStyle = true;
            $styleVariables["--rw-public-placement-{$device}-font-weight"] = $fontWeightValues[$fontWeightToken];
        }

        $padding = (string) ($appearance['padding'] ?? '');
        if ($appearanceContainerEnabled && isset($paddingValues[$padding])) {
            $hasPaddingStyle = true;
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                $styleVariables["--rw-public-placement-{$device}-style-padding-{$side}"] = $paddingValues[$padding];
            }
        }

        $radius = (string) ($appearance['radius'] ?? '');
        if ($appearanceContainerEnabled && isset($radiusValues[$radius])) {
            $hasRadiusStyle = true;
            $styleVariables["--rw-public-placement-{$device}-radius"] = $radiusValues[$radius];
        }

        $border = (string) ($appearance['border'] ?? '');
        if ($appearanceContainerEnabled && isset($borderValues[$border])) {
            $hasBorderStyle = true;
            $styleVariables["--rw-public-placement-{$device}-border-color"] = $borderValues[$border];
        }

        $shadow = (string) ($appearance['shadow'] ?? '');
        if ($appearanceContainerEnabled && isset($shadowValues[$shadow])) {
            $hasShadowStyle = true;
            $styleVariables["--rw-public-placement-{$device}-shadow"] = $shadowValues[$shadow];
        }

        if (isset($contentAlignmentJustify[$contentAlignment])) {
            $hasContentAlignmentStyle = true;
            $styleVariables["--rw-public-placement-{$device}-text-align"] = $contentAlignment;
            $styleVariables["--rw-public-placement-{$device}-content-justify"] = $contentAlignmentJustify[$contentAlignment];
        }

        if (isset($contentVerticalAlignmentJustify[$contentVerticalAlignment])) {
            $hasContentVerticalAlignmentStyle = true;
            $styleVariables["--rw-public-placement-{$device}-content-align"] = $contentVerticalAlignmentJustify[$contentVerticalAlignment];
        }

        if ($rendererKey === 'site_logo' && isset($logoSizeValues[$logoSize])) {
            $hasLogoSizeStyle = true;
            $styleVariables["--rw-public-placement-{$device}-logo-max-height"] = $logoSizeValues[$logoSize];
        }

        if (in_array($zIndex, ['0', '10', '20', '30', '40', '50'], true)) {
            $hasZIndexStyle = true;
            $styleVariables["--rw-public-placement-{$device}-z-index"] = $zIndex;
        }
    }

    if ($rendererKey === 'form') {
        $formStyle = is_array($normalizedStyleConfig['form'] ?? null) ? $normalizedStyleConfig['form'] : [];
        $formFieldSpacing = (string) ($formStyle['field_spacing'] ?? 'normal');
        $formLabelWeight = (string) ($formStyle['label_weight'] ?? 'inherit');
        $formInputRadius = (string) ($formStyle['input_radius'] ?? 'inherit');
        $formInputBorder = (string) ($formStyle['input_border'] ?? 'default');
        $formSubmitAlignment = (string) ($formStyle['submit_alignment'] ?? 'inherit');
        $formSubmitVariant = (string) ($formStyle['submit_variant'] ?? 'default');

        if ($formFieldSpacing !== 'normal' && isset($formSpacingValues[$formFieldSpacing])) {
            $hasFormStyle = true;
            $styleVariables['--rw-public-form-field-gap'] = $formSpacingValues[$formFieldSpacing];
        }

        if (isset($fontWeightValues[$formLabelWeight])) {
            $hasFormStyle = true;
            $styleVariables['--rw-public-form-label-weight'] = $fontWeightValues[$formLabelWeight];
        }

        if ($formInputRadius !== 'inherit' && isset($formInputRadiusValues[$formInputRadius])) {
            $hasFormStyle = true;
            $styleVariables['--rw-public-form-input-radius'] = $formInputRadiusValues[$formInputRadius];
        }

        if ($formInputBorder !== 'default' && isset($formInputBorderValues[$formInputBorder])) {
            $hasFormStyle = true;
            $styleVariables['--rw-public-form-input-border-color'] = $formInputBorderValues[$formInputBorder];
        }

        foreach (['input_background_color', 'input_text_color'] as $formColorField) {
            $formColor = $layoutNormalizer->normalizeCssColor($formStyle[$formColorField] ?? null);
            $formColorToken = is_string($formStyle[$formColorField.'_token'] ?? null) && preg_match('/^[a-z0-9_-]+$/', $formStyle[$formColorField.'_token']) === 1
                ? (string) $formStyle[$formColorField.'_token']
                : null;
            $formCssVariable = $formColorField === 'input_background_color'
                ? '--rw-public-form-input-background'
                : '--rw-public-form-input-color';

            if ($formColor !== null) {
                $hasFormStyle = true;
                $styleVariables[$formCssVariable] = $formColor;
            } elseif ($formColorToken !== null) {
                $hasFormStyle = true;
                $styleVariables[$formCssVariable] = "var(--rw-public-color-{$formColorToken})";
            }
        }

        if ($formSubmitAlignment !== 'inherit' && isset($formSubmitAlignmentValues[$formSubmitAlignment])) {
            $hasFormStyle = true;
            $styleVariables['--rw-public-form-submit-justify'] = $formSubmitAlignmentValues[$formSubmitAlignment];
        }

        if ($formSubmitVariant !== 'default') {
            $hasFormStyle = true;
        }
    }

    $appearanceClasses = array_filter([
        $boxVariables !== [] ? 'rw-public-placement--box-spacing' : '',
        $hasBackgroundStyle ? 'rw-public-placement--background-device' : '',
        $hasForegroundStyle ? 'rw-public-placement--foreground-device' : '',
        $hasFontFamilyStyle ? 'rw-public-placement--font-family-device' : '',
        $hasFontSizeStyle ? 'rw-public-placement--font-size-device' : '',
        $hasFontWeightStyle ? 'rw-public-placement--font-weight-device' : '',
        $hasTypographyPresetStyle ? 'rw-public-placement--typography-device' : '',
        $hasPaddingStyle ? 'rw-public-placement--padding-device' : '',
        $hasRadiusStyle ? 'rw-public-placement--radius-device' : '',
        $hasBorderStyle ? 'rw-public-placement--border-device' : '',
        $hasShadowStyle ? 'rw-public-placement--shadow-device' : '',
        $hasContentAlignmentStyle ? 'rw-public-placement--content-device' : '',
        $hasContentVerticalAlignmentStyle ? 'rw-public-placement--content-vertical-device' : '',
        $hasLogoSizeStyle ? 'rw-public-placement--logo-size-device' : '',
        $hasZIndexStyle ? 'rw-public-placement--z-index-device' : '',
        $hasFormStyle ? 'rw-public-placement--form-style' : '',
        ($rendererKey === 'form' && (($normalizedStyleConfig['form']['submit_alignment'] ?? null) === 'stretch')) ? 'rw-public-placement--form-submit-stretch' : '',
        ($rendererKey === 'form' && (($normalizedStyleConfig['form']['submit_variant'] ?? null) === 'outline')) ? 'rw-public-placement--form-submit-outline' : '',
        ($rendererKey === 'form' && (($normalizedStyleConfig['form']['submit_variant'] ?? null) === 'ghost')) ? 'rw-public-placement--form-submit-ghost' : '',
    ]);
    $deviceLayout = function (string $device, int $span, int $fallbackY = 0) use ($layoutConfig): array {
        $hasLayout = is_array($layoutConfig[$device] ?? null);
        $layout = is_array($layoutConfig[$device] ?? null) ? $layoutConfig[$device] : [];
        $width = min(max((int) ($layout['w'] ?? $span), 1), 12);
        $x = min(max((int) ($layout['x'] ?? 0), 0), 11);

        if ($x + $width > 12) {
            $x = max(0, 12 - $width);
        }

        return [
            'start' => $x + 1,
            'row' => max(1, (int) ($layout['y'] ?? $fallbackY) + 1),
            'span' => $width,
            'row_span' => max(1, (int) ($layout['h'] ?? 1)),
            'has_layout' => $hasLayout,
        ];
    };
    $alignmentStart = function (int $span, string $device) use ($deviceStyles): ?int {
        $deviceStyle = is_array($deviceStyles[$device] ?? null) ? $deviceStyles[$device] : [];

        return match ($deviceStyle['alignment'] ?? null) {
            'left' => 1,
            'center' => (int) floor((12 - $span) / 2) + 1,
            'right' => 13 - $span,
            default => null,
        };
    };
    $mobileLayout = $deviceLayout('mobile', $mobileSpan);
    $tabletLayout = $deviceLayout('tablet', $tabletSpan);
    $desktopLayout = $deviceLayout('desktop', $desktopSpan);
    $mobileStart = $alignmentStart($mobileLayout['span'], 'mobile') ?? $mobileLayout['start'];
    $tabletStart = $alignmentStart($tabletLayout['span'], 'tablet') ?? $tabletLayout['start'];
    $desktopStart = $alignmentStart($desktopLayout['span'], 'desktop') ?? $desktopLayout['start'];
    $mobileRow = $mobileLayout['has_layout'] ? $mobileLayout['row'] : 'auto';
    $tabletRow = $tabletLayout['has_layout'] ? $tabletLayout['row'] : 'auto';
    $desktopRow = $desktopLayout['has_layout'] ? $desktopLayout['row'] : 'auto';
    $definitionRuntime = app(App\Support\Cms\CmsBlockRegistry::class)->runtimeMetadataFor($rendererKey);
    $definitionClass = $definitionRuntime['custom_class'] ?? null;
    $definitionCssVariables = $definitionRuntime['css_variables'] ?? [];
    $definitionBehaviorKey = $definitionRuntime['behavior_key'] ?? null;
    $definitionBehaviorOptions = $definitionRuntime['behavior_options'] ?? [];
    $placementAnchor = is_string($settings['html_anchor'] ?? null) && preg_match('/^[a-z][a-z0-9-]{1,63}$/', $settings['html_anchor']) === 1
        ? $settings['html_anchor']
        : null;
    $safeRendererKey = preg_match('/^[a-z0-9_\-]+$/', $rendererKey) === 1 ? $rendererKey : null;
@endphp

<div
    @if ($placementAnchor)
        id="{{ $placementAnchor }}"
        data-cms-anchor="{{ $placementAnchor }}"
    @endif
    class="rw-public-placement {{ $visibilityClass }} {{ implode(' ', $appearanceClasses) }} {{ $definitionClass }}"
    data-cms-placement-id="{{ $placement['id'] }}"
    @if ($safeRendererKey)
        data-cms-block-type="{{ $safeRendererKey }}"
        data-cms-renderer="{{ $safeRendererKey }}"
    @endif
    @if ($definitionBehaviorKey)
        data-cms-behavior="{{ $definitionBehaviorKey }}"
        @if (! empty($definitionBehaviorOptions))
            data-cms-behavior-options="{{ json_encode($definitionBehaviorOptions, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
        @endif
    @endif
    style="--rw-public-placement-mobile-span: {{ $mobileLayout['span'] }}; --rw-public-placement-tablet-span: {{ $tabletLayout['span'] }}; --rw-public-placement-desktop-span: {{ $desktopLayout['span'] }}; --rw-public-placement-mobile-start: {{ $mobileStart }}; --rw-public-placement-tablet-start: {{ $tabletStart }}; --rw-public-placement-desktop-start: {{ $desktopStart }}; --rw-public-placement-mobile-row: {{ $mobileRow }}; --rw-public-placement-tablet-row: {{ $tabletRow }}; --rw-public-placement-desktop-row: {{ $desktopRow }}; --rw-public-placement-mobile-row-span: {{ $mobileLayout['row_span'] }}; --rw-public-placement-tablet-row-span: {{ $tabletLayout['row_span'] }}; --rw-public-placement-desktop-row-span: {{ $desktopLayout['row_span'] }}; @foreach ($styleVariables as $cssVariableName => $cssVariableValue) {{ $cssVariableName }}: {{ $cssVariableValue }}; @endforeach @foreach ($boxVariables as $cssVariableName => $cssVariableValue) {{ $cssVariableName }}: {{ $cssVariableValue }}; @endforeach @foreach ($definitionCssVariables as $cssVariableName => $cssVariableValue) {{ $cssVariableName }}: {{ $cssVariableValue }}; @endforeach"
>
    @include(app(App\Support\PublicSite\PublicViewResolver::class)->block($rendererKey), [
        'block' => $block,
        'placement' => $placement,
        'section' => $section ?? [],
        'contentItem' => $contentItem,
    ])
</div>
