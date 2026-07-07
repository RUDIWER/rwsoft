<?php

namespace App\Http\Requests\Admin\Cms;

use App\Actions\Admin\Cms\Seo\ValidateCmsSeoRulesAction;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPlaceableBlockRevision;
use App\Models\Cms\CmsTag;
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

class StoreCmsPostRequest extends FormRequest
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
        $postId = (int) $this->route('id');
        $locales = app(CmsLanguageSettings::class)->activeLocales();
        $blockRegistry = app(CmsBlockRegistry::class);

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('cms_posts', 'slug')
                    ->where(fn ($query) => $query->where('locale', $this->string('locale')->toString()))
                    ->ignore($postId > 0 ? $postId : null),
            ],
            'locale' => ['required', 'string', 'max:12', Rule::in($locales)],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'detail_template_id' => [
                'nullable',
                'integer',
                Rule::exists((new CmsTemplate)->getTable(), 'id')
                    ->where('template_class', 'blog')
                    ->where('template_key', 'blog.detail')
                    ->where('locale', $this->string('locale')->toString())
                    ->where('is_active', true),
            ],
            'excerpt' => ['nullable', 'string', 'max:5000'],
            ...$blockRegistry->contentBlockRules('content_blocks'),
            'featured_media_asset_id' => ['nullable', 'integer', Rule::exists('cms_media_assets', 'id')->whereNull('deleted_at')],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'canonical_url' => ['nullable', 'string', 'max:255', new CmsCanonicalUrl((string) $this->input('locale'))],
            'og_image_path' => ['nullable', 'string', 'max:255'],
            'noindex' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_searchable' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'pdf_download_enabled' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('cms_categories', 'id')->where('type', 'post')],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('cms_tags', 'id')],
            'structured_data_schema_type' => ['nullable', 'string', Rule::in(['auto', 'BlogPosting', 'NewsArticle', 'Article', 'None'])],
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
                $locale = $this->string('locale')->toString();
                $categoryIds = array_filter((array) $this->input('category_ids', []));
                $tagIds = array_filter((array) $this->input('tag_ids', []));

                if ($categoryIds !== [] && CmsCategory::query()->whereIn('id', $categoryIds)->where('locale', '!=', $locale)->exists()) {
                    $validator->errors()->add('category_ids', 'Categorieën moeten dezelfde taal hebben als het bericht.');
                }

                if ($tagIds !== [] && CmsTag::query()->whereIn('id', $tagIds)->where('locale', '!=', $locale)->exists()) {
                    $validator->errors()->add('tag_ids', 'Tags moeten dezelfde taal hebben als het bericht.');
                }

                foreach ((array) $this->input('content_blocks', []) as $index => $block) {
                    $url = is_array($block) ? (string) ($block['url'] ?? '') : '';

                    if ($url !== '' && ! str_starts_with($url, '/') && ! preg_match('/^https?:\/\//i', $url)) {
                        $validator->errors()->add(
                            "content_blocks.{$index}.url",
                            'Een knop URL moet relatief zijn of beginnen met http(s).'
                        );
                    }
                }

                $this->validateContentPlaceableBlocks($validator);

                foreach (app(CmsJsonLdTemplateValidator::class)->errors($this->input('structured_data_extra'), 'cms.post.json_ld') as $error) {
                    $validator->errors()->add('structured_data_extra', $error);
                }

                $this->validateSeo($validator, 'post');
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
}
