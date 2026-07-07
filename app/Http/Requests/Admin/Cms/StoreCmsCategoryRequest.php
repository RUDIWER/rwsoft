<?php

namespace App\Http\Requests\Admin\Cms;

use App\Actions\Admin\Cms\Seo\ValidateCmsSeoRulesAction;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPlaceableBlockRevision;
use App\Models\Cms\CmsTemplate;
use App\Rules\CmsCanonicalUrl;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\PublicSite\CmsJsonLdTemplateValidator;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale($this->user(), (string) $this->input('locale'));
    }

    protected function prepareForValidation(): void
    {
        $categoryId = (int) $this->route('id');

        if ($categoryId <= 0) {
            return;
        }

        $locale = CmsCategory::query()
            ->whereKey($categoryId)
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
        $categoryId = (int) $this->route('id');
        $locales = app(CmsLanguageSettings::class)->activeLocales();
        $blockRegistry = app(CmsBlockRegistry::class);

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('cms_categories', 'id'),
                Rule::notIn([$categoryId]),
            ],
            'type' => ['required', 'string', Rule::in(['post'])],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('cms_categories', 'slug')
                    ->where(fn ($query) => $query
                        ->where('type', $this->string('type')->toString())
                        ->where('locale', $this->string('locale')->toString()))
                    ->ignore($categoryId > 0 ? $categoryId : null),
            ],
            'locale' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2}([_-][A-Z]{2})?$/', Rule::in($locales)],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'archive_template_id' => [
                'nullable',
                'integer',
                Rule::exists((new CmsTemplate)->getTable(), 'id')
                    ->where('template_class', 'category')
                    ->where('template_key', 'category.archive')
                    ->where('locale', $this->string('locale')->toString())
                    ->where('is_active', true),
            ],
            'detail_template_id' => [
                'nullable',
                'integer',
                Rule::exists((new CmsTemplate)->getTable(), 'id')
                    ->where('template_class', 'category')
                    ->where('template_key', 'category.detail')
                    ->where('locale', $this->string('locale')->toString())
                    ->where('is_active', true),
            ],
            'is_active' => ['nullable', 'boolean'],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'template' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:5000'],
            ...$blockRegistry->contentBlockRules('content_blocks'),
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'canonical_url' => ['nullable', 'string', 'max:255', new CmsCanonicalUrl((string) $this->input('locale'))],
            'og_image_path' => ['nullable', 'string', 'max:255'],
            'noindex' => ['nullable', 'boolean'],
            'is_searchable' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'pdf_download_enabled' => ['nullable', 'boolean'],
            'structured_data_schema_type' => ['nullable', 'string', Rule::in(['auto', 'WebPage', 'AboutPage', 'ContactPage', 'FAQPage', 'Service', 'None'])],
            'structured_data_extra' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $categoryId = (int) $this->route('id');
                $parentId = (int) $this->input('parent_id');

                if ($categoryId > 0 && $parentId > 0 && in_array($parentId, $this->descendantIds($categoryId), true)) {
                    $validator->errors()->add(
                        'parent_id',
                        'Een categorie kan niet onder zichzelf of een onderliggende categorie geplaatst worden.'
                    );
                }

                $this->validateParentScope($validator, $parentId);
                $this->validateLandingPageSlug($validator, $categoryId);
                $this->validateBlockUrls($validator);
                $this->validateContentPlaceableBlocks($validator);

                foreach (app(CmsJsonLdTemplateValidator::class)->errors($this->input('structured_data_extra'), 'cms.page.json_ld') as $error) {
                    $validator->errors()->add('structured_data_extra', $error);
                }

                $this->validateSeo($validator, 'category');
            },
        ];
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

    private function validateLandingPageSlug(Validator $validator, int $categoryId): void
    {
        $landingPageId = $categoryId > 0
            ? CmsCategory::query()->whereKey($categoryId)->value('landing_page_id')
            : null;

        $exists = CmsPage::query()
            ->where('locale', $this->string('locale')->toString())
            ->where('slug', $this->string('slug')->toString())
            ->when($landingPageId, fn ($query) => $query->whereKeyNot($landingPageId))
            ->exists();

        if ($exists) {
            $validator->errors()->add('slug', 'Er bestaat al een CMS-pagina met deze slug in deze taal.');
        }
    }

    private function validateBlockUrls(Validator $validator): void
    {
        foreach ((array) $this->input('content_blocks', []) as $index => $block) {
            $url = is_array($block) ? (string) ($block['url'] ?? '') : '';

            if ($url !== '' && ! str_starts_with($url, '/') && ! preg_match('/^https?:\/\//i', $url)) {
                $validator->errors()->add(
                    "content_blocks.{$index}.url",
                    'Een knop URL moet relatief zijn of beginnen met http(s).'
                );
            }
        }
    }

    private function validateContentPlaceableBlocks(Validator $validator): void
    {
        $blocks = collect((array) $this->input('content_blocks', []))
            ->filter(fn ($block): bool => is_array($block))
            ->values();

        $placeableBlockIds = $blocks
            ->map(fn (array $block): int => (int) ($block['cms_placeable_block_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $revisionIds = $blocks
            ->map(fn (array $block): int => (int) ($block['placeable_block_revision_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $placeableBlocks = CmsPlaceableBlock::query()
            ->with('latestPublishedRevision')
            ->whereIn('id', $placeableBlockIds)
            ->get()
            ->keyBy('id');
        $revisions = CmsPlaceableBlockRevision::query()
            ->whereIn('id', $revisionIds)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->get()
            ->keyBy('id');

        foreach ($blocks as $index => $block) {
            $placeableBlock = $placeableBlocks->get((int) ($block['cms_placeable_block_id'] ?? 0));

            if (! $placeableBlock instanceof CmsPlaceableBlock || $placeableBlock->status !== 'published' || $placeableBlock->latestPublishedRevision === null) {
                $validator->errors()->add("content_blocks.{$index}.cms_placeable_block_id", __('cms_admin_ui.validation.placeable_block_unavailable'));

                continue;
            }

            if (! in_array('content', $placeableBlock->allowed_zones ?? [], true)) {
                $validator->errors()->add("content_blocks.{$index}.cms_placeable_block_id", __('cms_admin_ui.validation.layout_block_zone_forbidden'));
            }

            if ($placeableBlock->requires_permission && ! $this->canManageCodeBlocks()) {
                $validator->errors()->add("content_blocks.{$index}.cms_placeable_block_id", __('cms_admin_ui.validation.layout_code_block_forbidden'));
            }

            $revisionId = (int) ($block['placeable_block_revision_id'] ?? 0);

            if ($revisionId > 0) {
                $revision = $revisions->get($revisionId);

                if (! $revision instanceof CmsPlaceableBlockRevision || (int) $revision->cms_placeable_block_id !== (int) $placeableBlock->id) {
                    $validator->errors()->add("content_blocks.{$index}.placeable_block_revision_id", __('cms_admin_ui.validation.placeable_block_revision_unavailable'));
                }
            }
        }
    }

    private function canManageCodeBlocks(): bool
    {
        $user = $this->user();

        return (bool) ($user?->is_platform_admin || $user?->canAccessRoute('admin.cms.layouts.code-blocks.manage'));
    }

    private function validateParentScope(Validator $validator, int $parentId): void
    {
        if ($parentId <= 0) {
            return;
        }

        $parent = CmsCategory::query()->find($parentId, ['id', 'type', 'locale']);

        if (! $parent instanceof CmsCategory) {
            return;
        }

        if ($parent->type !== $this->string('type')->toString() || $parent->locale !== $this->string('locale')->toString()) {
            $validator->errors()->add(
                'parent_id',
                'De bovenliggende categorie moet hetzelfde type en dezelfde taal hebben.'
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(int $categoryId): array
    {
        $categories = CmsCategory::query()->get(['id', 'parent_id']);
        $descendantIds = [$categoryId];
        $frontier = [$categoryId];

        while ($frontier !== []) {
            $children = $categories
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
}
