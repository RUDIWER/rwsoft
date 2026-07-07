<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsPlaceableBlock;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\Cms\CmsResponsiveLayoutNormalizer;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsLayoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale($this->user(), $this->authorizationLocale());
    }

    protected function prepareForValidation(): void
    {
        $layoutId = (int) $this->route('id');

        if ($layoutId <= 0) {
            return;
        }

        $locale = CmsLayout::query()
            ->whereKey($layoutId)
            ->value('locale');

        if (filled($locale)) {
            $this->merge(['locale' => (string) $locale]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $blockRegistry = app(CmsBlockRegistry::class);
        $fontFamilyTokens = array_keys((array) config('cms_themes.font_family_tokens', []));
        $fontSizeTokens = array_keys((array) config('cms_themes.font_size_tokens', []));
        $fontWeightTokens = array_keys((array) config('cms_themes.font_weight_tokens', []));
        $typographyPresetTokens = array_keys((array) config('cms_themes.typography_preset_tokens', []));
        $colorTokens = array_keys((array) config('cms_themes.color_tokens', []));
        $menuCssColorRules = ['nullable', 'string', 'max:120', 'regex:/^(?:#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|transparent|currentColor|var\(--rw-public-[a-z0-9_-]+\)|(?:rgb|rgba|hsl|hsla)\([0-9.%\s,\/+-]+\))$/'];
        $formColorRules = $menuCssColorRules;
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
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2}([_-][A-Z]{2})?$/'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'cache_strategy' => ['required', 'string', Rule::in(['none', 'block', 'layout'])],
            'settings' => ['nullable', 'array'],
            'settings.html_anchor' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9-]{1,63}$/'],
            'settings.scroll_mode' => ['required', 'string', Rule::in(['browser', 'internal'])],
            'settings.background' => ['nullable', 'array'],
            'settings.background.color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'settings.background.media_asset_id' => ['nullable', 'integer', Rule::exists('cms_media_assets', 'id')->whereNull('deleted_at')],
            'settings.background.mode' => ['nullable', 'string', Rule::in(['cover', 'contain', 'stretch', 'center', 'repeat', 'repeat-x', 'repeat-y'])],
            'settings.background.position' => ['nullable', 'string', Rule::in(['center center', 'center top', 'center bottom', 'left center', 'right center'])],
            'settings.background.image_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sections' => ['nullable', 'array'],
            'sections.head' => ['nullable', 'array'],
            'sections.header' => ['nullable', 'array'],
            'sections.footer' => ['nullable', 'array'],
            'sections.body_end' => ['nullable', 'array'],
            'sections.*.*.id' => ['nullable', 'integer', Rule::exists('cms_sections', 'id')],
            'sections.*.*.name' => ['required', 'string', 'max:255'],
            'sections.*.*.is_active' => ['nullable', 'boolean'],
            'sections.*.*.visible_mobile' => ['nullable', 'boolean'],
            'sections.*.*.visible_tablet' => ['nullable', 'boolean'],
            'sections.*.*.visible_desktop' => ['nullable', 'boolean'],
            'sections.*.*.settings' => ['nullable', 'array'],
            'sections.*.*.settings.html_anchor' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9-]{1,63}$/'],
            'sections.*.*.settings.layout_type' => ['nullable', 'string', Rule::in(['standard', 'hero', 'two_columns', 'grid'])],
            'sections.*.*.settings.width_mode' => ['nullable', 'string', Rule::in(['content', 'display'])],
            'sections.*.*.settings.spacing' => ['nullable', 'string', Rule::in(['none', 'compact', 'normal', 'spacious'])],
            'sections.*.*.settings.scroll_behavior' => ['nullable', 'string', Rule::in(['normal', 'sticky', 'auto_hide'])],
            'sections.*.*.settings.background' => ['nullable', 'array'],
            'sections.*.*.settings.background.color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'sections.*.*.settings.background.media_asset_id' => ['nullable', 'integer', Rule::exists('cms_media_assets', 'id')->whereNull('deleted_at')],
            'sections.*.*.settings.background.mode' => ['nullable', 'string', Rule::in(['cover', 'contain', 'stretch', 'center', 'repeat', 'repeat-x', 'repeat-y'])],
            'sections.*.*.settings.background.position' => ['nullable', 'string', Rule::in(['center center', 'center top', 'center bottom', 'left center', 'right center'])],
            'sections.*.*.settings.background.image_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sections.*.*.settings.box' => ['nullable', 'array'],
            'sections.*.*.settings.box.*' => ['nullable', 'array'],
            'sections.*.*.settings.box.*.*' => ['nullable', 'array'],
            'sections.*.*.settings.box.*.*.unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.*.*.settings.box.*.*.top_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.*.*.settings.box.*.*.right_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.*.*.settings.box.*.*.bottom_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.*.*.settings.box.*.*.left_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.*.*.settings.box.*.*.top' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.*.*.settings.box.*.*.right' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.*.*.settings.box.*.*.bottom' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.*.*.settings.box.*.*.left' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.*.*.placements' => ['nullable', 'array'],
            'sections.*.*.placements.*.id' => ['nullable', 'integer', Rule::exists('cms_block_placements', 'id')],
            'sections.*.*.placements.*.is_active' => ['nullable', 'boolean'],
            'sections.*.*.placements.*.visible_mobile' => ['nullable', 'boolean'],
            'sections.*.*.placements.*.visible_tablet' => ['nullable', 'boolean'],
            'sections.*.*.placements.*.visible_desktop' => ['nullable', 'boolean'],
            'sections.*.*.placements.*.mobile_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.*.*.placements.*.tablet_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.*.*.placements.*.desktop_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.*.*.placements.*.layout_config' => ['nullable', 'array'],
            'sections.*.*.placements.*.layout_config.desktop' => ['nullable', 'array'],
            'sections.*.*.placements.*.layout_config.tablet' => ['nullable', 'array'],
            'sections.*.*.placements.*.layout_config.mobile' => ['nullable', 'array'],
            'sections.*.*.placements.*.layout_config.*.x' => ['nullable', 'integer', 'min:0', 'max:11'],
            'sections.*.*.placements.*.layout_config.*.y' => ['nullable', 'integer', 'min:0'],
            'sections.*.*.placements.*.layout_config.*.w' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.*.*.placements.*.layout_config.*.h' => ['nullable', 'integer', 'min:1'],
            'sections.*.*.placements.*.style_config' => ['nullable', 'array'],
            'sections.*.*.placements.*.style_config.devices' => ['nullable', 'array'],
            'sections.*.*.placements.*.style_config.devices.*' => ['nullable', 'array'],
            'sections.*.*.placements.*.style_config.devices.*.alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'sections.*.*.placements.*.style_config.devices.*.content_alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'sections.*.*.placements.*.style_config.devices.*.content_vertical_alignment' => ['nullable', 'string', Rule::in(['top', 'middle', 'bottom'])],
            'sections.*.*.placements.*.style_config.devices.*.z_index' => ['nullable', 'string', Rule::in(['auto', '0', '10', '20', '30', '40', '50'])],
            'sections.*.*.placements.*.style_config.devices.*.appearance' => ['nullable', 'array'],
            'sections.*.*.placements.*.style_config.devices.*.appearance.background_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'sections.*.*.placements.*.style_config.devices.*.appearance.background_color_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($colorTokens)],
            'sections.*.*.placements.*.style_config.devices.*.appearance.foreground_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'sections.*.*.placements.*.style_config.devices.*.appearance.foreground_color_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($colorTokens)],
            'sections.*.*.placements.*.style_config.devices.*.appearance.typography_preset' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($typographyPresetTokens)],
            'sections.*.*.placements.*.style_config.devices.*.appearance.font_family_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($fontFamilyTokens)],
            'sections.*.*.placements.*.style_config.devices.*.appearance.font_size_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($fontSizeTokens)],
            'sections.*.*.placements.*.style_config.devices.*.appearance.font_weight' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($fontWeightTokens)],
            'sections.*.*.placements.*.style_config.devices.*.appearance.logo_size' => ['nullable', 'string', Rule::in(['small', 'default', 'large'])],
            'sections.*.*.placements.*.style_config.devices.*.appearance.padding' => ['nullable', 'string', Rule::in(['none', 'sm', 'md', 'lg'])],
            'sections.*.*.placements.*.style_config.devices.*.appearance.radius' => ['nullable', 'string', Rule::in(['inherit', 'none', 'sm', 'md', 'lg'])],
            'sections.*.*.placements.*.style_config.devices.*.appearance.border' => ['nullable', 'string', Rule::in(['none', 'subtle', 'strong', 'primary'])],
            'sections.*.*.placements.*.style_config.devices.*.appearance.shadow' => ['nullable', 'string', Rule::in(['none', 'sm', 'md', 'lg'])],
            'sections.*.*.placements.*.style_config.appearance_container' => ['nullable', 'array:enabled'],
            'sections.*.*.placements.*.style_config.appearance_container.enabled' => ['nullable', 'boolean'],
            'sections.*.*.placements.*.style_config.box' => ['nullable', 'array'],
            'sections.*.*.placements.*.style_config.box.*' => ['nullable', 'array'],
            'sections.*.*.placements.*.style_config.box.*.*' => ['nullable', 'array'],
            'sections.*.*.placements.*.style_config.box.*.*.unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.*.*.placements.*.style_config.box.*.*.top_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.*.*.placements.*.style_config.box.*.*.right_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.*.*.placements.*.style_config.box.*.*.bottom_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.*.*.placements.*.style_config.box.*.*.left_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.*.*.placements.*.style_config.box.*.*.top' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.*.*.placements.*.style_config.box.*.*.right' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.*.*.placements.*.style_config.box.*.*.bottom' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.*.*.placements.*.style_config.box.*.*.left' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.*.*.placements.*.style_config.menu' => ['nullable', 'array:devices,item_variant,spacing,drawer_side,drawer_top,submenu_behavior,submenu_side,appearance'],
            'sections.*.*.placements.*.style_config.menu.devices' => ['nullable', 'array:desktop,tablet,mobile'],
            'sections.*.*.placements.*.style_config.menu.devices.*' => ['nullable', 'array:display,alignment,toggle_label,toggle'],
            'sections.*.*.placements.*.style_config.menu.devices.*.display' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_DISPLAY_MODES)],
            'sections.*.*.placements.*.style_config.menu.devices.*.alignment' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_ALIGNMENT_TOKENS)],
            'sections.*.*.placements.*.style_config.menu.devices.*.toggle_label' => ['nullable', 'string', 'max:120'],
            ...$menuToggleRules('sections.*.*.placements.*.style_config.menu.devices.*.toggle'),
            'sections.*.*.placements.*.style_config.menu.item_variant' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_ITEM_VARIANTS)],
            'sections.*.*.placements.*.style_config.menu.spacing' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_SPACING_TOKENS)],
            'sections.*.*.placements.*.style_config.menu.drawer_side' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_DRAWER_SIDES)],
            'sections.*.*.placements.*.style_config.menu.drawer_top' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_DRAWER_TOPS)],
            'sections.*.*.placements.*.style_config.menu.submenu_behavior' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_SUBMENU_BEHAVIORS)],
            'sections.*.*.placements.*.style_config.menu.submenu_side' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_SUBMENU_SIDES)],
            ...$menuAppearanceRules('sections.*.*.placements.*.style_config.menu.appearance'),
            'sections.*.*.placements.*.style_config.language' => ['nullable', 'array:devices,item_variant,spacing,appearance,flag_position,flag_shape,flag_size'],
            'sections.*.*.placements.*.style_config.language.devices' => ['nullable', 'array:desktop,tablet,mobile'],
            'sections.*.*.placements.*.style_config.language.devices.*' => ['nullable', 'array:display,alignment,label,icon'],
            'sections.*.*.placements.*.style_config.language.devices.*.display' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::LANGUAGE_DISPLAY_MODES)],
            'sections.*.*.placements.*.style_config.language.devices.*.alignment' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_ALIGNMENT_TOKENS)],
            'sections.*.*.placements.*.style_config.language.devices.*.label' => ['nullable', 'string', 'max:120'],
            'sections.*.*.placements.*.style_config.language.devices.*.icon' => ['nullable', 'string', 'max:64', 'regex:/^(?:none|mdi-[a-z0-9-]+)$/'],
            'sections.*.*.placements.*.style_config.language.item_variant' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_ITEM_VARIANTS)],
            'sections.*.*.placements.*.style_config.language.spacing' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_SPACING_TOKENS)],
            'sections.*.*.placements.*.style_config.language.flag_position' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::LANGUAGE_FLAG_POSITIONS)],
            'sections.*.*.placements.*.style_config.language.flag_shape' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::LANGUAGE_FLAG_SHAPES)],
            'sections.*.*.placements.*.style_config.language.flag_size' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::LANGUAGE_FLAG_SIZES)],
            ...$menuAppearanceRules('sections.*.*.placements.*.style_config.language.appearance'),
            'sections.*.*.placements.*.style_config.form' => ['nullable', 'array:field_spacing,label_weight,input_radius,input_border,input_background_color,input_background_color_token,input_text_color,input_text_color_token,submit_alignment,submit_variant'],
            'sections.*.*.placements.*.style_config.form.field_spacing' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::FORM_FIELD_SPACING_TOKENS)],
            'sections.*.*.placements.*.style_config.form.label_weight' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($fontWeightTokens)],
            'sections.*.*.placements.*.style_config.form.input_radius' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::FORM_INPUT_RADIUS_TOKENS)],
            'sections.*.*.placements.*.style_config.form.input_border' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::FORM_INPUT_BORDER_TOKENS)],
            'sections.*.*.placements.*.style_config.form.input_background_color' => $formColorRules,
            'sections.*.*.placements.*.style_config.form.input_background_color_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($colorTokens)],
            'sections.*.*.placements.*.style_config.form.input_text_color' => $formColorRules,
            'sections.*.*.placements.*.style_config.form.input_text_color_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($colorTokens)],
            'sections.*.*.placements.*.style_config.form.submit_alignment' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::FORM_SUBMIT_ALIGNMENT_TOKENS)],
            'sections.*.*.placements.*.style_config.form.submit_variant' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::FORM_SUBMIT_VARIANT_TOKENS)],
            'sections.*.*.placements.*.style_config.developer' => ['nullable', 'array'],
            'sections.*.*.placements.*.style_config.developer.css_source' => ['nullable', 'string', 'max:100000'],
            'sections.*.*.placements.*.height_mode' => ['nullable', 'string', Rule::in(['auto', 'fixed', 'min'])],
            'sections.*.*.placements.*.height_value' => ['nullable', 'string', 'max:64'],
            'sections.*.*.placements.*.cache_strategy' => ['nullable', 'string', Rule::in(['inherit', 'none', 'block', 'layout'])],
            'sections.*.*.placements.*.settings' => ['nullable', 'array'],
            'sections.*.*.placements.*.slots' => ['nullable', 'array'],
            'sections.*.*.placements.*.slots.*' => ['nullable', 'array'],
            'sections.*.*.placements.*.slots.*.placements' => ['nullable', 'array', 'max:50'],
            'sections.*.*.placements.*.slots.*.placements.*.id' => ['nullable', 'integer', Rule::exists('cms_block_placements', 'id')],
            'sections.*.*.placements.*.slots.*.placements.*.is_active' => ['nullable', 'boolean'],
            'sections.*.*.placements.*.slots.*.placements.*.visible_mobile' => ['nullable', 'boolean'],
            'sections.*.*.placements.*.slots.*.placements.*.visible_tablet' => ['nullable', 'boolean'],
            'sections.*.*.placements.*.slots.*.placements.*.visible_desktop' => ['nullable', 'boolean'],
            'sections.*.*.placements.*.slots.*.placements.*.mobile_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.*.*.placements.*.slots.*.placements.*.tablet_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.*.*.placements.*.slots.*.placements.*.desktop_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.*.*.placements.*.slots.*.placements.*.layout_config' => ['nullable', 'array'],
            'sections.*.*.placements.*.slots.*.placements.*.style_config' => ['nullable', 'array'],
            'sections.*.*.placements.*.slots.*.placements.*.height_mode' => ['nullable', 'string', Rule::in(['auto', 'fixed', 'min'])],
            'sections.*.*.placements.*.slots.*.placements.*.height_value' => ['nullable', 'string', 'max:64'],
            'sections.*.*.placements.*.slots.*.placements.*.cache_strategy' => ['nullable', 'string', Rule::in(['inherit', 'none', 'block', 'layout'])],
            'sections.*.*.placements.*.slots.*.placements.*.settings' => ['nullable', 'array'],
            'sections.*.*.placements.*.slots.*.placements.*.block' => ['required_with:sections.*.*.placements.*.slots.*.placements', 'array'],
            'sections.*.*.placements.*.slots.*.placements.*.block.cms_placeable_block_id' => ['nullable', 'integer', Rule::exists('cms_placeable_blocks', 'id')],
            'sections.*.*.placements.*.slots.*.placements.*.block.placeable_block_revision_id' => ['nullable', 'integer', Rule::exists('cms_placeable_block_revisions', 'id')],
            'sections.*.*.placements.*.slots.*.placements.*.block.*' => ['nullable'],
            'sections.*.*.placements.*.settings.html_anchor' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9-]{1,63}$/'],
            'sections.*.*.placements.*.settings.content_key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]{0,79}$/'],
            'sections.*.*.placements.*.settings.editor_label' => ['nullable', 'string', 'max:120'],
            'sections.*.*.placements.*.settings.page_editable' => ['nullable', 'boolean'],
            'sections.*.*.placements.*.settings.page_editable_fields' => ['nullable', 'array'],
            'sections.*.*.placements.*.settings.page_editable_fields.*' => ['string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'sections.*.*.placements.*.settings.page_editable_meta' => ['nullable', 'array'],
            'sections.*.*.placements.*.settings.page_editable_meta.*' => ['string', Rule::in(['is_active'])],
            'sections.*.*.placements.*.slots.*.placements.*.settings.content_key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]{0,79}$/'],
            'sections.*.*.placements.*.slots.*.placements.*.settings.editor_label' => ['nullable', 'string', 'max:120'],
            'sections.*.*.placements.*.slots.*.placements.*.settings.page_editable' => ['nullable', 'boolean'],
            'sections.*.*.placements.*.slots.*.placements.*.settings.page_editable_fields' => ['nullable', 'array'],
            'sections.*.*.placements.*.slots.*.placements.*.settings.page_editable_fields.*' => ['string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'sections.*.*.placements.*.slots.*.placements.*.settings.page_editable_meta' => ['nullable', 'array'],
            'sections.*.*.placements.*.slots.*.placements.*.settings.page_editable_meta.*' => ['string', Rule::in(['is_active'])],
            'sections.*.*.placements.*.settings.alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'sections.*.*.placements.*.settings.content_alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            ...$blockRegistry->blockRules('sections.*.*.placements.*.block'),
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateUniqueTranslationLocale($validator);
                $this->validateHeadStack($validator);

                foreach ((array) $this->input('sections', []) as $zone => $sections) {
                    if (! in_array($zone, $this->layoutZones(), true)) {
                        $validator->errors()->add("sections.{$zone}", __('cms_admin_ui.validation.layout_zone_forbidden'));

                        continue;
                    }

                    foreach ((array) $sections as $sectionIndex => $section) {
                        if ((string) $zone === 'header' && ! $this->headerSectionLayoutAllowed((array) ($section['settings'] ?? []))) {
                            $validator->errors()->add(
                                "sections.{$zone}.{$sectionIndex}.settings.layout_type",
                                __('cms_admin_ui.validation.layout_header_section_layout_forbidden')
                            );
                        }

                        foreach ((array) ($section['placements'] ?? []) as $placementIndex => $placement) {
                            $block = is_array($placement) ? (array) ($placement['block'] ?? []) : [];
                            $url = trim((string) ($block['url'] ?? ''));
                            $placeableBlockId = (int) ($block['cms_placeable_block_id'] ?? 0);
                            $rendererKey = (string) ($this->placeableBlock($placeableBlockId)?->renderer_key ?? '');

                            if (! $this->placeableBlockAllowedInZone($placeableBlockId, (string) $zone)) {
                                $validator->errors()->add(
                                    "sections.{$zone}.{$sectionIndex}.placements.{$placementIndex}.block.cms_placeable_block_id",
                                    __('cms_admin_ui.validation.layout_block_zone_forbidden')
                                );
                            }

                            if (! $this->placeableBlockPermissionAllowed($placeableBlockId)) {
                                $validator->errors()->add(
                                    "sections.{$zone}.{$sectionIndex}.placements.{$placementIndex}.block.cms_placeable_block_id",
                                    __('cms_admin_ui.validation.layout_code_block_forbidden')
                                );
                            }

                            if (! $this->siteMenuSelectionAllowed($placeableBlockId, (string) $zone, (int) ($block['cms_menu_id'] ?? 0))) {
                                $validator->errors()->add(
                                    "sections.{$zone}.{$sectionIndex}.placements.{$placementIndex}.block.cms_menu_id",
                                    __('cms_admin_ui.validation.site_menu_not_available_for_place')
                                );
                            }

                            if ($this->hasDeveloperCss($placement) && ! $this->canManageCodeBlocks()) {
                                $validator->errors()->add(
                                    "sections.{$zone}.{$sectionIndex}.placements.{$placementIndex}.style_config.developer.css_source",
                                    __('cms_admin_ui.validation.layout_developer_css_forbidden')
                                );
                            }

                            if ($url !== '' && ! str_starts_with($url, '/') && ! preg_match('/^https?:\/\//i', $url)) {
                                $validator->errors()->add(
                                    "sections.{$zone}.{$sectionIndex}.placements.{$placementIndex}.block.url",
                                    __('cms_admin_ui.validation.button_url_relative_or_http')
                                );
                            }

                            if ($rendererKey === 'button') {
                                $this->validateButtonBlock($validator, "sections.{$zone}.{$sectionIndex}.placements.{$placementIndex}.block", $block);
                            }
                        }
                    }
                }
            },
        ];
    }

    private function validateUniqueTranslationLocale(Validator $validator): void
    {
        $layoutId = (int) $this->route('id');

        if ($layoutId <= 0) {
            return;
        }

        $layout = CmsLayout::query()->find($layoutId, ['id', 'translation_key']);
        $translationKey = (string) ($layout?->translation_key ?? '');

        if ($translationKey === '') {
            return;
        }

        $exists = CmsLayout::query()
            ->where('translation_key', $translationKey)
            ->where('locale', (string) $this->input('locale'))
            ->where('id', '!=', $layoutId)
            ->exists();

        if ($exists) {
            $validator->errors()->add('locale', __('cms_admin_ui.validation.translation_target_exists'));
        }
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function validateButtonBlock(Validator $validator, string $attribute, array $block): void
    {
        if (trim((string) ($block['label'] ?? '')) === '') {
            $validator->errors()->add($attribute.'.label', __('cms_admin_ui.validation.button_label_required'));
        }

        if (trim((string) ($block['url'] ?? '')) === '') {
            $validator->errors()->add($attribute.'.url', __('cms_admin_ui.validation.button_url_required'));
        }
    }

    private function placeableBlockAllowedInZone(int $placeableBlockId, string $zone): bool
    {
        $placeableBlock = $this->placeableBlock($placeableBlockId);

        return $placeableBlock instanceof CmsPlaceableBlock
            && $placeableBlock->status === 'published'
            && in_array($zone, $placeableBlock->allowed_zones ?? [], true);
    }

    private function placeableBlockPermissionAllowed(int $placeableBlockId): bool
    {
        $permission = $this->placeableBlock($placeableBlockId)?->requires_permission;

        return blank($permission) || $this->user()?->canAccessRoute((string) $permission) || (bool) $this->user()?->is_platform_admin;
    }

    private function siteMenuSelectionAllowed(int $placeableBlockId, string $zone, int $menuId): bool
    {
        $placeableBlock = $this->placeableBlock($placeableBlockId);

        if ($placeableBlock?->renderer_key !== 'site_menu') {
            return true;
        }

        if (! in_array($zone, ['header', 'footer'], true) || $menuId <= 0) {
            return false;
        }

        $menu = CmsMenu::query()->where('is_active', true)->find($menuId);

        return $menu instanceof CmsMenu && $menu->availableForPlacement($zone);
    }

    private function placeableBlock(int $placeableBlockId): ?CmsPlaceableBlock
    {
        if ($placeableBlockId <= 0) {
            return null;
        }

        return CmsPlaceableBlock::query()->find($placeableBlockId);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function headerSectionLayoutAllowed(array $settings): bool
    {
        return in_array($settings['layout_type'] ?? 'standard', ['standard', 'grid'], true);
    }

    /**
     * @return array<int, string>
     */
    private function layoutZones(): array
    {
        return app(CmsBlockRegistry::class)->layoutZones();
    }

    private function canManageCodeBlocks(): bool
    {
        $user = $this->user();

        return (bool) ($user?->is_platform_admin || $user?->canAccessRoute('admin.cms.layouts.code-blocks.manage'));
    }

    /**
     * @param  array<string, mixed>  $placement
     */
    private function hasDeveloperCss(array $placement): bool
    {
        return filled($placement['style_config']['developer']['css_source'] ?? null);
    }

    private function validateHeadStack(Validator $validator): void
    {
        $headBlockTypes = collect((array) $this->input('sections.head', []))
            ->flatMap(fn (mixed $section): array => is_array($section) ? (array) ($section['placements'] ?? []) : [])
            ->map(function (mixed $placement): string {
                $placeableBlockId = is_array($placement) ? (int) ($placement['block']['cms_placeable_block_id'] ?? 0) : 0;

                return (string) ($this->placeableBlock($placeableBlockId)?->renderer_key ?? '');
            })
            ->filter()
            ->values();
        $lockedHeadBlockTypes = collect($this->lockedHeadBlockTypes());

        if ($headBlockTypes->contains('site_head')) {
            if ($headBlockTypes->intersect($lockedHeadBlockTypes)->isNotEmpty()) {
                $validator->errors()->add('sections.head', __('cms_admin_ui.validation.layout_head_stack_legacy_mixed'));
            }

            return;
        }

        if ($headBlockTypes->intersect($lockedHeadBlockTypes)->isEmpty()) {
            return;
        }

        foreach ($this->lockedHeadBlockTypes() as $type) {
            if ($headBlockTypes->filter(fn (string $headBlockType): bool => $headBlockType === $type)->count() !== 1) {
                $validator->errors()->add('sections.head', __('cms_admin_ui.validation.layout_head_stack_required'));

                return;
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function lockedHeadBlockTypes(): array
    {
        return [
            'site_head_meta',
            'site_head_favicons',
            'site_head_system_assets',
            'site_head_theme',
        ];
    }

    private function authorizationLocale(): string
    {
        $layoutId = (int) $this->route('id');

        if ($layoutId <= 0) {
            return (string) $this->input('locale');
        }

        return (string) (CmsLayout::query()
            ->whereKey($layoutId)
            ->value('locale') ?? $this->input('locale'));
    }
}
