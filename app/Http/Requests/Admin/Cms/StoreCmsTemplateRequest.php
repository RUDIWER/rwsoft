<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsTemplate;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\Cms\CmsResponsiveLayoutNormalizer;
use App\Support\Cms\CmsTemplateDataContract;
use App\Support\Cms\CmsTemplateRegistry;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsTemplateRequest extends FormRequest
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
        $templateId = (int) $this->route('id');

        if ($templateId <= 0) {
            return;
        }

        $template = CmsTemplate::query()
            ->whereKey($templateId)
            ->first(['locale', 'template_class', 'template_key']);

        if (! $template instanceof CmsTemplate) {
            return;
        }

        $this->merge([
            'locale' => (string) $template->locale,
            'template_class' => (string) $template->template_class,
            'template_key' => (string) $template->template_key,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $templateRegistry = app(CmsTemplateRegistry::class);
        $blockRegistry = app(CmsBlockRegistry::class);
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
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2}([_-][A-Z]{2})?$/'],
            'layout_id' => [
                'required',
                'integer',
                Rule::exists((new CmsLayout)->getTable(), 'id')
                    ->where('locale', $this->string('locale')->toString())
                    ->where('is_active', true),
            ],
            'template_class' => ['required', 'string', Rule::in($templateRegistry->classKeys())],
            'template_key' => ['required', 'string', Rule::in($templateRegistry->templateKeys())],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'cache_strategy' => ['required', 'string', Rule::in(['inherit', 'none', 'block', 'layout'])],
            'data_contract' => ['nullable', 'array'],
            'data_contract.system_fields' => ['nullable', 'array'],
            'data_contract.system_fields.*.key' => ['required_with:data_contract.system_fields', 'string', 'max:120'],
            'data_contract.system_fields.*.enabled' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
            'settings.html_anchor' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9-]{1,63}$/'],
            'sections' => ['nullable', 'array'],
            'sections.content' => ['nullable', 'array'],
            'sections.content.*.id' => ['nullable', 'integer', Rule::exists('cms_sections', 'id')],
            'sections.content.*.name' => ['required', 'string', 'max:255'],
            'sections.content.*.is_active' => ['nullable', 'boolean'],
            'sections.content.*.visible_mobile' => ['nullable', 'boolean'],
            'sections.content.*.visible_tablet' => ['nullable', 'boolean'],
            'sections.content.*.visible_desktop' => ['nullable', 'boolean'],
            'sections.content.*.settings' => ['nullable', 'array'],
            'sections.content.*.settings.html_anchor' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9-]{1,63}$/'],
            'sections.content.*.settings.layout_type' => ['nullable', 'string', Rule::in(['standard', 'hero', 'two_columns', 'grid'])],
            'sections.content.*.settings.width_mode' => ['nullable', 'string', Rule::in(['content', 'display'])],
            'sections.content.*.settings.spacing' => ['nullable', 'string', Rule::in(['none', 'compact', 'normal', 'spacious'])],
            'sections.content.*.settings.background' => ['nullable', 'array'],
            'sections.content.*.settings.background.color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'sections.content.*.settings.background.media_asset_id' => ['nullable', 'integer', Rule::exists('cms_media_assets', 'id')->whereNull('deleted_at')],
            'sections.content.*.settings.background.mode' => ['nullable', 'string', Rule::in(['cover', 'contain', 'stretch', 'center', 'repeat', 'repeat-x', 'repeat-y'])],
            'sections.content.*.settings.background.position' => ['nullable', 'string', Rule::in(['center center', 'center top', 'center bottom', 'left center', 'right center'])],
            'sections.content.*.settings.background.image_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sections.content.*.settings.box' => ['nullable', 'array'],
            'sections.content.*.settings.box.*' => ['nullable', 'array'],
            'sections.content.*.settings.box.*.*' => ['nullable', 'array'],
            'sections.content.*.settings.box.*.*.unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.content.*.settings.box.*.*.top_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.content.*.settings.box.*.*.right_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.content.*.settings.box.*.*.bottom_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.content.*.settings.box.*.*.left_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.content.*.settings.box.*.*.top' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.content.*.settings.box.*.*.right' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.content.*.settings.box.*.*.bottom' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.content.*.settings.box.*.*.left' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.content.*.placements' => ['nullable', 'array'],
            'sections.content.*.placements.*.id' => ['nullable', 'integer', Rule::exists('cms_block_placements', 'id')],
            'sections.content.*.placements.*.is_active' => ['nullable', 'boolean'],
            'sections.content.*.placements.*.visible_mobile' => ['nullable', 'boolean'],
            'sections.content.*.placements.*.visible_tablet' => ['nullable', 'boolean'],
            'sections.content.*.placements.*.visible_desktop' => ['nullable', 'boolean'],
            'sections.content.*.placements.*.mobile_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.content.*.placements.*.tablet_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.content.*.placements.*.desktop_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.content.*.placements.*.layout_config' => ['nullable', 'array'],
            'sections.content.*.placements.*.layout_config.desktop' => ['nullable', 'array'],
            'sections.content.*.placements.*.layout_config.tablet' => ['nullable', 'array'],
            'sections.content.*.placements.*.layout_config.mobile' => ['nullable', 'array'],
            'sections.content.*.placements.*.layout_config.*.x' => ['nullable', 'integer', 'min:0', 'max:11'],
            'sections.content.*.placements.*.layout_config.*.y' => ['nullable', 'integer', 'min:0'],
            'sections.content.*.placements.*.layout_config.*.w' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.content.*.placements.*.layout_config.*.h' => ['nullable', 'integer', 'min:1'],
            'sections.content.*.placements.*.style_config' => ['nullable', 'array'],
            'sections.content.*.placements.*.style_config.devices' => ['nullable', 'array'],
            'sections.content.*.placements.*.style_config.devices.*' => ['nullable', 'array'],
            'sections.content.*.placements.*.style_config.devices.*.alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'sections.content.*.placements.*.style_config.devices.*.content_alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'sections.content.*.placements.*.style_config.devices.*.content_vertical_alignment' => ['nullable', 'string', Rule::in(['top', 'middle', 'bottom'])],
            'sections.content.*.placements.*.style_config.devices.*.z_index' => ['nullable', 'string', Rule::in(['auto', '0', '10', '20', '30', '40', '50'])],
            'sections.content.*.placements.*.style_config.devices.*.appearance' => ['nullable', 'array'],
            'sections.content.*.placements.*.style_config.devices.*.appearance.background_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'sections.content.*.placements.*.style_config.devices.*.appearance.background_color_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($colorTokens)],
            'sections.content.*.placements.*.style_config.devices.*.appearance.foreground_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'sections.content.*.placements.*.style_config.devices.*.appearance.foreground_color_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($colorTokens)],
            'sections.content.*.placements.*.style_config.devices.*.appearance.typography_preset' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($typographyPresetTokens)],
            'sections.content.*.placements.*.style_config.devices.*.appearance.font_family_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($fontFamilyTokens)],
            'sections.content.*.placements.*.style_config.devices.*.appearance.font_size_token' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($fontSizeTokens)],
            'sections.content.*.placements.*.style_config.devices.*.appearance.font_weight' => ['nullable', 'string', 'regex:/^[a-z0-9_-]+$/', Rule::in($fontWeightTokens)],
            'sections.content.*.placements.*.style_config.devices.*.appearance.logo_size' => ['nullable', 'string', Rule::in(['small', 'default', 'large'])],
            'sections.content.*.placements.*.style_config.devices.*.appearance.padding' => ['nullable', 'string', Rule::in(['none', 'sm', 'md', 'lg'])],
            'sections.content.*.placements.*.style_config.devices.*.appearance.radius' => ['nullable', 'string', Rule::in(['inherit', 'none', 'sm', 'md', 'lg'])],
            'sections.content.*.placements.*.style_config.devices.*.appearance.border' => ['nullable', 'string', Rule::in(['none', 'subtle', 'strong', 'primary'])],
            'sections.content.*.placements.*.style_config.devices.*.appearance.shadow' => ['nullable', 'string', Rule::in(['none', 'sm', 'md', 'lg'])],
            'sections.content.*.placements.*.style_config.appearance_container' => ['nullable', 'array:enabled'],
            'sections.content.*.placements.*.style_config.appearance_container.enabled' => ['nullable', 'boolean'],
            'sections.content.*.placements.*.style_config.box' => ['nullable', 'array'],
            'sections.content.*.placements.*.style_config.box.*' => ['nullable', 'array'],
            'sections.content.*.placements.*.style_config.box.*.*' => ['nullable', 'array'],
            'sections.content.*.placements.*.style_config.box.*.*.unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.content.*.placements.*.style_config.box.*.*.top_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.content.*.placements.*.style_config.box.*.*.right_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.content.*.placements.*.style_config.box.*.*.bottom_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.content.*.placements.*.style_config.box.*.*.left_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'sections.content.*.placements.*.style_config.box.*.*.top' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.content.*.placements.*.style_config.box.*.*.right' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.content.*.placements.*.style_config.box.*.*.bottom' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.content.*.placements.*.style_config.box.*.*.left' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'sections.content.*.placements.*.style_config.menu' => ['nullable', 'array:devices,item_variant,spacing,drawer_side,drawer_top,submenu_behavior,submenu_side,appearance'],
            'sections.content.*.placements.*.style_config.menu.devices' => ['nullable', 'array:desktop,tablet,mobile'],
            'sections.content.*.placements.*.style_config.menu.devices.*' => ['nullable', 'array:display,alignment,toggle_label,toggle'],
            'sections.content.*.placements.*.style_config.menu.devices.*.display' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_DISPLAY_MODES)],
            'sections.content.*.placements.*.style_config.menu.devices.*.alignment' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_ALIGNMENT_TOKENS)],
            'sections.content.*.placements.*.style_config.menu.devices.*.toggle_label' => ['nullable', 'string', 'max:120'],
            ...$menuToggleRules('sections.content.*.placements.*.style_config.menu.devices.*.toggle'),
            'sections.content.*.placements.*.style_config.menu.item_variant' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_ITEM_VARIANTS)],
            'sections.content.*.placements.*.style_config.menu.spacing' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_SPACING_TOKENS)],
            'sections.content.*.placements.*.style_config.menu.drawer_side' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_DRAWER_SIDES)],
            'sections.content.*.placements.*.style_config.menu.drawer_top' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_DRAWER_TOPS)],
            'sections.content.*.placements.*.style_config.menu.submenu_behavior' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_SUBMENU_BEHAVIORS)],
            'sections.content.*.placements.*.style_config.menu.submenu_side' => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::MENU_SUBMENU_SIDES)],
            ...$menuAppearanceRules('sections.content.*.placements.*.style_config.menu.appearance'),
            'sections.content.*.placements.*.style_config.developer' => ['nullable', 'array'],
            'sections.content.*.placements.*.style_config.developer.css_source' => ['nullable', 'string', 'max:100000'],
            'sections.content.*.placements.*.height_mode' => ['nullable', 'string', Rule::in(['auto', 'fixed', 'min'])],
            'sections.content.*.placements.*.height_value' => ['nullable', 'string', 'max:64'],
            'sections.content.*.placements.*.cache_strategy' => ['nullable', 'string', Rule::in(['inherit', 'none', 'block', 'layout'])],
            'sections.content.*.placements.*.settings' => ['nullable', 'array'],
            'sections.content.*.placements.*.slots' => ['nullable', 'array'],
            'sections.content.*.placements.*.slots.*' => ['nullable', 'array'],
            'sections.content.*.placements.*.slots.*.placements' => ['nullable', 'array', 'max:50'],
            'sections.content.*.placements.*.slots.*.placements.*.id' => ['nullable', 'integer', Rule::exists('cms_block_placements', 'id')],
            'sections.content.*.placements.*.slots.*.placements.*.is_active' => ['nullable', 'boolean'],
            'sections.content.*.placements.*.slots.*.placements.*.visible_mobile' => ['nullable', 'boolean'],
            'sections.content.*.placements.*.slots.*.placements.*.visible_tablet' => ['nullable', 'boolean'],
            'sections.content.*.placements.*.slots.*.placements.*.visible_desktop' => ['nullable', 'boolean'],
            'sections.content.*.placements.*.slots.*.placements.*.mobile_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.content.*.placements.*.slots.*.placements.*.tablet_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.content.*.placements.*.slots.*.placements.*.desktop_span' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sections.content.*.placements.*.slots.*.placements.*.layout_config' => ['nullable', 'array'],
            'sections.content.*.placements.*.slots.*.placements.*.style_config' => ['nullable', 'array'],
            'sections.content.*.placements.*.slots.*.placements.*.height_mode' => ['nullable', 'string', Rule::in(['auto', 'fixed', 'min'])],
            'sections.content.*.placements.*.slots.*.placements.*.height_value' => ['nullable', 'string', 'max:64'],
            'sections.content.*.placements.*.slots.*.placements.*.cache_strategy' => ['nullable', 'string', Rule::in(['inherit', 'none', 'block', 'layout'])],
            'sections.content.*.placements.*.slots.*.placements.*.settings' => ['nullable', 'array'],
            'sections.content.*.placements.*.slots.*.placements.*.block' => ['required_with:sections.content.*.placements.*.slots.*.placements', 'array'],
            'sections.content.*.placements.*.slots.*.placements.*.block.cms_placeable_block_id' => ['nullable', 'integer', Rule::exists('cms_placeable_blocks', 'id')],
            'sections.content.*.placements.*.slots.*.placements.*.block.placeable_block_revision_id' => ['nullable', 'integer', Rule::exists('cms_placeable_block_revisions', 'id')],
            'sections.content.*.placements.*.slots.*.placements.*.block.*' => ['nullable'],
            'sections.content.*.placements.*.settings.html_anchor' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9-]{1,63}$/'],
            'sections.content.*.placements.*.settings.content_key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]{0,79}$/'],
            'sections.content.*.placements.*.settings.editor_label' => ['nullable', 'string', 'max:120'],
            'sections.content.*.placements.*.settings.page_editable' => ['nullable', 'boolean'],
            'sections.content.*.placements.*.settings.page_editable_fields' => ['nullable', 'array'],
            'sections.content.*.placements.*.settings.page_editable_fields.*' => ['string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'sections.content.*.placements.*.settings.page_editable_meta' => ['nullable', 'array'],
            'sections.content.*.placements.*.settings.page_editable_meta.*' => ['string', Rule::in(['is_active'])],
            'sections.content.*.placements.*.slots.*.placements.*.settings.content_key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]{0,79}$/'],
            'sections.content.*.placements.*.slots.*.placements.*.settings.editor_label' => ['nullable', 'string', 'max:120'],
            'sections.content.*.placements.*.slots.*.placements.*.settings.page_editable' => ['nullable', 'boolean'],
            'sections.content.*.placements.*.slots.*.placements.*.settings.page_editable_fields' => ['nullable', 'array'],
            'sections.content.*.placements.*.slots.*.placements.*.settings.page_editable_fields.*' => ['string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'sections.content.*.placements.*.slots.*.placements.*.settings.page_editable_meta' => ['nullable', 'array'],
            'sections.content.*.placements.*.slots.*.placements.*.settings.page_editable_meta.*' => ['string', Rule::in(['is_active'])],
            'sections.content.*.placements.*.settings.alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'sections.content.*.placements.*.settings.content_alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            ...$blockRegistry->blockRules('sections.content.*.placements.*.block'),
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
                $templateClass = (string) $this->input('template_class');
                $templateKey = (string) $this->input('template_key');
                $templateId = (int) $this->route('id');

                if (! app(CmsTemplateRegistry::class)->isValidTemplateKey($templateKey, $templateClass)) {
                    $validator->errors()->add('template_key', __('cms_admin_ui.validation.template_type_forbidden'));
                }

                if ((bool) $this->input('is_default')) {
                    $defaultExists = CmsTemplate::query()
                        ->where('template_key', $templateKey)
                        ->where('locale', (string) $this->input('locale'))
                        ->when($templateId > 0, fn ($query) => $query->where('id', '!=', $templateId))
                        ->where('is_default', true)
                        ->exists();

                    if ($defaultExists) {
                        $validator->errors()->add('is_default', __('cms_admin_ui.validation.template_default_exists'));
                    }
                }

                app(CmsTemplateDataContract::class)->validateContract(
                    $validator,
                    is_array($this->input('data_contract')) ? $this->input('data_contract') : [],
                    $templateKey,
                );

                $contract = app(CmsTemplateDataContract::class)->normalize(
                    is_array($this->input('data_contract')) ? $this->input('data_contract') : [],
                    $templateKey,
                );
                $allowedFieldKeys = collect($contract['system_fields'])
                    ->filter(fn (array $field): bool => (bool) $field['enabled'])
                    ->pluck('key')
                    ->merge(collect($contract['template_fields'])->pluck('key')->map(fn (string $key): string => 'template.'.$key))
                    ->values()
                    ->all();

                foreach ((array) $this->input('sections.content', []) as $sectionIndex => $section) {
                    foreach ((array) ($section['placements'] ?? []) as $placementIndex => $placement) {
                        $block = is_array($placement) ? (array) ($placement['block'] ?? []) : [];
                        $url = trim((string) ($block['url'] ?? ''));
                        $fieldKey = (string) ($block['field_key'] ?? '');
                        $placeableBlockId = (int) ($block['cms_placeable_block_id'] ?? 0);
                        $rendererKey = (string) ($this->placeableBlock($placeableBlockId)?->renderer_key ?? '');

                        if (! $this->placeableBlockAllowedInContent($placeableBlockId)) {
                            $validator->errors()->add(
                                "sections.content.{$sectionIndex}.placements.{$placementIndex}.block.cms_placeable_block_id",
                                __('cms_admin_ui.validation.layout_block_zone_forbidden')
                            );
                        }

                        if (! $this->placeableBlockPermissionAllowed($placeableBlockId)) {
                            $validator->errors()->add(
                                "sections.content.{$sectionIndex}.placements.{$placementIndex}.block.cms_placeable_block_id",
                                __('cms_admin_ui.validation.layout_code_block_forbidden')
                            );
                        }

                        if ($url !== '' && ! str_starts_with($url, '/') && ! preg_match('/^https?:\/\//i', $url)) {
                            $validator->errors()->add(
                                "sections.content.{$sectionIndex}.placements.{$placementIndex}.block.url",
                                __('cms_admin_ui.validation.button_url_relative_or_http')
                            );
                        }

                        if ($rendererKey === 'button') {
                            $this->validateButtonBlock($validator, "sections.content.{$sectionIndex}.placements.{$placementIndex}.block", $block);
                        }

                        if ($this->hasDeveloperCss($placement) && ! $this->canManageCodeBlocks()) {
                            $validator->errors()->add(
                                "sections.content.{$sectionIndex}.placements.{$placementIndex}.style_config.developer.css_source",
                                __('cms_admin_ui.validation.layout_developer_css_forbidden')
                            );
                        }

                        if ($rendererKey === 'dynamic_field' && $fieldKey !== '' && ! in_array($fieldKey, $allowedFieldKeys, true)) {
                            $validator->errors()->add(
                                "sections.content.{$sectionIndex}.placements.{$placementIndex}.block.field_key",
                                __('cms_admin_ui.validation.template_field_forbidden')
                            );
                        }
                    }
                }
            },
        ];
    }

    private function canManageCodeBlocks(): bool
    {
        $user = $this->user();

        return (bool) ($user?->is_platform_admin || $user?->canAccessRoute('admin.cms.layouts.code-blocks.manage'));
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

    /**
     * @param  array<string, mixed>  $placement
     */
    private function hasDeveloperCss(array $placement): bool
    {
        return filled($placement['style_config']['developer']['css_source'] ?? null);
    }

    private function placeableBlockAllowedInContent(int $placeableBlockId): bool
    {
        $placeableBlock = $this->placeableBlock($placeableBlockId);

        return $placeableBlock instanceof CmsPlaceableBlock
            && $placeableBlock->status === 'published'
            && in_array('content', $placeableBlock->allowed_zones ?? [], true);
    }

    private function placeableBlockPermissionAllowed(int $placeableBlockId): bool
    {
        $permission = $this->placeableBlock($placeableBlockId)?->requires_permission;

        return blank($permission) || $this->user()?->canAccessRoute((string) $permission) || (bool) $this->user()?->is_platform_admin;
    }

    private function placeableBlock(int $placeableBlockId): ?CmsPlaceableBlock
    {
        if ($placeableBlockId <= 0) {
            return null;
        }

        return CmsPlaceableBlock::query()->find($placeableBlockId);
    }

    private function authorizationLocale(): string
    {
        $templateId = (int) $this->route('id');

        if ($templateId <= 0) {
            return (string) $this->input('locale');
        }

        return (string) (CmsTemplate::query()
            ->whereKey($templateId)
            ->value('locale') ?? $this->input('locale'));
    }
}
