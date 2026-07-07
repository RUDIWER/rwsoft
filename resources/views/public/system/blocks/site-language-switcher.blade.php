@php
    use App\Support\Cms\CmsResponsiveLayoutNormalizer;
    use App\Support\PublicSite\CmsLanguageSettings;

    $locale = $site['current_locale'] ?? app()->getLocale();
    $availableTranslations = collect($translations ?? [])->keyBy(fn (array $translation): string => (string) ($translation['locale'] ?? ''));
    $languageSettings = app(CmsLanguageSettings::class);
    $languages = collect($languageSettings->languages(true))->keyBy('locale');
    $hideMissing = filter_var($block['hide_missing_translations'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    $showCurrent = filter_var($block['show_current'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    $labelDisplays = ['code', 'name', 'native_name', 'code_name', 'code_native_name', 'flag_only', 'flag_code', 'flag_name', 'flag_native_name'];
    $labelDisplay = in_array($block['label_display'] ?? null, $labelDisplays, true) ? (string) $block['label_display'] : 'flag_code';
    $blockFlagPosition = in_array($block['flag_position'] ?? null, CmsResponsiveLayoutNormalizer::LANGUAGE_FLAG_POSITIONS, true) ? (string) $block['flag_position'] : null;
    $blockFlagShape = in_array($block['flag_shape'] ?? null, CmsResponsiveLayoutNormalizer::LANGUAGE_FLAG_SHAPES, true) ? (string) $block['flag_shape'] : null;
    $blockFlagSize = in_array($block['flag_size'] ?? null, CmsResponsiveLayoutNormalizer::LANGUAGE_FLAG_SIZES, true) ? (string) $block['flag_size'] : null;
    $styleConfig = is_array($placement['style_config'] ?? null) ? $placement['style_config'] : [];
    $layoutNormalizer = app(CmsResponsiveLayoutNormalizer::class);
    $languageStyle = $layoutNormalizer->normalizeLanguageStyle(is_array($styleConfig['language'] ?? null) ? $styleConfig['language'] : null);
    $languageDevices = is_array($languageStyle['devices'] ?? null) ? $languageStyle['devices'] : [];
    $desktopLanguage = is_array($languageDevices['desktop'] ?? null) ? $languageDevices['desktop'] : [];
    $tabletLanguage = is_array($languageDevices['tablet'] ?? null) ? $languageDevices['tablet'] : [];
    $mobileLanguage = is_array($languageDevices['mobile'] ?? null) ? $languageDevices['mobile'] : [];
    $languageHeadings = [
        'desktop' => [
            'label' => trim((string) ($desktopLanguage['label'] ?? '')),
            'icon' => (string) ($desktopLanguage['icon'] ?? 'none'),
        ],
        'tablet' => [
            'label' => trim((string) ($tabletLanguage['label'] ?? '')),
            'icon' => (string) ($tabletLanguage['icon'] ?? 'none'),
        ],
        'mobile' => [
            'label' => trim((string) ($mobileLanguage['label'] ?? '')),
            'icon' => (string) ($mobileLanguage['icon'] ?? 'none'),
        ],
    ];
    $hasLanguageHeading = collect($languageHeadings)->contains(
        fn (array $heading): bool => $heading['label'] !== ''
    );
    $languageIconPaths = [
        'mdi-earth' => ['M12 2A10 10 0 0 0 2 12A10 10 0 0 0 12 22A10 10 0 0 0 22 12A10 10 0 0 0 12 2M18.93 8H15.97C15.64 6.71 15.12 5.5 14.44 4.43A8.014 8.014 0 0 1 18.93 8M12 4.04C12.83 5.24 13.48 6.58 13.84 8H10.16C10.52 6.58 11.17 5.24 12 4.04M4.26 14C4.1 13.36 4 12.69 4 12C4 11.31 4.1 10.64 4.26 10H7.64C7.56 10.66 7.5 11.32 7.5 12C7.5 12.68 7.56 13.34 7.64 14H4.26M5.07 16H8.03C8.36 17.29 8.88 18.5 9.56 19.57A8.014 8.014 0 0 1 5.07 16M8.03 8H5.07A8.014 8.014 0 0 1 9.56 4.43C8.88 5.5 8.36 6.71 8.03 8M12 19.96C11.17 18.76 10.52 17.42 10.16 16H13.84C13.48 17.42 12.83 18.76 12 19.96M14.27 14H9.73C9.64 13.34 9.57 12.68 9.57 12C9.57 11.32 9.64 10.65 9.73 10H14.27C14.36 10.65 14.43 11.32 14.43 12C14.43 12.68 14.36 13.34 14.27 14M14.44 19.57C15.12 18.5 15.64 17.29 15.97 16H18.93A8.014 8.014 0 0 1 14.44 19.57M16.36 14C16.44 13.34 16.5 12.68 16.5 12C16.5 11.32 16.44 10.66 16.36 10H19.74C19.9 10.64 20 11.31 20 12C20 12.69 19.9 13.36 19.74 14H16.36Z'],
        'mdi-translate' => ['M12.87 15.07L10.33 12.56L10.36 12.53C12.1 10.59 13.34 8.36 14.07 6H17V4H10V2H8V4H1V6H12.17C11.5 7.92 10.44 9.75 9 11.35C8.07 10.32 7.3 9.19 6.69 8H4.69C5.42 9.63 6.42 11.17 7.67 12.56L2.58 17.58L4 19L9 14L12.11 17.11L12.87 15.07M18.5 10H16.5L12 22H14L15.12 19H19.87L21 22H23L18.5 10M15.88 17L17.5 12.67L19.12 17H15.88Z'],
        'mdi-web' => ['M16.36 14C16.44 13.34 16.5 12.68 16.5 12C16.5 11.32 16.44 10.66 16.36 10H19.74C19.9 10.64 20 11.31 20 12C20 12.69 19.9 13.36 19.74 14M14.59 19.56C15.19 18.45 15.65 17.25 15.92 16H18.88C17.91 17.68 16.38 18.96 14.59 19.56M14.34 14H9.66C9.56 13.34 9.5 12.68 9.5 12C9.5 11.32 9.56 10.65 9.66 10H14.34C14.43 10.65 14.5 11.32 14.5 12C14.5 12.68 14.43 13.34 14.34 14M12 19.96C11.17 18.76 10.5 17.43 10.18 16H13.82C13.5 17.43 12.83 18.76 12 19.96M8 8H5.12C6.09 6.32 7.62 5.04 9.41 4.44C8.81 5.55 8.35 6.75 8.08 8M5.12 16H8.08C8.35 17.25 8.81 18.45 9.41 19.56C7.62 18.96 6.09 17.68 5.12 16M4.26 14C4.1 13.36 4 12.69 4 12C4 11.31 4.1 10.64 4.26 10H7.64C7.56 10.66 7.5 11.32 7.5 12C7.5 12.68 7.56 13.34 7.64 14M12 4.03C12.83 5.23 13.5 6.57 13.82 8H10.18C10.5 6.57 11.17 5.23 12 4.03M18.88 8H15.92C15.65 6.75 15.19 5.55 14.59 4.44C16.38 5.04 17.91 6.31 18.88 8M12 2C6.47 2 2 6.5 2 12A10 10 0 0 0 12 22A10 10 0 0 0 22 12A10 10 0 0 0 12 2Z'],
        'mdi-flag-outline' => ['M14.4 6L14 4H5V21H7V14H12.6L13 16H20V6H14.4M18 14H14.6L14.2 12H7V6H12.4L12.8 8H18V14Z'],
    ];
    $languageAppearance = is_array($languageStyle['appearance'] ?? null) ? $languageStyle['appearance'] : [];
    $flagPosition = $blockFlagPosition ?? (string) ($languageStyle['flag_position'] ?? 'before');
    $flagShape = $blockFlagShape ?? (string) ($languageStyle['flag_shape'] ?? 'rounded');
    $flagSize = $blockFlagSize ?? (string) ($languageStyle['flag_size'] ?? 'normal');
    $languageStyleVariables = [];
    $languageColorVariableMap = [
        'text_color' => '--rw-public-language-text-color',
        'background_color' => '--rw-public-language-background-color',
        'hover_text_color' => '--rw-public-language-hover-text-color',
        'hover_background_color' => '--rw-public-language-hover-background-color',
        'pressed_text_color' => '--rw-public-language-pressed-text-color',
        'pressed_background_color' => '--rw-public-language-pressed-background-color',
        'active_text_color' => '--rw-public-language-active-text-color',
        'active_background_color' => '--rw-public-language-active-background-color',
    ];
    $languageCssColor = function (string $field) use ($layoutNormalizer, $languageAppearance): ?string {
        $color = $layoutNormalizer->normalizeCssColor($languageAppearance[$field] ?? null);
        $token = is_string($languageAppearance[$field.'_token'] ?? null) && preg_match('/^[a-z0-9_-]+$/', $languageAppearance[$field.'_token']) === 1
            ? (string) $languageAppearance[$field.'_token']
            : '';

        return $color ?? ($token !== '' ? "var(--rw-public-color-{$token})" : null);
    };

    foreach ($languageColorVariableMap as $field => $cssVariable) {
        $cssColor = $languageCssColor($field);

        if ($cssColor !== null) {
            $languageStyleVariables[$cssVariable] = $cssColor;
        }
    }

    $fontFamilyToken = is_string($languageAppearance['font_family_token'] ?? null) && $languageAppearance['font_family_token'] !== 'inherit' && preg_match('/^[a-z0-9_-]+$/', $languageAppearance['font_family_token']) === 1
        ? (string) $languageAppearance['font_family_token']
        : '';
    $typographyPreset = is_string($languageAppearance['typography_preset'] ?? null) && $languageAppearance['typography_preset'] !== 'inherit' && preg_match('/^[a-z0-9_-]+$/', $languageAppearance['typography_preset']) === 1
        ? (string) $languageAppearance['typography_preset']
        : '';
    $fontSizeToken = is_string($languageAppearance['font_size_token'] ?? null) && $languageAppearance['font_size_token'] !== 'inherit' && preg_match('/^[a-z0-9_-]+$/', $languageAppearance['font_size_token']) === 1
        ? (string) $languageAppearance['font_size_token']
        : '';
    $fontWeightValues = [
        'normal' => '400',
        'medium' => '500',
        'semibold' => '600',
        'bold' => '700',
    ];
    $fontWeight = (string) ($languageAppearance['font_weight'] ?? 'inherit');

    if ($typographyPreset !== '') {
        $languageStyleVariables['--rw-public-language-font-family'] = "var(--rw-public-typo-{$typographyPreset}-font-family)";
        $languageStyleVariables['--rw-public-language-font-size'] = "var(--rw-public-typo-{$typographyPreset}-font-size)";
        $languageStyleVariables['--rw-public-language-font-weight'] = "var(--rw-public-typo-{$typographyPreset}-font-weight)";
        $languageStyleVariables['--rw-public-language-line-height'] = "var(--rw-public-typo-{$typographyPreset}-line-height)";
        $languageStyleVariables['--rw-public-language-letter-spacing'] = "var(--rw-public-typo-{$typographyPreset}-letter-spacing)";
    }

    if ($fontFamilyToken !== '') {
        $languageStyleVariables['--rw-public-language-font-family'] = "var(--rw-public-font-{$fontFamilyToken})";
    }

    if ($fontSizeToken !== '') {
        $languageStyleVariables['--rw-public-language-font-size'] = "var(--rw-public-font-size-{$fontSizeToken})";
    }

    if (isset($fontWeightValues[$fontWeight])) {
        $languageStyleVariables['--rw-public-language-font-weight'] = $fontWeightValues[$fontWeight];
    }

    $labelFor = function (array $language, string $display): string {
        $code = strtoupper((string) ($language['locale'] ?? ''));
        $name = trim((string) ($language['name'] ?? '')) ?: $code;
        $nativeName = trim((string) ($language['native_name'] ?? '')) ?: $name;

        return match ($display) {
            'name', 'flag_name' => $name,
            'native_name', 'flag_native_name' => $nativeName,
            'code_name' => trim($code.' '.$name),
            'code_native_name' => trim($code.' '.$nativeName),
            default => $code,
        };
    };
    $usesFlag = str_starts_with($labelDisplay, 'flag_');
    $languagePayloads = $hideMissing
        ? $availableTranslations->map(function (array $translation) use ($languages): array {
            $language = $languages->get((string) ($translation['locale'] ?? ''));

            return array_replace(is_array($language) ? $language : [], $translation);
        })
        : $languages->map(function (array $language) use ($availableTranslations, $languageSettings): array {
            $translation = $availableTranslations->get((string) $language['locale']);

            return array_replace($language, is_array($translation) ? $translation : [
                'url' => $languageSettings->pathPrefix((string) $language['locale']) ?: '/',
                'active' => false,
            ]);
        });
    $languagePayloads = $languagePayloads
        ->filter(fn (array $language): bool => (string) ($language['locale'] ?? '') !== '')
        ->filter(fn (array $language): bool => $showCurrent || empty($language['active']))
        ->sortBy(fn (array $language): int => (int) ($language['sort_order'] ?? 0))
        ->values();
    $dropdownLanguagePayloads = $languagePayloads
        ->reject(fn (array $language): bool => ! empty($language['active']))
        ->values();
    $currentLanguage = $languages->get((string) $locale) ?? $languagePayloads->firstWhere('active', true) ?? $languagePayloads->first();
    $currentLabel = is_array($currentLanguage) ? $labelFor($currentLanguage, $labelDisplay) : strtoupper((string) $locale);
    $currentFlag = is_array($currentLanguage['flag'] ?? null) ? $currentLanguage['flag'] : null;
    $currentFlagUrl = is_string($currentFlag['url'] ?? null) ? trim((string) $currentFlag['url']) : '';
    $hideCurrentLabel = $labelDisplay === 'flag_only' && $currentFlagUrl !== '';
    $languageClasses = array_filter([
        'rw-public-language-menu',
        'rw-public-language-menu--desktop-'.($desktopLanguage['display'] ?? 'horizontal'),
        'rw-public-language-menu--tablet-'.($tabletLanguage['display'] ?? 'dropdown'),
        'rw-public-language-menu--mobile-'.($mobileLanguage['display'] ?? 'dropdown'),
        'rw-public-language-menu--desktop-align-'.($desktopLanguage['alignment'] ?? 'right'),
        'rw-public-language-menu--tablet-align-'.($tabletLanguage['alignment'] ?? 'right'),
        'rw-public-language-menu--mobile-align-'.($mobileLanguage['alignment'] ?? 'right'),
        'rw-public-language-menu--variant-'.($languageStyle['item_variant'] ?? 'pill'),
        'rw-public-language-menu--spacing-'.($languageStyle['spacing'] ?? 'normal'),
        'rw-public-language-menu--flag-'.$flagPosition,
        'rw-public-language-menu--flag-shape-'.$flagShape,
        'rw-public-language-menu--flag-size-'.$flagSize,
        ($desktopLanguage['icon'] ?? 'none') !== 'none' ? 'rw-public-language-menu--desktop-summary-icon' : null,
        ($tabletLanguage['icon'] ?? 'none') !== 'none' ? 'rw-public-language-menu--tablet-summary-icon' : null,
        ($mobileLanguage['icon'] ?? 'none') !== 'none' ? 'rw-public-language-menu--mobile-summary-icon' : null,
    ]);
@endphp

@if (! empty($site['multilingual_enabled']) && $languagePayloads->count() > 0)
    <nav
        class="{{ implode(' ', $languageClasses) }}"
        aria-label="{{ public_text('language_switcher.label', 'Language selection', $locale) }}"
        @if ($languageStyleVariables !== [])
            style="@foreach ($languageStyleVariables as $cssVariableName => $cssVariableValue) {{ $cssVariableName }}: {{ $cssVariableValue }}; @endforeach"
        @endif
    >
        @if ($hasLanguageHeading)
            <span class="rw-public-language-menu__heading" aria-hidden="true">
                @foreach ($languageHeadings as $device => $heading)
                    @if ($heading['label'] !== '')
                        <span class="rw-public-language-menu__heading-device rw-public-language-menu__heading-device--{{ $device }}">
                            <span class="rw-public-language-menu__heading-label">{{ $heading['label'] }}</span>
                        </span>
                    @endif
                @endforeach
            </span>
        @endif

        <details class="rw-public-language-menu__dropdown">
            <summary class="rw-public-language-menu__summary" aria-label="{{ $currentLabel }}">
                @foreach ($languageHeadings as $device => $heading)
                    @php
                        $summaryIcon = preg_match('/^mdi-[a-z0-9-]+$/', $heading['icon']) === 1 ? $heading['icon'] : 'none';
                    @endphp
                    @if ($summaryIcon !== 'none')
                        <span class="rw-public-language-menu__summary-icon rw-public-language-menu__summary-icon--{{ $device }}" aria-hidden="true">
                            @if (isset($languageIconPaths[$summaryIcon]))
                                <svg viewBox="0 0 24 24" focusable="false">
                                    @foreach ($languageIconPaths[$summaryIcon] as $path)
                                        <path d="{{ $path }}" />
                                    @endforeach
                                </svg>
                            @else
                                <span class="mdi {{ $summaryIcon }}" aria-hidden="true"></span>
                            @endif
                        </span>
                    @endif
                @endforeach
                @if ($usesFlag && $currentFlagUrl !== '')
                    <img
                        class="rw-public-language-menu__flag rw-public-language-menu__summary-flag"
                        src="{{ $currentFlagUrl }}"
                        alt=""
                        loading="lazy"
                    >
                @endif
                <span class="rw-public-language-menu__summary-label {{ $hideCurrentLabel ? 'rw-public-language-menu__label--visually-hidden' : '' }}">{{ $currentLabel }}</span>
                <span class="rw-public-language-menu__check mdi mdi-check" aria-hidden="true"></span>
            </summary>
            <div class="rw-public-language-menu__dropdown-list">
                @foreach ($dropdownLanguagePayloads as $translation)
                    @include('public.system.blocks.partials.language-switcher-link', [
                        'translation' => $translation,
                        'labelDisplay' => $labelDisplay,
                        'labelFor' => $labelFor,
                        'usesFlag' => $usesFlag,
                    ])
                @endforeach
            </div>
        </details>

        <div class="rw-public-language-menu__list">
            @foreach ($languagePayloads as $translation)
                @include('public.system.blocks.partials.language-switcher-link', [
                    'translation' => $translation,
                    'labelDisplay' => $labelDisplay,
                    'labelFor' => $labelFor,
                    'usesFlag' => $usesFlag,
                ])
            @endforeach
        </div>
    </nav>
@endif
