<?php

namespace App\Http\Requests\Admin\Cms;

use App\Actions\Admin\Cms\Seo\ValidateCmsSeoRulesAction;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsTemplate;
use App\Rules\CmsCanonicalUrl;
use App\Support\Cms\CmsTemplateBlockDataContractBuilder;
use App\Support\PublicSite\CmsJsonLdTemplateValidator;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsPageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale($this->user(), (string) $this->input('locale'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $pageId = (int) $this->route('id');
        $locales = app(CmsLanguageSettings::class)->activeLocales();

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('cms_pages', 'id'),
                Rule::notIn([$pageId]),
            ],
            'detail_template_id' => [
                'required',
                'integer',
                Rule::exists((new CmsTemplate)->getTable(), 'id')
                    ->where('locale', $this->string('locale')->toString())
                    ->where('is_active', true)
                    ->where(function ($query): void {
                        $query
                            ->where(function ($query): void {
                                $query
                                    ->where('template_class', 'page')
                                    ->where('template_key', 'page.detail');
                            })
                            ->orWhere(function ($query): void {
                                $query
                                    ->where('template_class', 'system')
                                    ->where('template_key', 'like', 'system.account.%');
                            });
                    }),
            ],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('cms_pages', 'slug')
                    ->where(fn ($query) => $query->where('locale', $this->string('locale')->toString()))
                    ->ignore($pageId > 0 ? $pageId : null),
            ],
            'locale' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2}([_-][A-Z]{2})?$/', Rule::in($locales)],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'template' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:5000'],
            ...$this->templateDataRules(),
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'canonical_url' => ['nullable', 'string', 'max:255', new CmsCanonicalUrl((string) $this->input('locale'))],
            'og_image_path' => ['nullable', 'string', 'max:255'],
            'noindex' => ['nullable', 'boolean'],
            'is_home' => ['nullable', 'boolean'],
            'is_searchable' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'published_at' => ['nullable', 'date'],
            'pdf_download_enabled' => ['nullable', 'boolean'],
            'scroll_mode' => ['nullable', 'string', Rule::in(['inherit', 'browser', 'internal'])],
            'structured_data_schema_type' => ['nullable', 'string', Rule::in(['auto', 'WebPage', 'AboutPage', 'ContactPage', 'FAQPage', 'Service', 'None'])],
            'structured_data_extra' => ['nullable', 'string', 'max:20000'],
            'page_style' => ['nullable', 'array:foreground_color,width_mode,content_gap,css_class,html_anchor,background,box'],
            'page_style.foreground_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'page_style.width_mode' => ['nullable', 'string', Rule::in(['content', 'display'])],
            'page_style.content_gap' => ['nullable', 'string', Rule::in(['none', 'compact', 'normal', 'spacious'])],
            'page_style.css_class' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z_][A-Za-z0-9_-]*(?:\s+[A-Za-z_][A-Za-z0-9_-]*)*$/'],
            'page_style.html_anchor' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9-]{1,63}$/'],
            'page_style.background' => ['nullable', 'array:color,media_asset_id,mode,position,image_opacity'],
            'page_style.background.color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'page_style.background.media_asset_id' => ['nullable', 'integer', Rule::exists('cms_media_assets', 'id')->whereNull('deleted_at')],
            'page_style.background.mode' => ['nullable', 'string', Rule::in(['cover', 'contain', 'stretch', 'center', 'repeat', 'repeat-x', 'repeat-y'])],
            'page_style.background.position' => ['nullable', 'string', Rule::in(['center center', 'center top', 'center bottom', 'left center', 'right center'])],
            'page_style.background.image_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'page_style.box' => ['nullable', 'array'],
            'page_style.box.*' => ['nullable', 'array'],
            'page_style.box.*.*' => ['nullable', 'array'],
            'page_style.box.*.*.unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'page_style.box.*.*.top_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'page_style.box.*.*.right_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'page_style.box.*.*.bottom_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'page_style.box.*.*.left_unit' => ['nullable', 'string', Rule::in(['px', 'rem', 'em', '%', 'vw', 'vh'])],
            'page_style.box.*.*.top' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'page_style.box.*.*.right' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'page_style.box.*.*.bottom' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'page_style.box.*.*.left' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'developer' => ['nullable', 'array:css_source,head_code,body_end_code'],
            'developer.css_source' => ['nullable', 'string', 'max:100000'],
            'developer.head_code' => ['nullable', 'string', 'max:100000'],
            'developer.body_end_code' => ['nullable', 'string', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $templateId = (int) $this->input('detail_template_id');
        $template = $templateId > 0 ? CmsTemplate::query()->find($templateId) : null;

        if (! $template instanceof CmsTemplate) {
            return [];
        }

        $attributes = [];

        foreach (app(CmsTemplateBlockDataContractBuilder::class)->handle($template)['blocks'] as $block) {
            foreach ($block['fields'] as $field) {
                $attributes['template_data.blocks.'.$block['content_key'].'.'.$field['key']] = $this->templateDataAttribute($block, $field);
            }

            foreach ($block['meta_fields'] ?? [] as $field) {
                $attributes['template_data.blocks.'.$block['content_key'].'._meta.'.$field['key']] = $this->templateDataAttribute($block, $field);
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template_data.blocks.*.*.required' => __('cms_admin_ui.validation.required'),
            'template_data.blocks.*.*.max' => __('cms_admin_ui.validation.max_string'),
            'template_data.blocks.*.*.regex' => __('cms_admin_ui.validation.invalid_url'),
            'template_data.blocks.*.*.string' => __('cms_admin_ui.validation.string'),
            'template_data.blocks.*.*.numeric' => __('cms_admin_ui.validation.numeric'),
            'template_data.blocks.*.*.integer' => __('cms_admin_ui.validation.integer'),
            'template_data.blocks.*.*.boolean' => __('cms_admin_ui.validation.boolean'),
            'template_data.blocks.*.*.array' => __('cms_admin_ui.validation.array'),
            'template_data.blocks.*.*.in' => __('cms_admin_ui.validation.invalid_choice'),
            'template_data.blocks.*.*.exists' => __('cms_admin_ui.validation.invalid_choice'),
            'template_data.blocks.*._meta.*.boolean' => __('cms_admin_ui.validation.boolean'),
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
                $pageId = (int) $this->route('id');
                $parentId = (int) $this->input('parent_id');

                if ($pageId > 0 && $parentId > 0 && in_array($parentId, $this->descendantIds($pageId), true)) {
                    $validator->errors()->add(
                        'parent_id',
                        __('cms_admin_ui.validation.page_parent_descendant')
                    );
                }

                foreach (app(CmsJsonLdTemplateValidator::class)->errors($this->input('structured_data_extra'), 'cms.page.json_ld') as $error) {
                    $validator->errors()->add('structured_data_extra', $error);
                }

                if ($this->hasPageDeveloperCode() && ! $this->canManageCodeBlocks()) {
                    $validator->errors()->add(
                        'developer',
                        __('cms_admin_ui.validation.layout_code_block_forbidden')
                    );
                }

                $this->validateSeo($validator, 'page');
            },
        ];
    }

    private function hasPageDeveloperCode(): bool
    {
        $developer = $this->input('developer');

        if (! is_array($developer)) {
            return false;
        }

        return filled($developer['css_source'] ?? null)
            || filled($developer['head_code'] ?? null)
            || filled($developer['body_end_code'] ?? null);
    }

    private function validateSeo(Validator $validator, string $type): void
    {
        $result = app(ValidateCmsSeoRulesAction::class)->handle(
            $this->all(),
            $type,
            $this->input('status') === 'published',
        );

        foreach ($result['errors'] as $error) {
            $validator->errors()->add('status', $error);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function templateDataRules(): array
    {
        $templateId = (int) $this->input('detail_template_id');

        if ($templateId <= 0) {
            return ['template_data' => ['nullable', 'array']];
        }

        $template = CmsTemplate::query()->find($templateId);

        if (! $template instanceof CmsTemplate) {
            return ['template_data' => ['nullable', 'array']];
        }

        return app(CmsTemplateBlockDataContractBuilder::class)->validationRules(
            $template,
            'template_data',
            is_array($this->input('template_data')) ? $this->input('template_data') : [],
        );
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $field
     */
    private function templateDataAttribute(array $block, array $field): string
    {
        $blockLabel = is_scalar($block['editor_label'] ?? null) ? (string) $block['editor_label'] : (string) ($block['content_key'] ?? '');
        $labelKey = is_string($field['label_key'] ?? null) ? $field['label_key'] : null;
        $fieldLabel = $labelKey ? __('cms_admin_ui.'.$labelKey) : (string) ($field['key'] ?? '');

        return trim($blockLabel) !== ''
            ? $blockLabel.' - '.$fieldLabel
            : $fieldLabel;
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(int $pageId): array
    {
        $pages = CmsPage::query()->get(['id', 'parent_id']);
        $descendantIds = [$pageId];
        $frontier = [$pageId];

        while ($frontier !== []) {
            $children = $pages
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $frontier = array_values(array_diff($children, $descendantIds));
            $descendantIds = array_values(array_unique(array_merge($descendantIds, $frontier)));
        }

        return $descendantIds;
    }

    private function canManageCodeBlocks(): bool
    {
        $user = $this->user();

        return (bool) ($user?->is_platform_admin || $user?->canAccessRoute('admin.cms.layouts.code-blocks.manage'));
    }
}
