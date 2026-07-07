@php
    $menu = is_array($block['menu'] ?? null) ? $block['menu'] : ['title' => null, 'items' => []];
    $items = $menu['items'] ?? [];
    $title = trim((string) ($block['title'] ?? ''));
    $locale = $site['current_locale'] ?? null;
    $label = $menu['title'] ?? public_text('navigation.site_menu_label', 'Site navigation', $locale);
    $instanceId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($placement['id'] ?? $block['id'] ?? $block['cms_menu_id'] ?? 'menu'));
    $panelId = 'rw-public-site-menu-panel-'.$instanceId;
    $styleConfig = is_array($placement['style_config'] ?? null) ? $placement['style_config'] : [];
    $layoutNormalizer = app(App\Support\Cms\CmsResponsiveLayoutNormalizer::class);
    $menuStyle = $layoutNormalizer->normalizeMenuStyle(is_array($styleConfig['menu'] ?? null) ? $styleConfig['menu'] : null);
    $menuDevices = is_array($menuStyle['devices'] ?? null) ? $menuStyle['devices'] : [];
    $desktopMenu = is_array($menuDevices['desktop'] ?? null) ? $menuDevices['desktop'] : [];
    $tabletMenu = is_array($menuDevices['tablet'] ?? null) ? $menuDevices['tablet'] : [];
    $mobileMenu = is_array($menuDevices['mobile'] ?? null) ? $menuDevices['mobile'] : [];
    $menuToggleLabels = [
        'desktop' => trim((string) ($desktopMenu['toggle_label'] ?? '')),
        'tablet' => trim((string) ($tabletMenu['toggle_label'] ?? '')),
        'mobile' => trim((string) ($mobileMenu['toggle_label'] ?? '')),
    ];
    $menuToggles = [
        'desktop' => is_array($desktopMenu['toggle'] ?? null) ? $desktopMenu['toggle'] : [],
        'tablet' => is_array($tabletMenu['toggle'] ?? null) ? $tabletMenu['toggle'] : [],
        'mobile' => is_array($mobileMenu['toggle'] ?? null) ? $mobileMenu['toggle'] : [],
    ];
    $hasVisibleToggleLabel = implode('', $menuToggleLabels) !== '';
    $menuAppearance = is_array($menuStyle['appearance'] ?? null) ? $menuStyle['appearance'] : [];
    $menuStyleVariables = [];
    $menuColorVariableMap = [
        'text_color' => '--rw-public-menu-text-color',
        'background_color' => '--rw-public-menu-background-color',
        'hover_text_color' => '--rw-public-menu-hover-text-color',
        'hover_background_color' => '--rw-public-menu-hover-background-color',
        'pressed_text_color' => '--rw-public-menu-pressed-text-color',
        'pressed_background_color' => '--rw-public-menu-pressed-background-color',
        'active_text_color' => '--rw-public-menu-active-text-color',
        'active_background_color' => '--rw-public-menu-active-background-color',
    ];
    $menuCssColor = function (string $field) use ($layoutNormalizer, $menuAppearance): ?string {
        $color = $layoutNormalizer->normalizeCssColor($menuAppearance[$field] ?? null);
        $token = is_string($menuAppearance[$field.'_token'] ?? null) && preg_match('/^[a-z0-9_-]+$/', $menuAppearance[$field.'_token']) === 1
            ? (string) $menuAppearance[$field.'_token']
            : '';

        return $color ?? ($token !== '' ? "var(--rw-public-color-{$token})" : null);
    };
    $toggleCssColor = function (array $toggle, string $field) use ($layoutNormalizer): ?string {
        $color = $layoutNormalizer->normalizeCssColor($toggle[$field] ?? null);
        $token = is_string($toggle[$field.'_token'] ?? null) && preg_match('/^[a-z0-9_-]+$/', $toggle[$field.'_token']) === 1
            ? (string) $toggle[$field.'_token']
            : '';

        return $color ?? ($token !== '' ? "var(--rw-public-color-{$token})" : null);
    };

    foreach ($menuColorVariableMap as $field => $cssVariable) {
        $cssColor = $menuCssColor($field);

        if ($cssColor !== null) {
            $menuStyleVariables[$cssVariable] = $cssColor;
        }
    }

    $fontFamilyToken = is_string($menuAppearance['font_family_token'] ?? null) && $menuAppearance['font_family_token'] !== 'inherit' && preg_match('/^[a-z0-9_-]+$/', $menuAppearance['font_family_token']) === 1
        ? (string) $menuAppearance['font_family_token']
        : '';
    $typographyPreset = is_string($menuAppearance['typography_preset'] ?? null) && $menuAppearance['typography_preset'] !== 'inherit' && preg_match('/^[a-z0-9_-]+$/', $menuAppearance['typography_preset']) === 1
        ? (string) $menuAppearance['typography_preset']
        : '';
    $fontSizeToken = is_string($menuAppearance['font_size_token'] ?? null) && $menuAppearance['font_size_token'] !== 'inherit' && preg_match('/^[a-z0-9_-]+$/', $menuAppearance['font_size_token']) === 1
        ? (string) $menuAppearance['font_size_token']
        : '';
    $fontWeightValues = [
        'normal' => '400',
        'medium' => '500',
        'semibold' => '600',
        'bold' => '700',
    ];
    $fontWeight = (string) ($menuAppearance['font_weight'] ?? 'inherit');

    if ($typographyPreset !== '') {
        $menuStyleVariables['--rw-public-menu-font-family'] = "var(--rw-public-typo-{$typographyPreset}-font-family)";
        $menuStyleVariables['--rw-public-menu-font-size'] = "var(--rw-public-typo-{$typographyPreset}-font-size)";
        $menuStyleVariables['--rw-public-menu-font-weight'] = "var(--rw-public-typo-{$typographyPreset}-font-weight)";
        $menuStyleVariables['--rw-public-menu-line-height'] = "var(--rw-public-typo-{$typographyPreset}-line-height)";
        $menuStyleVariables['--rw-public-menu-letter-spacing'] = "var(--rw-public-typo-{$typographyPreset}-letter-spacing)";
    }

    if ($fontFamilyToken !== '') {
        $menuStyleVariables['--rw-public-menu-font-family'] = "var(--rw-public-font-{$fontFamilyToken})";
    }

    if ($fontSizeToken !== '') {
        $menuStyleVariables['--rw-public-menu-font-size'] = "var(--rw-public-font-size-{$fontSizeToken})";
    }

    if (isset($fontWeightValues[$fontWeight])) {
        $menuStyleVariables['--rw-public-menu-font-weight'] = $fontWeightValues[$fontWeight];
    }
    $toggleColorVariableMap = [
        'color' => '--rw-public-menu-toggle-%s-color',
        'background_color' => '--rw-public-menu-toggle-%s-background-color',
        'hover_color' => '--rw-public-menu-toggle-%s-hover-color',
        'hover_background_color' => '--rw-public-menu-toggle-%s-hover-background-color',
    ];

    foreach ($menuToggles as $device => $toggle) {
        foreach ($toggleColorVariableMap as $field => $cssVariablePattern) {
            $cssColor = $toggleCssColor($toggle, $field);

            if ($cssColor !== null) {
                $menuStyleVariables[sprintf($cssVariablePattern, $device)] = $cssColor;
            }
        }
    }

    $toggleIconPaths = [
        'hamburger' => ['M4 7h16M4 12h16M4 17h16'],
        'dots' => ['M7 12h.01M12 12h.01M17 12h.01'],
        'grid' => ['M5 5h5v5H5zM14 5h5v5h-5zM5 14h5v5H5zM14 14h5v5h-5z'],
    ];
    $menuClasses = array_filter([
        'rw-public-menu',
        'rw-public-menu--desktop-'.($desktopMenu['display'] ?? 'horizontal'),
        'rw-public-menu--tablet-'.($tabletMenu['display'] ?? 'hamburger'),
        'rw-public-menu--mobile-'.($mobileMenu['display'] ?? 'hamburger'),
        'rw-public-menu--desktop-align-'.($desktopMenu['alignment'] ?? 'right'),
        'rw-public-menu--tablet-align-'.($tabletMenu['alignment'] ?? 'right'),
        'rw-public-menu--mobile-align-'.($mobileMenu['alignment'] ?? 'right'),
        'rw-public-menu--variant-'.($menuStyle['item_variant'] ?? 'pill'),
        'rw-public-menu--spacing-'.($menuStyle['spacing'] ?? 'normal'),
        'rw-public-menu--drawer-'.($menuStyle['drawer_side'] ?? 'right'),
        'rw-public-menu--drawer-top-'.str_replace('_', '-', (string) ($menuStyle['drawer_top'] ?? 'viewport')),
        'rw-public-menu--submenu-'.($menuStyle['submenu_behavior'] ?? 'hover'),
        'rw-public-menu--submenu-side-'.($menuStyle['submenu_side'] ?? 'right'),
        'rw-public-menu--desktop-toggle-shape-'.($menuToggles['desktop']['shape'] ?? 'pill'),
        'rw-public-menu--tablet-toggle-shape-'.($menuToggles['tablet']['shape'] ?? 'pill'),
        'rw-public-menu--mobile-toggle-shape-'.($menuToggles['mobile']['shape'] ?? 'pill'),
        'rw-public-menu--desktop-toggle-size-'.($menuToggles['desktop']['size'] ?? 'normal'),
        'rw-public-menu--tablet-toggle-size-'.($menuToggles['tablet']['size'] ?? 'normal'),
        'rw-public-menu--mobile-toggle-size-'.($menuToggles['mobile']['size'] ?? 'normal'),
    ]);
@endphp

@if (! empty($items))
    <div class="rw-public-menu-block">
        @if ($title !== '')
            <h2 class="rw-public-menu-block__title">{{ $title }}</h2>
        @endif

        <div
            class="{{ implode(' ', $menuClasses) }}"
            data-rw-public-menu
            @if ($menuStyleVariables !== [])
                style="@foreach ($menuStyleVariables as $cssVariableName => $cssVariableValue) {{ $cssVariableName }}: {{ $cssVariableValue }}; @endforeach"
            @endif
        >
            <div class="rw-public-menu__desktop">
                @include('public.system.partials.navigation', [
                    'items' => $items,
                    'label' => $label,
                    'title' => $menu['title'] ?? null,
                    'nav_id_prefix' => $panelId.'-desktop',
                ])
            </div>

            <button
                type="button"
                class="rw-public-menu-toggle"
                aria-expanded="false"
                aria-controls="{{ $panelId }}"
                aria-label="{{ public_text('mobile_menu.toggle_label', 'Open or close menu', $locale) }}"
                data-rw-public-menu-toggle
            >
                @foreach ($menuToggleLabels as $device => $toggleLabel)
                    <span class="rw-public-menu-toggle__label rw-public-menu-toggle__label--{{ $device }}">{{ $toggleLabel }}</span>
                @endforeach
                @foreach ($menuToggles as $device => $toggle)
                    @php
                        $icon = in_array($toggle['icon'] ?? null, array_keys($toggleIconPaths), true) ? $toggle['icon'] : 'hamburger';
                    @endphp
                    <svg class="rw-public-menu-toggle__icon rw-public-menu-toggle__icon--{{ $device }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        @foreach ($toggleIconPaths[$icon] as $path)
                            <path d="{{ $path }}" />
                        @endforeach
                    </svg>
                @endforeach
            </button>

            <div class="rw-public-menu__backdrop" hidden data-rw-public-menu-backdrop></div>

            <div
                id="{{ $panelId }}"
                class="rw-public-menu__panel"
                hidden
                data-rw-public-menu-panel
            >
                <div class="rw-public-menu__panel-header">
                    @if ($hasVisibleToggleLabel)
                        <span>
                            @foreach ($menuToggleLabels as $device => $toggleLabel)
                                <span class="rw-public-menu-toggle__label rw-public-menu-toggle__label--{{ $device }}">{{ $toggleLabel }}</span>
                            @endforeach
                        </span>
                    @endif
                    <button
                        type="button"
                        class="rw-public-menu__close"
                        aria-label="{{ public_text('mobile_menu.close_label', 'Close menu', $locale) }}"
                        data-rw-public-menu-close
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                @include('public.system.partials.navigation', [
                    'items' => $items,
                    'label' => public_text('navigation.mobile_site_menu_label', 'Mobile site navigation', $locale),
                    'title' => $menu['title'] ?? null,
                    'nav_id_prefix' => $panelId.'-panel',
                ])
            </div>
        </div>
    </div>
@endif
