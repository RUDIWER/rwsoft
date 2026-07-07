<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsBlockPlacement;
use App\Support\Cms\CmsResponsiveLayoutNormalizer;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PublishCmsPlacementStyleRevisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $placement = CmsBlockPlacement::query()->find((int) $this->route('placement'));

        if (! $placement instanceof CmsBlockPlacement) {
            return false;
        }

        if (! (bool) ($user?->is_platform_admin || $user?->canAccessRoute('admin.cms.layouts.code-blocks.manage'))) {
            return false;
        }

        $placement->loadMissing('section.owner');
        $owner = $placement->section?->owner;
        $locale = is_scalar($owner?->locale ?? null) ? (string) $owner->locale : '';

        return $locale === '' || app(CmsLocalePermission::class)->canEditLocale($user, $locale);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $fontFamilyTokens = array_keys((array) config('cms_themes.font_family_tokens', []));
        $fontSizeTokens = array_keys((array) config('cms_themes.font_size_tokens', []));
        $fontWeightTokens = array_keys((array) config('cms_themes.font_weight_tokens', []));
        $typographyPresetTokens = array_keys((array) config('cms_themes.typography_preset_tokens', []));
        $colorTokens = array_keys((array) config('cms_themes.color_tokens', []));
        $menuCssColorRules = ['nullable', 'string', 'max:120', 'regex:/^(?:#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|transparent|currentColor|var\(--rw-public-[a-z0-9_-]+\)|(?:rgb|rgba|hsl|hsla)\([0-9.%\s,\/+-]+\))$/'];
        $menuAppearanceRules = function (string $prefix) use ($colorTokens, $fontFamilyTokens, $fontSizeTokens, $fontWeightTokens, $typographyPresetTokens, $menuCssColorRules): array {
            $keys = ['typography_preset', 'font_family_token', 'font_size_token', 'font_weight'];

            foreach (CmsResponsiveLayoutNormalizer::MENU_COLOR_FIELDS as $field) {
                $keys[] = $field;
                $keys[] = $field.'_token';
            }

            $rules = [
                $prefix => ['nullable', 'array:'.implode(',', $keys)],
                $prefix.'.typography_preset' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($typographyPresetTokens)],
                $prefix.'.font_family_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($fontFamilyTokens)],
                $prefix.'.font_size_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($fontSizeTokens)],
                $prefix.'.font_weight' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($fontWeightTokens)],
            ];

            foreach (CmsResponsiveLayoutNormalizer::MENU_COLOR_FIELDS as $field) {
                $rules[$prefix.'.'.$field] = $menuCssColorRules;
                $rules[$prefix.'.'.$field.'_token'] = ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($colorTokens)];
            }

            return $rules;
        };
        $menuToggleRules = function (string $prefix) use ($colorTokens, $menuCssColorRules): array {
            $keys = ['icon', 'shape', 'size'];

            foreach (CmsResponsiveLayoutNormalizer::MENU_TOGGLE_COLOR_FIELDS as $field) {
                $keys[] = $field;
                $keys[] = $field.'_token';
            }

            $rules = [
                $prefix => ['nullable', 'array:'.implode(',', $keys)],
                $prefix.'.icon' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_TOGGLE_ICONS)],
                $prefix.'.shape' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_TOGGLE_SHAPES)],
                $prefix.'.size' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_TOGGLE_SIZES)],
            ];

            foreach (CmsResponsiveLayoutNormalizer::MENU_TOGGLE_COLOR_FIELDS as $field) {
                $rules[$prefix.'.'.$field] = $menuCssColorRules;
                $rules[$prefix.'.'.$field.'_token'] = ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($colorTokens)];
            }

            return $rules;
        };

        return [
            'css_source' => ['required', 'string', 'max:100000'],
            'style_config' => ['nullable', 'array'],
            'style_config.devices' => ['nullable', 'array'],
            'style_config.devices.*' => ['nullable', 'array'],
            'style_config.devices.*.alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'style_config.devices.*.content_alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'style_config.devices.*.content_vertical_alignment' => ['nullable', 'string', Rule::in(['top', 'middle', 'bottom'])],
            'style_config.devices.*.z_index' => ['nullable', 'string', Rule::in(['auto', '0', '10', '20', '30', '40', '50'])],
            'style_config.devices.*.appearance' => ['nullable', 'array'],
            'style_config.devices.*.appearance.background_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'style_config.devices.*.appearance.background_color_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($colorTokens)],
            'style_config.devices.*.appearance.foreground_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'style_config.devices.*.appearance.foreground_color_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($colorTokens)],
            'style_config.devices.*.appearance.typography_preset' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($typographyPresetTokens)],
            'style_config.devices.*.appearance.font_family_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($fontFamilyTokens)],
            'style_config.devices.*.appearance.font_size_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($fontSizeTokens)],
            'style_config.devices.*.appearance.font_weight' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($fontWeightTokens)],
            'style_config.devices.*.appearance.logo_size' => ['nullable', 'string', Rule::in(['small', 'default', 'large'])],
            'style_config.devices.*.appearance.padding' => ['nullable', 'string', Rule::in(['none', 'sm', 'md', 'lg'])],
            'style_config.devices.*.appearance.radius' => ['nullable', 'string', Rule::in(['inherit', 'none', 'sm', 'md', 'lg'])],
            'style_config.devices.*.appearance.border' => ['nullable', 'string', Rule::in(['none', 'subtle', 'strong', 'primary'])],
            'style_config.devices.*.appearance.shadow' => ['nullable', 'string', Rule::in(['none', 'sm', 'md', 'lg'])],
            'style_config.appearance_container' => ['nullable', 'array:enabled'],
            'style_config.appearance_container.enabled' => ['nullable', 'boolean'],
            'style_config.box' => ['nullable', 'array'],
            'style_config.box.*' => ['nullable', 'array'],
            'style_config.box.*.*' => ['nullable', 'array'],
            'style_config.box.*.*.unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'style_config.box.*.*.top_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'style_config.box.*.*.right_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'style_config.box.*.*.bottom_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'style_config.box.*.*.left_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'style_config.box.*.*.top' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'style_config.box.*.*.right' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'style_config.box.*.*.bottom' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'style_config.box.*.*.left' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'style_config.menu' => ['nullable', 'array:devices,item_variant,spacing,drawer_side,drawer_top,submenu_behavior,submenu_side,appearance'],
            'style_config.menu.devices' => ['nullable', 'array:desktop,tablet,mobile'],
            'style_config.menu.devices.*' => ['nullable', 'array:display,alignment,toggle_label,toggle'],
            'style_config.menu.devices.*.display' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_DISPLAY_MODES)],
            'style_config.menu.devices.*.alignment' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_ALIGNMENT_TOKENS)],
            'style_config.menu.devices.*.toggle_label' => ['nullable', 'string', 'max:120'],
            ...$menuToggleRules('style_config.menu.devices.*.toggle'),
            'style_config.menu.item_variant' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_ITEM_VARIANTS)],
            'style_config.menu.spacing' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_SPACING_TOKENS)],
            'style_config.menu.drawer_side' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_DRAWER_SIDES)],
            'style_config.menu.drawer_top' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_DRAWER_TOPS)],
            'style_config.menu.submenu_behavior' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_SUBMENU_BEHAVIORS)],
            'style_config.menu.submenu_side' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_SUBMENU_SIDES)],
            ...$menuAppearanceRules('style_config.menu.appearance'),
            'style_config.language' => ['nullable', 'array:devices,item_variant,spacing,appearance,flag_position,flag_shape,flag_size'],
            'style_config.language.devices' => ['nullable', 'array:desktop,tablet,mobile'],
            'style_config.language.devices.*' => ['nullable', 'array:display,alignment,label,icon'],
            'style_config.language.devices.*.display' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::LANGUAGE_DISPLAY_MODES)],
            'style_config.language.devices.*.alignment' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_ALIGNMENT_TOKENS)],
            'style_config.language.devices.*.label' => ['nullable', 'string', 'max:120'],
            'style_config.language.devices.*.icon' => ['nullable', 'string', 'max:64', 'regex:/^(?:none|mdi-[a-z0-9-]+)$/'],
            'style_config.language.item_variant' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_ITEM_VARIANTS)],
            'style_config.language.spacing' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_SPACING_TOKENS)],
            'style_config.language.flag_position' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::LANGUAGE_FLAG_POSITIONS)],
            'style_config.language.flag_shape' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::LANGUAGE_FLAG_SHAPES)],
            'style_config.language.flag_size' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::LANGUAGE_FLAG_SIZES)],
            ...$menuAppearanceRules('style_config.language.appearance'),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (str_contains(mb_strtolower((string) $this->input('css_source')), '</style')) {
                    $validator->errors()->add('css_source', __('cms_admin_ui.validation.layout_css_style_tag_forbidden'));
                }
            },
        ];
    }
}
