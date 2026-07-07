<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsDocPage;
use App\Support\Ai\TranslationAiService;
use App\Support\Cms\Docs\CmsDocsMarkdownRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOrUpdateCmsDocPageTranslationAction
{
    public function __construct(
        private readonly TranslationAiService $translationAiService,
        private readonly CmsDocsMarkdownRenderer $markdownRenderer,
    ) {}

    /**
     * @param  array{title?: string, body?: string, seo_title?: string|null, seo_description?: string|null}  $sourceData
     * @param  array<int, string>  $targetLocales
     * @return array{created: int, updated: int, pages: array<int, CmsDocPage>}
     */
    public function handle(CmsDocPage $sourcePage, array $sourceData, array $targetLocales, int $authorId, bool $useAi = true): array
    {
        $targetLocales = collect($targetLocales)
            ->map(fn (string $locale): string => trim($locale))
            ->filter(fn (string $locale): bool => $locale !== '' && $locale !== (string) $sourcePage->locale)
            ->unique()
            ->values()
            ->all();

        if ($targetLocales === []) {
            return ['created' => 0, 'updated' => 0, 'pages' => []];
        }

        $translationKey = $this->ensureTranslationKey($sourcePage);
        $sourceData = $this->normalizedSourceData($sourcePage, $sourceData);
        $translations = [];

        foreach ($targetLocales as $targetLocale) {
            $translations[$targetLocale] = $useAi
                ? $this->translatedData($sourceData, (string) $sourcePage->locale, $targetLocale)
                : $sourceData;
        }

        $pages = [];
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($sourcePage, $sourceData, $targetLocales, $authorId, $useAi, $translationKey, $translations, &$pages, &$created, &$updated): void {
            foreach ($targetLocales as $targetLocale) {
                $translatedData = $translations[$targetLocale] ?? $sourceData;

                $page = CmsDocPage::query()
                    ->where('translation_key', $translationKey)
                    ->where('locale', $targetLocale)
                    ->first();

                $isCreate = ! $page instanceof CmsDocPage;
                $page ??= new CmsDocPage;

                $body = (string) ($translatedData['body'] ?? $sourceData['body']);
                $rendered = $this->markdownRenderer->render($body, $targetLocale);

                $page->fill([
                    'cms_doc_version_id' => $sourcePage->cms_doc_version_id,
                    'parent_id' => $this->translatedParentId($sourcePage, $targetLocale),
                    'author_id' => $isCreate ? $authorId : $page->author_id,
                    'title' => (string) ($translatedData['title'] ?? $sourceData['title']),
                    'slug' => $isCreate ? $this->uniqueSlug((string) ($translatedData['title'] ?? $sourceData['title']), (int) $sourcePage->cms_doc_version_id, $targetLocale) : $page->slug,
                    'path' => $isCreate ? $this->uniquePath((string) $sourcePage->path, (int) $sourcePage->cms_doc_version_id, $targetLocale) : $page->path,
                    'locale' => $targetLocale,
                    'translation_key' => $translationKey,
                    'translated_from_doc_page_id' => $sourcePage->id,
                    'status' => $useAi ? 'draft' : ($isCreate ? 'draft' : $page->status),
                    'body_format' => 'markdown',
                    'body' => $body,
                    'plain_text' => $rendered['plain_text'],
                    'seo_title' => $translatedData['seo_title'] ?? $sourceData['seo_title'],
                    'seo_description' => $translatedData['seo_description'] ?? $sourceData['seo_description'],
                    'noindex' => $useAi ? true : ($isCreate ? true : $page->noindex),
                    'sort_order' => $sourcePage->sort_order,
                    'published_at' => $useAi ? null : $page->published_at,
                    'settings' => $this->settings($page, $useAi),
                ]);
                $page->save();

                $pages[] = $page;

                if ($isCreate) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated, 'pages' => $pages];
    }

    private function ensureTranslationKey(CmsDocPage $page): string
    {
        if (filled($page->translation_key)) {
            return (string) $page->translation_key;
        }

        $translationKey = (string) Str::ulid();
        $page->forceFill(['translation_key' => $translationKey])->save();

        return $translationKey;
    }

    /**
     * @param  array{title?: string, body?: string, seo_title?: string|null, seo_description?: string|null}  $sourceData
     * @return array{title: string, body: string, seo_title: string|null, seo_description: string|null}
     */
    private function normalizedSourceData(CmsDocPage $sourcePage, array $sourceData): array
    {
        return [
            'title' => (string) ($sourceData['title'] ?? $sourcePage->title),
            'body' => (string) ($sourceData['body'] ?? $sourcePage->body),
            'seo_title' => $sourceData['seo_title'] ?? $sourcePage->seo_title,
            'seo_description' => $sourceData['seo_description'] ?? $sourcePage->seo_description,
        ];
    }

    /**
     * @param  array{title: string, body: string, seo_title: string|null, seo_description: string|null}  $sourceData
     * @return array<string, string>
     */
    private function translatedData(array $sourceData, string $sourceLocale, string $targetLocale): array
    {
        return $this->translationAiService->translateBatch([
            ['id' => 'title', 'key' => 'title', 'source_text' => $sourceData['title']],
            ['id' => 'body', 'key' => 'markdown_body', 'source_text' => $sourceData['body']],
            ['id' => 'seo_title', 'key' => 'seo_title', 'source_text' => (string) ($sourceData['seo_title'] ?? '')],
            ['id' => 'seo_description', 'key' => 'seo_description', 'source_text' => (string) ($sourceData['seo_description'] ?? '')],
        ], $sourceLocale, $targetLocale, 'You translate CMS documentation content. Preserve Markdown formatting and never convert Markdown to HTML. Never add explanations. Return exactly one translated text for each provided id.', [
            'Preserve Markdown heading levels, lists, tables, links, images, code fences, inline code, frontmatter-like syntax, and admonition fences such as :::tip, :::warning, and :::danger.',
            'Preserve media tokens such as media:123 and image syntax such as ![Alt](media:123). Translate image alt text, but do not change media ids or URLs.',
            'Preserve placeholders like :name, :count, {0}, {1}, and {{ token }} exactly.',
            'Return one translation per id.',
        ]);
    }

    private function translatedParentId(CmsDocPage $sourcePage, string $targetLocale): ?int
    {
        if (! $sourcePage->parent_id) {
            return null;
        }

        $parent = CmsDocPage::query()->find($sourcePage->parent_id, ['translation_key']);

        if (! $parent instanceof CmsDocPage || blank($parent->translation_key)) {
            return null;
        }

        $id = CmsDocPage::query()
            ->where('translation_key', $parent->translation_key)
            ->where('locale', $targetLocale)
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function uniqueSlug(string $title, int $versionId, string $locale): string
    {
        $baseSlug = Str::slug($title) ?: 'page';
        $slug = $baseSlug;
        $counter = 2;

        while (CmsDocPage::query()->where('cms_doc_version_id', $versionId)->where('locale', $locale)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function uniquePath(string $sourcePath, int $versionId, string $locale): string
    {
        $basePath = trim($sourcePath, '/') ?: 'page';
        $path = $basePath;
        $counter = 2;

        while (CmsDocPage::query()->where('cms_doc_version_id', $versionId)->where('locale', $locale)->where('path', $path)->exists()) {
            $path = $basePath.'-'.$counter;
            $counter++;
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(CmsDocPage $page, bool $useAi): array
    {
        $settings = is_array($page->settings ?? null) ? $page->settings : [];

        if (! $useAi) {
            return $settings;
        }

        return array_merge($settings, [
            'translation_source' => 'ai',
            'translation_review_status' => 'pending',
        ]);
    }
}
