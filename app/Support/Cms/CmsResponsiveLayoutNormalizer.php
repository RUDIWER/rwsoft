<?php

namespace App\Support\Cms;

class CmsResponsiveLayoutNormalizer
{
    public const DEVICES = ['desktop', 'tablet', 'mobile'];

    public const COLUMN_COUNT = 12;

    private const BOX_GROUPS = ['padding', 'margin'];

    private const BOX_SIDES = ['top', 'right', 'bottom', 'left'];

    private const BOX_UNITS = ['px', 'rem', 'em', '%', 'vw', 'vh'];

    private const BACKGROUND_MODES = ['cover', 'contain', 'stretch', 'center', 'repeat', 'repeat-x', 'repeat-y'];

    private const BACKGROUND_POSITIONS = ['center center', 'center top', 'center bottom', 'left center', 'right center'];

    private const FONT_FAMILY_TOKENS = ['inherit', 'body', 'heading', 'brand', 'accent'];

    private const FONT_SIZE_TOKENS = ['inherit', 'body', 'small', 'nav', 'brand', 'baseline'];

    private const FONT_WEIGHT_TOKENS = ['inherit', 'normal', 'medium', 'semibold', 'bold'];

    private const TYPOGRAPHY_PRESET_TOKENS = ['inherit', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'body', 'lead', 'small', 'caption', 'eyebrow'];

    private const COLOR_TOKENS = ['page', 'surface', 'surface-muted', 'text', 'muted', 'border', 'primary', 'primary-strong', 'primary-contrast', 'success', 'success-bg', 'error', 'error-bg'];

    private const LOGO_SIZE_TOKENS = ['small', 'default', 'large'];

    private const Z_INDEX_TOKENS = ['auto', '0', '10', '20', '30', '40', '50'];

    private const CONTENT_VERTICAL_ALIGNMENT_TOKENS = ['', 'top', 'middle', 'bottom'];

    public const MENU_DISPLAY_MODES = ['horizontal', 'vertical', 'hamburger'];

    public const LANGUAGE_DISPLAY_MODES = ['horizontal', 'vertical', 'dropdown'];

    public const MENU_ALIGNMENT_TOKENS = ['left', 'center', 'right'];

    public const MENU_ITEM_VARIANTS = ['plain', 'pill', 'underline', 'button'];

    public const MENU_SPACING_TOKENS = ['compact', 'normal', 'spacious'];

    public const MENU_DRAWER_SIDES = ['left', 'right'];

    public const MENU_DRAWER_TOPS = ['viewport', 'below_sticky_header'];

    public const MENU_SUBMENU_BEHAVIORS = ['hover'];

    public const MENU_SUBMENU_SIDES = ['left', 'right'];

    public const MENU_TOGGLE_ICONS = ['hamburger', 'dots', 'grid'];

    public const MENU_TOGGLE_SHAPES = ['pill', 'rounded', 'square', 'circle'];

    public const MENU_TOGGLE_SIZES = ['compact', 'normal', 'large'];

    public const LANGUAGE_LABEL_DISPLAYS = ['code', 'name', 'native_name', 'code_name', 'code_native_name', 'flag_only', 'flag_code', 'flag_name', 'flag_native_name'];

    public const LANGUAGE_FLAG_POSITIONS = ['before', 'after'];

    public const LANGUAGE_FLAG_SHAPES = ['rectangle', 'rounded', 'circle'];

    public const LANGUAGE_FLAG_SIZES = ['small', 'normal', 'large'];

    public const LANGUAGE_ICONS = ['none', 'mdi-earth', 'mdi-translate', 'mdi-web', 'mdi-flag-outline'];

    public const FORM_FIELD_SPACING_TOKENS = ['compact', 'normal', 'spacious'];

    public const FORM_INPUT_RADIUS_TOKENS = ['inherit', 'none', 'sm', 'md', 'lg', 'pill'];

    public const FORM_INPUT_BORDER_TOKENS = ['default', 'none', 'subtle', 'strong', 'primary'];

    public const FORM_SUBMIT_ALIGNMENT_TOKENS = ['inherit', 'left', 'center', 'right', 'stretch'];

    public const FORM_SUBMIT_VARIANT_TOKENS = ['default', 'outline', 'ghost'];

    public const FORM_COLOR_FIELDS = [
        'input_background_color',
        'input_text_color',
    ];

    public const MENU_TOGGLE_COLOR_FIELDS = [
        'color',
        'background_color',
        'hover_color',
        'hover_background_color',
    ];

    public const MENU_COLOR_FIELDS = [
        'text_color',
        'background_color',
        'hover_text_color',
        'hover_background_color',
        'pressed_text_color',
        'pressed_background_color',
        'active_text_color',
        'active_background_color',
    ];

    /**
     * @param  array<string, mixed>|null  $layout
     * @return array<string, array{x: int, y: int, w: int, h: int}>
     */
    public function normalizeLayout(?array $layout, int $defaultWidth = self::COLUMN_COUNT): array
    {
        $normalized = [];

        foreach (self::DEVICES as $device) {
            $normalized[$device] = $this->normalizeDeviceLayout(
                is_array($layout[$device] ?? null) ? $layout[$device] : null,
                $defaultWidth,
            );
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $placements
     * @return array<int, array<string, mixed>>
     */
    public function resolvePlacementLayoutCollisions(array $placements, bool $applyAlignment = true): array
    {
        $resolved = array_values($placements);

        foreach ($resolved as $index => $placement) {
            $resolved[$index]['layout_config'] = $this->normalizePlacementLayoutConfigForData($placement, $index, $applyAlignment);
        }

        foreach (self::DEVICES as $device) {
            $placed = [];
            $items = [];

            foreach ($resolved as $index => $placement) {
                if (! $this->placementIsVisibleOnDevice($placement, $device)) {
                    continue;
                }

                $layout = $placement['layout_config'][$device] ?? ['x' => 0, 'y' => $index, 'w' => self::COLUMN_COUNT, 'h' => 1];
                $items[] = ['index' => $index, 'layout' => $layout];
            }

            usort($items, fn (array $left, array $right): int => ((int) $left['layout']['y'] <=> (int) $right['layout']['y'])
                ?: ((int) $left['layout']['x'] <=> (int) $right['layout']['x'])
                ?: ((int) $left['index'] <=> (int) $right['index']));

            foreach ($items as $item) {
                $layout = $item['layout'];

                while ($this->layoutOverlapsAny($layout, $placed)) {
                    $layout['y'] = (int) $layout['y'] + 1;
                }

                $placed[] = $layout;
                $resolved[(int) $item['index']]['layout_config'][$device] = $layout;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>|null  $style
     * @return array<string, mixed>
     */
    public function normalizeStyle(?array $style): array
    {
        $normalized = [];
        $style ??= [];

        $normalized['devices'] = [];
        $legacyAppearance = is_array($style['appearance'] ?? null) ? $style['appearance'] : [];
        $legacyDesktop = ['appearance' => $legacyAppearance];

        foreach (self::DEVICES as $device) {
            $deviceStyles = is_array($style['devices'] ?? null) ? $style['devices'] : [];
            $deviceStyle = is_array($deviceStyles[$device] ?? null)
                ? $deviceStyles[$device]
                : (is_array($style[$device] ?? null) ? $style[$device] : []);

            if ($device === 'desktop' && $deviceStyle === []) {
                $deviceStyle = $legacyDesktop;
            }

            $normalized['devices'][$device] = $this->normalizeDeviceStyle(
                $deviceStyle,
                $device === 'desktop' ? null : $normalized['devices']['desktop']
            );
        }

        $normalized['box'] = $this->normalizeBoxSpacing(is_array($style['box'] ?? null) ? $style['box'] : null);
        $normalized['menu'] = $this->normalizeMenuStyle(is_array($style['menu'] ?? null) ? $style['menu'] : null);
        $normalized['language'] = $this->normalizeLanguageStyle(is_array($style['language'] ?? null) ? $style['language'] : null);
        $normalized['form'] = $this->normalizeFormStyle(is_array($style['form'] ?? null) ? $style['form'] : null);
        $normalized['appearance_container'] = $this->normalizeAppearanceContainer(
            is_array($style['appearance_container'] ?? null) ? $style['appearance_container'] : null,
            $this->hasContainerAppearance($normalized['devices'])
        );

        $developer = is_array($style['developer'] ?? null) ? $style['developer'] : [];
        $cssSource = $this->nullableString($developer['css_source'] ?? null);

        if ($cssSource !== null) {
            $normalized['developer'] = [
                'css_source' => $cssSource,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $container
     * @return array{enabled: bool}
     */
    public function normalizeAppearanceContainer(?array $container, bool $defaultEnabled = false): array
    {
        $enabled = filter_var($container['enabled'] ?? $defaultEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return [
            'enabled' => $enabled === true || $defaultEnabled,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $deviceStyles
     */
    private function hasContainerAppearance(array $deviceStyles): bool
    {
        foreach ($deviceStyles as $deviceStyle) {
            $appearance = is_array($deviceStyle['appearance'] ?? null) ? $deviceStyle['appearance'] : [];

            if (($appearance['background_color'] ?? null) !== null || ($appearance['background_color_token'] ?? '') !== '') {
                return true;
            }

            if (($appearance['padding'] ?? 'none') !== 'none') {
                return true;
            }

            if (! in_array($appearance['radius'] ?? 'inherit', ['inherit', 'none'], true)) {
                return true;
            }

            if (($appearance['border'] ?? 'none') !== 'none') {
                return true;
            }

            if (($appearance['shadow'] ?? 'none') !== 'none') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $form
     * @return array<string, mixed>
     */
    public function normalizeFormStyle(?array $form): array
    {
        $form ??= [];
        $normalized = [
            'field_spacing' => $this->allowedValue($form['field_spacing'] ?? null, self::FORM_FIELD_SPACING_TOKENS, 'normal'),
            'label_weight' => $this->allowedValue($form['label_weight'] ?? null, $this->fontWeightTokens(), 'inherit'),
            'input_radius' => $this->allowedValue($form['input_radius'] ?? null, self::FORM_INPUT_RADIUS_TOKENS, 'inherit'),
            'input_border' => $this->allowedValue($form['input_border'] ?? null, self::FORM_INPUT_BORDER_TOKENS, 'default'),
            'submit_alignment' => $this->allowedValue($form['submit_alignment'] ?? null, self::FORM_SUBMIT_ALIGNMENT_TOKENS, 'inherit'),
            'submit_variant' => $this->allowedValue($form['submit_variant'] ?? null, self::FORM_SUBMIT_VARIANT_TOKENS, 'default'),
        ];

        foreach (self::FORM_COLOR_FIELDS as $field) {
            $normalized[$field] = $this->normalizeCssColor($form[$field] ?? null);
            $normalized[$field.'_token'] = $this->allowedValue($form[$field.'_token'] ?? null, $this->colorTokens(), '');
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $language
     * @return array<string, mixed>
     */
    public function normalizeLanguageStyle(?array $language): array
    {
        $language ??= [];
        $normalized = [
            'devices' => [],
            'item_variant' => $this->allowedValue($language['item_variant'] ?? null, self::MENU_ITEM_VARIANTS, 'pill'),
            'spacing' => $this->allowedValue($language['spacing'] ?? null, self::MENU_SPACING_TOKENS, 'normal'),
            'appearance' => $this->normalizeMenuAppearance(is_array($language['appearance'] ?? null) ? $language['appearance'] : null),
            'flag_position' => $this->allowedValue($language['flag_position'] ?? null, self::LANGUAGE_FLAG_POSITIONS, 'before'),
            'flag_shape' => $this->allowedValue($language['flag_shape'] ?? null, self::LANGUAGE_FLAG_SHAPES, 'rounded'),
            'flag_size' => $this->allowedValue($language['flag_size'] ?? null, self::LANGUAGE_FLAG_SIZES, 'normal'),
        ];
        $deviceStyles = is_array($language['devices'] ?? null) ? $language['devices'] : [];

        foreach (self::DEVICES as $device) {
            $fallbackDisplay = $device === 'desktop' ? 'horizontal' : 'dropdown';
            $source = is_array($deviceStyles[$device] ?? null) ? $deviceStyles[$device] : [];

            $normalized['devices'][$device] = [
                'display' => $this->allowedValue($source['display'] ?? null, self::LANGUAGE_DISPLAY_MODES, $fallbackDisplay),
                'alignment' => $this->allowedValue($source['alignment'] ?? null, self::MENU_ALIGNMENT_TOKENS, 'right'),
                'label' => $this->menuToggleLabel($source['label'] ?? null),
                'icon' => $this->languageIcon($source['icon'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $menu
     * @return array<string, mixed>
     */
    public function normalizeMenuStyle(?array $menu): array
    {
        $menu ??= [];
        $normalized = [
            'devices' => [],
            'item_variant' => $this->allowedValue($menu['item_variant'] ?? null, self::MENU_ITEM_VARIANTS, 'pill'),
            'spacing' => $this->allowedValue($menu['spacing'] ?? null, self::MENU_SPACING_TOKENS, 'normal'),
            'drawer_side' => $this->allowedValue($menu['drawer_side'] ?? null, self::MENU_DRAWER_SIDES, 'right'),
            'drawer_top' => $this->allowedValue($menu['drawer_top'] ?? null, self::MENU_DRAWER_TOPS, 'viewport'),
            'submenu_behavior' => $this->allowedValue($menu['submenu_behavior'] ?? null, self::MENU_SUBMENU_BEHAVIORS, 'hover'),
            'submenu_side' => $this->allowedValue($menu['submenu_side'] ?? null, self::MENU_SUBMENU_SIDES, 'right'),
            'appearance' => $this->normalizeMenuAppearance(is_array($menu['appearance'] ?? null) ? $menu['appearance'] : null),
        ];
        $deviceStyles = is_array($menu['devices'] ?? null) ? $menu['devices'] : [];

        $fallbackToggle = null;

        foreach (self::DEVICES as $device) {
            $fallback = $device === 'desktop'
                ? ['display' => 'horizontal', 'alignment' => 'right']
                : ['display' => 'hamburger', 'alignment' => 'right'];
            $source = is_array($deviceStyles[$device] ?? null) ? $deviceStyles[$device] : [];
            $toggle = $this->normalizeMenuToggle(is_array($source['toggle'] ?? null) ? $source['toggle'] : null, $fallbackToggle);

            $normalized['devices'][$device] = [
                'display' => $this->allowedValue($source['display'] ?? null, self::MENU_DISPLAY_MODES, $fallback['display']),
                'alignment' => $this->allowedValue($source['alignment'] ?? null, self::MENU_ALIGNMENT_TOKENS, $fallback['alignment']),
                'toggle_label' => $this->menuToggleLabel($source['toggle_label'] ?? null),
                'toggle' => $toggle,
            ];
            $fallbackToggle = $toggle;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $appearance
     * @return array<string, mixed>
     */
    public function normalizeMenuAppearance(?array $appearance): array
    {
        $appearance ??= [];
        $normalized = [
            'typography_preset' => $this->allowedValue($appearance['typography_preset'] ?? null, $this->typographyPresetTokens(), 'inherit'),
            'font_family_token' => $this->allowedValue($appearance['font_family_token'] ?? null, $this->fontFamilyTokens(), 'inherit'),
            'font_size_token' => $this->allowedValue($appearance['font_size_token'] ?? null, $this->fontSizeTokens(), 'inherit'),
            'font_weight' => $this->allowedValue($appearance['font_weight'] ?? null, $this->fontWeightTokens(), 'inherit'),
        ];

        foreach (self::MENU_COLOR_FIELDS as $field) {
            $normalized[$field] = $this->normalizeCssColor($appearance[$field] ?? null);
            $normalized[$field.'_token'] = $this->allowedValue($appearance[$field.'_token'] ?? null, $this->colorTokens(), '');
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $toggle
     * @param  array<string, mixed>|null  $fallback
     * @return array<string, mixed>
     */
    public function normalizeMenuToggle(?array $toggle, ?array $fallback = null): array
    {
        $toggle ??= [];
        $fallback ??= [
            'icon' => 'hamburger',
            'shape' => 'pill',
            'size' => 'normal',
        ];

        $normalized = [
            'icon' => $this->allowedValue($toggle['icon'] ?? null, self::MENU_TOGGLE_ICONS, (string) ($fallback['icon'] ?? 'hamburger')),
            'shape' => $this->allowedValue($toggle['shape'] ?? null, self::MENU_TOGGLE_SHAPES, (string) ($fallback['shape'] ?? 'pill')),
            'size' => $this->allowedValue($toggle['size'] ?? null, self::MENU_TOGGLE_SIZES, (string) ($fallback['size'] ?? 'normal')),
        ];

        foreach (self::MENU_TOGGLE_COLOR_FIELDS as $field) {
            $normalized[$field] = array_key_exists($field, $toggle)
                ? $this->normalizeCssColor($toggle[$field])
                : ($fallback[$field] ?? null);
            $normalized[$field.'_token'] = array_key_exists($field.'_token', $toggle)
                ? $this->allowedValue($toggle[$field.'_token'], $this->colorTokens(), '')
                : (string) ($fallback[$field.'_token'] ?? '');
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $style
     * @param  array<string, mixed>|null  $fallback
     * @return array{alignment: string, content_alignment: string, content_vertical_alignment: string, z_index: string, appearance: array<string, mixed>}
     */
    private function normalizeDeviceStyle(array $style, ?array $fallback): array
    {
        $fallback ??= [
            'alignment' => '',
            'content_alignment' => '',
            'content_vertical_alignment' => '',
            'z_index' => 'auto',
            'appearance' => $this->defaultAppearance(),
        ];
        $appearance = is_array($style['appearance'] ?? null) ? $style['appearance'] : [];

        return [
            'alignment' => $this->allowedValue($style['alignment'] ?? null, ['', 'left', 'center', 'right'], (string) $fallback['alignment']),
            'content_alignment' => $this->allowedValue($style['content_alignment'] ?? null, ['', 'left', 'center', 'right'], (string) $fallback['content_alignment']),
            'content_vertical_alignment' => $this->allowedValue($style['content_vertical_alignment'] ?? null, self::CONTENT_VERTICAL_ALIGNMENT_TOKENS, (string) ($fallback['content_vertical_alignment'] ?? '')),
            'z_index' => $this->allowedValue($style['z_index'] ?? null, self::Z_INDEX_TOKENS, (string) $fallback['z_index']),
            'appearance' => $this->normalizeAppearance($appearance, (array) $fallback['appearance']),
        ];
    }

    /**
     * @param  array<string, mixed>  $appearance
     * @param  array<string, mixed>|null  $fallback
     * @return array<string, mixed>
     */
    private function normalizeAppearance(array $appearance, ?array $fallback = null): array
    {
        $fallback ??= $this->defaultAppearance();

        return [
            'background_color' => $this->normalizeHexColor($appearance['background_color'] ?? null) ?? $fallback['background_color'],
            'background_color_token' => $this->allowedValue($appearance['background_color_token'] ?? null, $this->colorTokens(), (string) ($fallback['background_color_token'] ?? '')),
            'foreground_color' => $this->normalizeHexColor($appearance['foreground_color'] ?? null) ?? $fallback['foreground_color'],
            'foreground_color_token' => $this->allowedValue($appearance['foreground_color_token'] ?? null, $this->colorTokens(), (string) ($fallback['foreground_color_token'] ?? '')),
            'typography_preset' => $this->allowedValue($appearance['typography_preset'] ?? null, $this->typographyPresetTokens(), (string) $fallback['typography_preset']),
            'font_family_token' => $this->allowedValue($appearance['font_family_token'] ?? null, $this->fontFamilyTokens(), (string) $fallback['font_family_token']),
            'font_size_token' => $this->allowedValue($appearance['font_size_token'] ?? null, $this->fontSizeTokens(), (string) $fallback['font_size_token']),
            'font_weight' => $this->allowedValue($appearance['font_weight'] ?? null, $this->fontWeightTokens(), (string) $fallback['font_weight']),
            'logo_size' => $this->allowedValue($appearance['logo_size'] ?? null, self::LOGO_SIZE_TOKENS, (string) $fallback['logo_size']),
            'padding' => $this->allowedValue($appearance['padding'] ?? null, ['none', 'sm', 'md', 'lg'], (string) $fallback['padding']),
            'radius' => $this->allowedValue($appearance['radius'] ?? null, ['inherit', 'none', 'sm', 'md', 'lg'], (string) $fallback['radius']),
            'border' => $this->allowedValue($appearance['border'] ?? null, ['none', 'subtle', 'strong', 'primary'], (string) $fallback['border']),
            'shadow' => $this->allowedValue($appearance['shadow'] ?? null, ['none', 'sm', 'md', 'lg'], (string) $fallback['shadow']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultAppearance(): array
    {
        return [
            'background_color' => null,
            'background_color_token' => '',
            'foreground_color' => null,
            'foreground_color_token' => '',
            'typography_preset' => 'inherit',
            'font_family_token' => 'inherit',
            'font_size_token' => 'inherit',
            'font_weight' => 'inherit',
            'logo_size' => 'default',
            'padding' => 'none',
            'radius' => 'inherit',
            'border' => 'none',
            'shadow' => 'none',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $box
     * @return array<string, array<string, array<string, float|string|null>>>
     */
    public function normalizeBoxSpacing(?array $box): array
    {
        $normalized = [];

        foreach (self::DEVICES as $device) {
            $deviceBox = is_array($box[$device] ?? null) ? $box[$device] : [];

            foreach (self::BOX_GROUPS as $group) {
                $groupBox = is_array($deviceBox[$group] ?? null) ? $deviceBox[$group] : [];
                $unit = $this->allowedValue($groupBox['unit'] ?? null, self::BOX_UNITS, 'rem');
                $normalized[$device][$group] = ['unit' => $unit];

                foreach (self::BOX_SIDES as $side) {
                    $value = $this->nullableFloat($groupBox[$side] ?? null);
                    $sideUnit = $this->allowedValue($groupBox[$side.'_unit'] ?? null, self::BOX_UNITS, $unit);

                    $normalized[$device][$group][$side] = $group === 'padding' && $value !== null
                        ? max(0.0, $value)
                        : $value;
                    $normalized[$device][$group][$side.'_unit'] = $sideUnit;
                }
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $background
     * @return array{color: string|null, media_asset_id: int|null, mode: string, position: string, image_opacity: int}
     */
    public function normalizeBackground(?array $background): array
    {
        $background ??= [];
        $mediaAssetId = (int) ($background['media_asset_id'] ?? 0);

        return [
            'color' => $this->normalizeHexColor($background['color'] ?? null),
            'media_asset_id' => $mediaAssetId > 0 ? $mediaAssetId : null,
            'mode' => $this->allowedValue($background['mode'] ?? null, self::BACKGROUND_MODES, 'cover'),
            'position' => $this->allowedValue($background['position'] ?? null, self::BACKGROUND_POSITIONS, 'center center'),
            'image_opacity' => $this->integerInRange($background['image_opacity'] ?? 100, 0, 100),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $box
     * @return array<string, string>
     */
    public function boxSpacingCssVariables(?array $box, string $prefix): array
    {
        $normalized = $this->normalizeBoxSpacing($box);
        $variables = [];

        foreach (self::DEVICES as $device) {
            foreach (self::BOX_GROUPS as $group) {
                foreach (self::BOX_SIDES as $side) {
                    $value = $normalized[$device][$group][$side];
                    $unit = (string) $normalized[$device][$group][$side.'_unit'];

                    if ($value === null) {
                        continue;
                    }

                    $variables["--rw-public-{$prefix}-{$device}-{$group}-{$side}"] = $this->cssLength((float) $value, $unit);
                }
            }
        }

        return $variables;
    }

    public function hasBoxSpacing(?array $box): bool
    {
        return $this->boxSpacingCssVariables($box, 'box') !== [];
    }

    public function normalizeHexColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (! preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return null;
        }

        $hex = strtolower(ltrim($value, '#'));

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.$hex;
    }

    public function normalizeCssColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > 120 || preg_match('/[;{}<>]|url\s*\(|expression\s*\(/i', $value) === 1) {
            return null;
        }

        $hex = $this->normalizeHexColor($value);

        if ($hex !== null) {
            return $hex;
        }

        if (preg_match('/^#(?:[0-9a-fA-F]{4}|[0-9a-fA-F]{8})$/', $value) === 1) {
            $alphaHex = strtolower(ltrim($value, '#'));

            if (strlen($alphaHex) === 4) {
                $alphaHex = $alphaHex[0].$alphaHex[0].$alphaHex[1].$alphaHex[1].$alphaHex[2].$alphaHex[2].$alphaHex[3].$alphaHex[3];
            }

            return '#'.$alphaHex;
        }

        if (in_array($value, ['transparent', 'currentColor'], true)) {
            return $value;
        }

        if (preg_match('/^var\(--rw-public-[a-z0-9_-]+\)$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^(?:rgb|rgba|hsl|hsla)\([0-9a-zA-Z%.,\s\/+-]+\)$/', $value) === 1) {
            return preg_replace('/\s+/', ' ', $value) ?: null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $placement
     * @return array<string, array{x: int, y: int, w: int, h: int}>
     */
    private function normalizePlacementLayoutConfigForData(array $placement, int $fallbackY, bool $applyAlignment): array
    {
        $layoutConfig = is_array($placement['layout_config'] ?? null) ? $placement['layout_config'] : [];
        $styleConfig = is_array($placement['style_config'] ?? null) ? $placement['style_config'] : [];
        $normalized = [];

        foreach (self::DEVICES as $device) {
            $deviceLayout = is_array($layoutConfig[$device] ?? null) ? $layoutConfig[$device] : [];
            $width = $this->integerInRange(
                $deviceLayout['w'] ?? $placement[$device.'_span'] ?? self::COLUMN_COUNT,
                1,
                self::COLUMN_COUNT,
            );
            $x = $this->integerInRange($deviceLayout['x'] ?? 0, 0, self::COLUMN_COUNT - 1);

            if ($x + $width > self::COLUMN_COUNT) {
                $x = max(0, self::COLUMN_COUNT - $width);
            }

            if ($applyAlignment) {
                $x = $this->placementAlignmentStart($width, $styleConfig, $device) ?? $x;
            }

            $normalized[$device] = [
                'x' => $x,
                'y' => max(0, (int) ($deviceLayout['y'] ?? $fallbackY)),
                'w' => $width,
                'h' => max(1, (int) ($deviceLayout['h'] ?? 1)),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $styleConfig
     */
    private function placementAlignmentStart(int $width, array $styleConfig, string $device): ?int
    {
        $deviceStyles = is_array($styleConfig['devices'] ?? null) ? $styleConfig['devices'] : [];
        $deviceStyle = is_array($deviceStyles[$device] ?? null) ? $deviceStyles[$device] : [];

        return match ($deviceStyle['alignment'] ?? null) {
            'left' => 0,
            'center' => (int) floor((self::COLUMN_COUNT - $width) / 2),
            'right' => self::COLUMN_COUNT - $width,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $placement
     */
    private function placementIsVisibleOnDevice(array $placement, string $device): bool
    {
        return ! in_array($placement['visible_'.$device] ?? true, [false, 0, '0'], true);
    }

    /**
     * @param  array{x: int, y: int, w: int, h: int}  $layout
     * @param  array<int, array{x: int, y: int, w: int, h: int}>  $placed
     */
    private function layoutOverlapsAny(array $layout, array $placed): bool
    {
        foreach ($placed as $candidate) {
            if ($this->layoutsOverlap($candidate, $layout)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{x: int, y: int, w: int, h: int}  $left
     * @param  array{x: int, y: int, w: int, h: int}  $right
     */
    private function layoutsOverlap(array $left, array $right): bool
    {
        return ! (
            $right['x'] >= $left['x'] + $left['w'] ||
            $left['x'] >= $right['x'] + $right['w'] ||
            $right['y'] >= $left['y'] + $left['h'] ||
            $left['y'] >= $right['y'] + $right['h']
        );
    }

    /**
     * @param  array<string, mixed>|null  $layout
     * @return array{x: int, y: int, w: int, h: int}
     */
    private function normalizeDeviceLayout(?array $layout, int $defaultWidth): array
    {
        $width = $this->integerInRange($layout['w'] ?? $defaultWidth, 1, self::COLUMN_COUNT);
        $x = $this->integerInRange($layout['x'] ?? 0, 0, self::COLUMN_COUNT - 1);

        if ($x + $width > self::COLUMN_COUNT) {
            $x = max(0, self::COLUMN_COUNT - $width);
        }

        return [
            'x' => $x,
            'y' => max(0, (int) ($layout['y'] ?? 0)),
            'w' => $width,
            'h' => max(1, (int) ($layout['h'] ?? 1)),
        ];
    }

    private function integerInRange(mixed $value, int $min, int $max): int
    {
        return min($max, max($min, (int) $value));
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function allowedValue(mixed $value, array $allowed, string $fallback): string
    {
        $normalized = is_string($value) ? $value : '';

        return in_array($normalized, $allowed, true) ? $normalized : $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function menuToggleLabel(mixed $value): string
    {
        $label = $this->nullableString($value) ?? '';

        return mb_substr($label, 0, 120);
    }

    private function languageIcon(mixed $value): string
    {
        if (! is_string($value)) {
            return 'none';
        }

        $value = trim($value);

        if (in_array($value, self::LANGUAGE_ICONS, true)) {
            return $value;
        }

        if (mb_strlen($value) <= 64 && preg_match('/^mdi-[a-z0-9-]+$/', $value) === 1) {
            return $value;
        }

        return 'none';
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return min(999.0, max(-999.0, (float) $value));
    }

    private function cssLength(float $value, string $unit): string
    {
        if (abs($value) < 0.0001) {
            return '0';
        }

        $formatted = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

        return $formatted.$unit;
    }

    /**
     * @return array<int, string>
     */
    private function fontFamilyTokens(): array
    {
        return $this->configuredTokenKeys('font_family_tokens', self::FONT_FAMILY_TOKENS);
    }

    /**
     * @return array<int, string>
     */
    private function fontSizeTokens(): array
    {
        return $this->configuredTokenKeys('font_size_tokens', self::FONT_SIZE_TOKENS);
    }

    /**
     * @return array<int, string>
     */
    private function fontWeightTokens(): array
    {
        return $this->configuredTokenKeys('font_weight_tokens', self::FONT_WEIGHT_TOKENS);
    }

    /**
     * @return array<int, string>
     */
    private function typographyPresetTokens(): array
    {
        return $this->configuredTokenKeys('typography_preset_tokens', self::TYPOGRAPHY_PRESET_TOKENS);
    }

    /**
     * @return array<int, string>
     */
    private function colorTokens(): array
    {
        return $this->configuredTokenKeys('color_tokens', self::COLOR_TOKENS);
    }

    /**
     * @param  array<int, string>  $fallback
     * @return array<int, string>
     */
    private function configuredTokenKeys(string $key, array $fallback): array
    {
        try {
            $tokens = app()->bound('config') ? config("cms_themes.{$key}") : null;
        } catch (\Throwable) {
            $tokens = null;
        }

        return is_array($tokens) && $tokens !== []
            ? array_values(array_filter(array_keys($tokens), fn (mixed $token): bool => is_string($token) && preg_match('/^[a-z0-9_-]+$/', $token) === 1))
            : $fallback;
    }
}
