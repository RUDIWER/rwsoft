<?php

namespace App\Actions\Admin\Cms\Health;

use App\Actions\Admin\Cms\Seo\ValidateCmsSeoRulesAction;
use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsModule;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Support\Collection;

class BuildCmsHealthReportAction
{
    public function __construct(
        private readonly CmsLanguageSettings $languageSettings,
        private readonly ValidateCmsSeoRulesAction $seoRules,
    ) {}

    /**
     * @return array{summary: array<string, int>, issues: array<int, array<string, mixed>>, repairs: array<string, string>}
     */
    public function handle(): array
    {
        $issues = collect()
            ->merge($this->menuIssues())
            ->merge($this->contentReferenceIssues())
            ->merge($this->mediaIssues())
            ->merge($this->formIssues())
            ->merge($this->publicAccountIssues())
            ->merge($this->seoIssues())
            ->merge($this->translationIssues())
            ->values();

        return [
            'summary' => [
                'error' => $issues->where('severity', 'error')->count(),
                'warning' => $issues->where('severity', 'warning')->count(),
                'info' => $issues->where('severity', 'info')->count(),
                'total' => $issues->count(),
            ],
            'issues' => $issues->all(),
            'repairs' => [
                'public_account' => route('admin.cms.health.public-account.repair'),
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function menuIssues(): Collection
    {
        return CmsMenuItem::query()
            ->with(['menu:id,title', 'page:id,title,status,published_at', 'post:id,title,status,published_at'])
            ->where('is_active', true)
            ->get()
            ->flatMap(function (CmsMenuItem $item): array {
                $target = $item->type === 'post' ? $item->post : $item->page;

                if (in_array($item->type, ['page', 'category', 'post'], true) && $target === null) {
                    return [$this->issue('error', 'menus', 'cms_menu_item', $item->id, $item->label, __('cms_admin_ui.health.issues.menu_missing_target'), route('admin.cms.menus.edit', ['id' => $item->cms_menu_id, 'item' => $item->id]), 'technical')];
                }

                if ($target instanceof CmsPage || $target instanceof CmsPost) {
                    if (! $this->isPublished($target->status, $target->published_at)) {
                        return [$this->issue('warning', 'menus', 'cms_menu_item', $item->id, $item->label, __('cms_admin_ui.health.issues.menu_unpublished_target'), route('admin.cms.menus.edit', ['id' => $item->cms_menu_id, 'item' => $item->id]), 'technical')];
                    }
                }

                return [];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function contentReferenceIssues(): Collection
    {
        $issues = collect();

        $this->contentOwners()->each(function (array $owner) use ($issues): void {
            foreach ($this->blocksFrom($owner['blocks']) as $index => $block) {
                foreach ($this->blockReferenceIssues($owner, $block, $index) as $issue) {
                    $issues->push($issue);
                }
            }
        });

        return $issues;
    }

    /**
     * @return Collection<int, array{module: string, record_type: string, record_id: int, title: string, edit_url: string, blocks: array<int, array<string, mixed>>}>
     */
    private function contentOwners(): Collection
    {
        return collect()
            ->merge(CmsPage::query()->with('contentSections.placements.block')->get(['id', 'title', 'content_blocks'])->map(fn (CmsPage $page): array => [
                'module' => 'pages',
                'record_type' => 'cms_page',
                'record_id' => (int) $page->id,
                'title' => (string) $page->title,
                'edit_url' => route('admin.cms.pages.edit', ['id' => $page->id]),
                'blocks' => $this->blocksForPage($page),
            ]))
            ->merge(CmsPost::query()->get(['id', 'title', 'content_blocks'])->map(fn (CmsPost $post): array => [
                'module' => 'posts',
                'record_type' => 'cms_post',
                'record_id' => (int) $post->id,
                'title' => (string) $post->title,
                'edit_url' => route('admin.cms.posts.edit', ['id' => $post->id]),
                'blocks' => $post->content_blocks ?? [],
            ]))
            ->merge(CmsCategory::query()->with('landingPage:id,content_blocks')->get(['id', 'title', 'landing_page_id'])->map(fn (CmsCategory $category): array => [
                'module' => 'categories',
                'record_type' => 'cms_category',
                'record_id' => (int) $category->id,
                'title' => (string) $category->title,
                'edit_url' => route('admin.cms.categories.edit', ['id' => $category->id]),
                'blocks' => $category->landingPage?->content_blocks ?? [],
            ]))
            ->merge(CmsTag::query()->with('landingPage:id,content_blocks')->get(['id', 'title', 'landing_page_id'])->map(fn (CmsTag $tag): array => [
                'module' => 'tags',
                'record_type' => 'cms_tag',
                'record_id' => (int) $tag->id,
                'title' => (string) $tag->title,
                'edit_url' => route('admin.cms.tags.edit', ['id' => $tag->id]),
                'blocks' => $tag->landingPage?->content_blocks ?? [],
            ]));
    }

    /**
     * @param  array<string, mixed>  $owner
     * @param  array<string, mixed>  $block
     * @return array<int, array<string, mixed>>
     */
    private function blockReferenceIssues(array $owner, array $block, int $index): array
    {
        $issues = [];
        $title = $owner['title'].' #'.($index + 1);

        if (($block['type'] ?? null) === 'form' && filled($block['form_translation_key'] ?? null)) {
            $exists = CmsForm::query()->where('translation_key', $block['form_translation_key'])->where('is_active', true)->exists();

            if (! $exists) {
                $issues[] = $this->issue('error', $owner['module'], $owner['record_type'], $owner['record_id'], $title, __('cms_admin_ui.health.issues.block_missing_form'), $owner['edit_url'], 'forms');
            }
        }

        if (($block['type'] ?? null) === 'image' && filled($block['media_asset_id'] ?? null) && ! CmsMediaAsset::query()->whereKey((int) $block['media_asset_id'])->exists()) {
            $issues[] = $this->issue('error', $owner['module'], $owner['record_type'], $owner['record_id'], $title, __('cms_admin_ui.health.issues.block_missing_media'), $owner['edit_url'], 'media');
        }

        foreach ((array) ($block['media_asset_ids'] ?? []) as $mediaAssetId) {
            if (! CmsMediaAsset::query()->whereKey((int) $mediaAssetId)->exists()) {
                $issues[] = $this->issue('error', $owner['module'], $owner['record_type'], $owner['record_id'], $title, __('cms_admin_ui.health.issues.block_missing_media'), $owner['edit_url'], 'media');
                break;
            }
        }

        if (filled($block['category_id'] ?? null) && ! CmsCategory::query()->whereKey((int) $block['category_id'])->exists()) {
            $issues[] = $this->issue('error', $owner['module'], $owner['record_type'], $owner['record_id'], $title, __('cms_admin_ui.health.issues.block_missing_category'), $owner['edit_url'], 'technical');
        }

        if (filled($block['tag_id'] ?? null) && ! CmsTag::query()->whereKey((int) $block['tag_id'])->exists()) {
            $issues[] = $this->issue('error', $owner['module'], $owner['record_type'], $owner['record_id'], $title, __('cms_admin_ui.health.issues.block_missing_tag'), $owner['edit_url'], 'technical');
        }

        return $issues;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function formIssues(): Collection
    {
        $issues = collect();

        CmsForm::query()->withCount(['fields as active_fields_count' => fn ($query) => $query->where('is_active', true)])->where('is_active', true)->get(['id', 'title'])->each(function (CmsForm $form) use ($issues): void {
            if ((int) $form->active_fields_count === 0) {
                $issues->push($this->issue('error', 'forms', 'cms_form', $form->id, $form->title, __('cms_admin_ui.health.issues.form_no_active_fields'), route('admin.cms.forms.edit', ['id' => $form->id]), 'forms'));
            }
        });

        CmsFormField::query()->with(['form:id,title'])->withCount('values')->where('is_active', false)->get(['id', 'cms_form_id', 'label'])->each(function (CmsFormField $field) use ($issues): void {
            if ((int) $field->values_count > 0 && $field->form instanceof CmsForm) {
                $issues->push($this->issue('warning', 'forms', 'cms_form_field', $field->id, $field->form->title.' - '.$field->label, __('cms_admin_ui.health.issues.form_inactive_answered_field'), route('admin.cms.forms.edit', ['id' => $field->cms_form_id]), 'forms'));
            }
        });

        return $issues;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function mediaIssues(): Collection
    {
        $issues = collect();
        $defaultLocale = $this->languageSettings->defaultLocale();
        $activeLocales = collect($this->languageSettings->activeLocales())
            ->map(fn (mixed $locale): string => (string) $locale)
            ->filter()
            ->values();

        CmsMediaAsset::query()
            ->with('translations')
            ->get(['id', 'filename', 'original_filename', 'path', 'alt_text'])
            ->each(function (CmsMediaAsset $asset) use ($issues, $activeLocales, $defaultLocale): void {
                $title = $asset->original_filename ?: ($asset->filename ?: $asset->path);
                $actionUrl = route('admin.cms.media.edit', ['id' => $asset->id]);

                if (blank($asset->alt_text)) {
                    $issues->push($this->issue(
                        'warning',
                        'media',
                        'cms_media_asset',
                        (int) $asset->id,
                        (string) $title,
                        __('cms_admin_ui.health.issues.media_missing_alt_text'),
                        $actionUrl,
                        'media',
                    ));
                }

                if (! $this->languageSettings->multilingualEnabled()) {
                    return;
                }

                $translations = $asset->translations->keyBy('locale');
                $missingLocales = $activeLocales
                    ->reject(fn (string $locale): bool => $locale === $defaultLocale)
                    ->filter(fn (string $locale): bool => blank($translations->get($locale)?->alt_text))
                    ->values();

                if ($missingLocales->isNotEmpty()) {
                    $issues->push($this->issue(
                        'warning',
                        'media',
                        'cms_media_asset',
                        (int) $asset->id,
                        (string) $title,
                        __('cms_admin_ui.health.issues.media_missing_translation_alt_text', ['locales' => $missingLocales->implode(', ')]),
                        $actionUrl,
                        'media',
                    ));
                }
            });

        return $issues;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function publicAccountIssues(): Collection
    {
        if (! CmsModule::query()->where('key', 'public-account')->where('status', 'active')->exists()) {
            return collect();
        }

        $issues = collect();

        foreach ($this->publicAccountLocales() as $locale) {
            $accountPage = CmsPage::query()
                ->where('locale', $locale)
                ->where('slug', 'account')
                ->whereNull('parent_id')
                ->first(['id', 'title', 'status', 'published_at', 'detail_template_id']);

            foreach ($this->publicAccountTemplateDefinitions() as $slug => $definition) {
                $template = CmsTemplate::query()
                    ->where('locale', $locale)
                    ->where('template_key', $definition['template_key'])
                    ->first(['id', 'name', 'locale', 'template_key', 'is_active']);
                $title = __('cms_admin_ui.health.public_account.record_title', [
                    'page' => $slug,
                    'locale' => $locale,
                ]);

                if (! $template instanceof CmsTemplate) {
                    $issues->push($this->issue('error', 'public_account', 'cms_template', 0, $title, __('cms_admin_ui.health.issues.public_account_template_missing'), route('admin.cms.health.index'), 'public_account'));

                    continue;
                }

                if (! $template->is_active) {
                    $issues->push($this->issue('error', 'public_account', 'cms_template', (int) $template->id, (string) $template->name, __('cms_admin_ui.health.issues.public_account_template_inactive'), route('admin.cms.templates.edit', ['id' => $template->id]), 'public_account'));
                }

                foreach ($definition['blocks'] as $blockType) {
                    if (! $this->templateHasActiveBlock($template, $blockType)) {
                        $issues->push($this->issue('error', 'public_account', 'cms_template', (int) $template->id, (string) $template->name, __('cms_admin_ui.health.issues.public_account_template_block_missing', ['block' => $blockType]), route('admin.cms.templates.edit', ['id' => $template->id]), 'public_account'));
                    }
                }

                $page = $slug === 'account'
                    ? $accountPage
                    : ($accountPage instanceof CmsPage
                        ? CmsPage::query()
                            ->where('locale', $locale)
                            ->where('slug', $slug)
                            ->where('parent_id', $accountPage->id)
                            ->first(['id', 'title', 'status', 'published_at', 'detail_template_id'])
                        : null);

                if (! $page instanceof CmsPage) {
                    $issues->push($this->issue('error', 'public_account', 'cms_page', 0, $title, __('cms_admin_ui.health.issues.public_account_page_missing'), route('admin.cms.health.index'), 'public_account'));

                    continue;
                }

                if ((int) $page->detail_template_id !== (int) $template->id) {
                    $issues->push($this->issue('error', 'public_account', 'cms_page', (int) $page->id, (string) $page->title, __('cms_admin_ui.health.issues.public_account_page_template_mismatch'), route('admin.cms.pages.edit', ['id' => $page->id]), 'public_account'));
                }

                if (! $this->isPublished($page->status, $page->published_at)) {
                    $issues->push($this->issue('error', 'public_account', 'cms_page', (int) $page->id, (string) $page->title, __('cms_admin_ui.health.issues.public_account_page_unpublished'), route('admin.cms.pages.edit', ['id' => $page->id]), 'public_account'));
                }
            }
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function publicAccountLocales(): array
    {
        $locales = $this->languageSettings->multilingualEnabled()
            ? $this->languageSettings->activeLocales()
            : [$this->languageSettings->defaultLocale()];

        return collect($locales)
            ->push($this->languageSettings->defaultLocale())
            ->map(fn (mixed $locale): string => trim((string) $locale))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{template_key: string, blocks: list<string>}>
     */
    private function publicAccountTemplateDefinitions(): array
    {
        return [
            'account' => ['template_key' => 'system.account.auth', 'blocks' => ['site_user_auth_panel']],
            'login' => ['template_key' => 'system.account.login', 'blocks' => ['site_user_auth_panel']],
            'register' => ['template_key' => 'system.account.register', 'blocks' => ['site_user_auth_panel']],
            'forgot-password' => ['template_key' => 'system.account.forgot_password', 'blocks' => ['site_user_forgot_password_form']],
            'reset-password' => ['template_key' => 'system.account.reset_password', 'blocks' => ['site_user_reset_password_form']],
            'dashboard' => ['template_key' => 'system.account.dashboard', 'blocks' => ['site_user_account_controls', 'site_user_dashboard']],
            'profile' => ['template_key' => 'system.account.profile', 'blocks' => ['site_user_account_controls', 'site_user_profile_form']],
            'security' => ['template_key' => 'system.account.security', 'blocks' => ['site_user_account_controls', 'site_user_security_settings']],
            'two-factor-challenge' => ['template_key' => 'system.account.two_factor_challenge', 'blocks' => ['site_user_two_factor_challenge']],
        ];
    }

    private function templateHasActiveBlock(CmsTemplate $template, string $blockType): bool
    {
        return $template->sections()
            ->where('zone', 'content')
            ->where('is_active', true)
            ->whereHas('placements', function ($query) use ($blockType): void {
                $query
                    ->where('is_active', true)
                    ->whereHas('block', fn ($query) => $query->where('type', $blockType));
            })
            ->exists();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function seoIssues(): Collection
    {
        return collect()
            ->merge(CmsPage::query()->where('status', 'published')->get(['id', 'title', 'slug', 'status', 'short_description', 'content_blocks', 'seo_title', 'seo_description', 'canonical_url', 'og_image_path', 'settings'])->flatMap(fn (CmsPage $page): array => $this->seoIssuesFor('pages', 'cms_page', $page->id, $page->title, $this->seoDataForPage($page), 'page', true, route('admin.cms.pages.edit', ['id' => $page->id]))))
            ->merge(CmsPost::query()->where('status', 'published')->get(['id', 'title', 'slug', 'status', 'excerpt', 'content_blocks', 'seo_title', 'seo_description', 'canonical_url', 'og_image_path', 'featured_media_asset_id', 'settings'])->flatMap(fn (CmsPost $post): array => $this->seoIssuesFor('posts', 'cms_post', $post->id, $post->title, $this->seoDataForPost($post), 'post', true, route('admin.cms.posts.edit', ['id' => $post->id]))))
            ->merge(CmsCategory::query()->with('landingPage:id,title,slug,status,short_description,content_blocks,seo_title,seo_description,canonical_url,og_image_path,settings')->get(['id', 'title', 'slug', 'description', 'is_active', 'landing_page_id'])->flatMap(fn (CmsCategory $category): array => $this->seoIssuesFor('categories', 'cms_category', $category->id, $category->title, $this->seoDataForTaxonomy($category), 'page', (bool) $category->is_active, route('admin.cms.categories.edit', ['id' => $category->id]))))
            ->merge(CmsTag::query()->with('landingPage:id,title,slug,status,short_description,content_blocks,seo_title,seo_description,canonical_url,og_image_path,settings')->get(['id', 'title', 'slug', 'description', 'is_active', 'landing_page_id'])->flatMap(fn (CmsTag $tag): array => $this->seoIssuesFor('tags', 'cms_tag', $tag->id, $tag->title, $this->seoDataForTaxonomy($tag), 'page', (bool) $tag->is_active, route('admin.cms.tags.edit', ['id' => $tag->id]))));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function seoIssuesFor(string $module, string $recordType, int $recordId, string $title, array $data, string $type, bool $publishing, string $url): array
    {
        $issues = [];
        $result = $this->seoRules->handle($data, $type, $publishing);

        foreach ($result['errors'] as $message) {
            $issues[] = $this->issue('error', $module, $recordType, $recordId, $title, $message, $url, 'seo');
        }

        foreach ($result['warnings'] as $message) {
            $issues[] = $this->issue('warning', $module, $recordType, $recordId, $title, $message, $url, 'seo');
        }

        return $issues;
    }

    /**
     * @return array<string, mixed>
     */
    private function seoDataForPage(CmsPage $page): array
    {
        return [
            'title' => $page->title,
            'slug' => $page->slug,
            'short_description' => $page->short_description,
            'content_blocks' => $page->content_blocks ?? [],
            'seo_title' => $page->seo_title,
            'seo_description' => $page->seo_description,
            'canonical_url' => $page->canonical_url,
            'og_image_path' => $page->og_image_path,
            'structured_data_extra' => $page->settings['structured_data_extra'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seoDataForPost(CmsPost $post): array
    {
        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'content_blocks' => $post->content_blocks ?? [],
            'seo_title' => $post->seo_title,
            'seo_description' => $post->seo_description,
            'canonical_url' => $post->canonical_url,
            'og_image_path' => $post->og_image_path,
            'featured_media_asset_id' => $post->featured_media_asset_id,
            'structured_data_extra' => $post->settings['structured_data_extra'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seoDataForTaxonomy(CmsCategory|CmsTag $taxonomy): array
    {
        $page = $taxonomy->landingPage;

        return [
            'title' => $taxonomy->title,
            'slug' => $taxonomy->slug,
            'excerpt' => $taxonomy->description,
            'content_blocks' => $page?->content_blocks ?? [],
            'seo_title' => $page?->seo_title,
            'seo_description' => $page?->seo_description,
            'canonical_url' => $page?->canonical_url,
            'og_image_path' => $page?->og_image_path,
            'structured_data_extra' => $page?->settings['structured_data_extra'] ?? null,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function translationIssues(): Collection
    {
        if (! $this->languageSettings->multilingualEnabled()) {
            return collect();
        }

        $activeLocales = collect($this->languageSettings->activeLocales())->map(fn (mixed $locale): string => (string) $locale)->filter()->values();

        return collect()
            ->merge($this->missingTranslationIssues(CmsPage::class, 'pages', 'cms_page', 'title', 'admin.cms.pages.edit', $activeLocales))
            ->merge($this->missingTranslationIssues(CmsPost::class, 'posts', 'cms_post', 'title', 'admin.cms.posts.edit', $activeLocales))
            ->merge($this->missingTranslationIssues(CmsCategory::class, 'categories', 'cms_category', 'title', 'admin.cms.categories.edit', $activeLocales))
            ->merge($this->missingTranslationIssues(CmsTag::class, 'tags', 'cms_tag', 'title', 'admin.cms.tags.edit', $activeLocales))
            ->merge($this->missingTranslationIssues(CmsForm::class, 'forms', 'cms_form', 'title', 'admin.cms.forms.edit', $activeLocales));
    }

    /**
     * @param  class-string  $modelClass
     * @param  Collection<int, string>  $activeLocales
     * @return Collection<int, array<string, mixed>>
     */
    private function missingTranslationIssues(string $modelClass, string $module, string $recordType, string $titleColumn, string $routeName, Collection $activeLocales): Collection
    {
        return $modelClass::query()
            ->whereNotNull('translation_key')
            ->get(['id', 'locale', 'translation_key', $titleColumn])
            ->groupBy('translation_key')
            ->flatMap(function (Collection $group) use ($module, $recordType, $titleColumn, $routeName, $activeLocales): array {
                $existingLocales = $group->pluck('locale')->map(fn (mixed $locale): string => (string) $locale)->all();
                $missingLocales = $activeLocales->diff($existingLocales)->values();

                if ($missingLocales->isEmpty()) {
                    return [];
                }

                $record = $group->first();

                return [$this->issue(
                    'info',
                    $module,
                    $recordType,
                    (int) $record->id,
                    (string) $record->{$titleColumn},
                    __('cms_admin_ui.health.issues.missing_translation', ['locales' => $missingLocales->implode(', ')]),
                    route($routeName, ['id' => $record->id]),
                    'translations',
                )];
            })
            ->values();
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function blocksFrom(array $blocks): array
    {
        return collect($blocks)
            ->map(fn (mixed $block): mixed => $this->normalizeBlock($block))
            ->filter(fn (mixed $block): bool => is_array($block))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blocksForPage(CmsPage $page): array
    {
        $sectionBlocks = $page->contentSections
            ->flatMap(fn ($section) => $section->placements)
            ->map(fn ($placement): mixed => $placement->block instanceof CmsBlock ? [
                'type' => $placement->block->type,
                'content' => $placement->block->content ?? [],
            ] : null)
            ->all();

        return array_merge($page->content_blocks ?? [], $sectionBlocks);
    }

    private function normalizeBlock(mixed $block): mixed
    {
        if (! is_array($block)) {
            return $block;
        }

        if (isset($block['content']) && is_array($block['content'])) {
            return array_merge(['type' => $block['type'] ?? null], $block['content']);
        }

        return $block;
    }

    private function isPublished(?string $status, mixed $publishedAt): bool
    {
        return $status === 'published' && ($publishedAt === null || $publishedAt->isPast());
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(string $severity, string $module, string $recordType, int $recordId, ?string $title, string $message, string $actionUrl, ?string $category = null): array
    {
        return [
            'severity' => $severity,
            'category' => $category ?? ($module === 'forms' ? 'forms' : 'technical'),
            'module' => $module,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'title' => $title ?: '#'.$recordId,
            'message' => $message,
            'action_url' => $actionUrl,
        ];
    }
}
