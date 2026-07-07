<?php

namespace App\Actions\Admin\Cms;

use App\Actions\Admin\Cms\Revisions\CreateCmsRevisionAction;
use App\Models\Cms\CmsDocCollection;
use App\Models\Cms\CmsDocPage;
use App\Models\Cms\CmsDocVersion;
use App\Models\Cms\CmsModule;
use App\Support\Cms\Docs\CmsDocsMarkdownRenderer;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InstallCmsDocsDemoDataAction
{
    public function __construct(
        private readonly CmsDocsMarkdownRenderer $markdownRenderer,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CreateCmsRevisionAction $createRevision,
    ) {}

    /**
     * @return array{collections:int,versions:int,pages:int,revisions:int}
     */
    public function handle(?int $authorId = null): array
    {
        $this->assertDocsModuleInstalled();

        return DB::connection('tenant')->transaction(function () use ($authorId): array {
            $locale = $this->languageSettings->defaultLocale();
            $collectionResult = $this->ensureCollection();
            $versionResult = $this->ensureVersion($collectionResult['model']);
            $pageResult = $this->ensurePages($versionResult['model'], $locale, $authorId);

            return [
                'collections' => $collectionResult['created'] ? 1 : 0,
                'versions' => $versionResult['created'] ? 1 : 0,
                'pages' => $pageResult['pages'],
                'revisions' => $pageResult['revisions'],
            ];
        });
    }

    private function assertDocsModuleInstalled(): void
    {
        if (! CmsModule::query()->where('key', InstallCmsDocsModuleAction::KEY)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages([
                'module' => __('cms_admin_ui.validation.cms_module_not_installed'),
            ]);
        }

        foreach (['cms_doc_collections', 'cms_doc_versions', 'cms_doc_pages'] as $table) {
            if (! Schema::connection('tenant')->hasTable($table)) {
                throw ValidationException::withMessages([
                    'module' => __('cms_admin_ui.validation.cms_module_schema_missing'),
                ]);
            }
        }
    }

    /**
     * @return array{model:CmsDocCollection,created:bool}
     */
    private function ensureCollection(): array
    {
        $collection = CmsDocCollection::query()
            ->where('settings->demo_key', 'docs-demo')
            ->first();

        $created = ! $collection instanceof CmsDocCollection;
        $collection ??= new CmsDocCollection;

        $collection->fill([
            'name' => 'Documentation Demo',
            'slug' => $collection->exists ? $collection->slug : $this->uniqueCollectionSlug('documentation-demo'),
            'description' => 'Demo documentation collection with examples for all documentation module features.',
            'is_active' => true,
            'sort_order' => 900,
            'settings' => array_merge(is_array($collection->settings) ? $collection->settings : [], [
                'source' => 'docs_demo_install',
                'demo_key' => 'docs-demo',
            ]),
        ])->save();

        return ['model' => $collection, 'created' => $created];
    }

    /**
     * @return array{model:CmsDocVersion,created:bool}
     */
    private function ensureVersion(CmsDocCollection $collection): array
    {
        $version = CmsDocVersion::query()
            ->where('cms_doc_collection_id', $collection->id)
            ->where('settings->demo_key', 'docs-demo-v1')
            ->first();

        $created = ! $version instanceof CmsDocVersion;
        $version ??= new CmsDocVersion;

        $version->fill([
            'cms_doc_collection_id' => $collection->id,
            'label' => 'v1 Demo',
            'slug' => $version->exists ? $version->slug : $this->uniqueVersionSlug($collection, 'v1-demo'),
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 900,
            'settings' => array_merge(is_array($version->settings) ? $version->settings : [], [
                'source' => 'docs_demo_install',
                'demo_key' => 'docs-demo-v1',
            ]),
        ])->save();

        return ['model' => $version, 'created' => $created];
    }

    /**
     * @return array{pages:int,revisions:int}
     */
    private function ensurePages(CmsDocVersion $version, string $locale, ?int $authorId): array
    {
        $pages = 0;
        $revisions = 0;
        $parentIds = [];

        foreach ($this->pageDefinitions() as $definition) {
            $parentId = isset($definition['parent']) ? ($parentIds[$definition['parent']] ?? null) : null;
            $page = CmsDocPage::withTrashed()
                ->where('cms_doc_version_id', $version->id)
                ->where('locale', $locale)
                ->where('settings->demo_key', $definition['key'])
                ->first();
            $page ??= new CmsDocPage;
            $rendered = $this->markdownRenderer->render($definition['body'], $locale);

            if (method_exists($page, 'restore') && $page->trashed()) {
                $page->restore();
            }

            $page->fill([
                'cms_doc_version_id' => $version->id,
                'parent_id' => $parentId,
                'author_id' => $page->author_id ?: $authorId,
                'title' => $definition['title'],
                'slug' => Str::slug($definition['title']),
                'path' => $definition['path'],
                'locale' => $locale,
                'translation_key' => 'docs-demo-'.$definition['key'],
                'translated_from_doc_page_id' => null,
                'status' => 'draft',
                'body_format' => 'markdown',
                'body' => $definition['body'],
                'plain_text' => $rendered['plain_text'],
                'seo_title' => $definition['title'].' | Documentation Demo',
                'seo_description' => $definition['description'],
                'noindex' => true,
                'sort_order' => $definition['sort_order'],
                'published_at' => null,
                'settings' => array_merge(is_array($page->settings) ? $page->settings : [], [
                    'source' => 'docs_demo_install',
                    'demo_key' => $definition['key'],
                ]),
            ])->save();

            $pages++;
            $parentIds[$definition['key']] = (int) $page->id;

            $revision = $this->createRevision->handle(
                $page,
                'full',
                $this->revisionSnapshot($page),
                $authorId,
                __('cms_admin_ui.revisions.auto_revision_title'),
                metadata: ['change_type' => 'demo-data'],
            );

            if ($revision !== null) {
                $revisions++;
            }
        }

        return ['pages' => $pages, 'revisions' => $revisions];
    }

    /**
     * @return array<int, array{key:string,title:string,path:string,parent?:string,sort_order:int,description:string,body:string}>
     */
    private function pageDefinitions(): array
    {
        return [
            [
                'key' => 'introduction',
                'title' => 'Introduction',
                'path' => 'introduction',
                'sort_order' => 10,
                'description' => 'Overview of the documentation demo module content.',
                'body' => <<<'MD'
# Documentation demo

This demo collection shows how the documentation module can be used for a real manual.

## What is included

- A versioned collection.
- A nested page tree.
- Markdown-only content.
- Generated page text for future search indexing.
- Admonitions, code blocks, media references and SEO metadata.

:::note
Demo pages are created as drafts, so they are safe to inspect before publishing.
:::

## Recommended workflow

1. Review the generated pages in the admin area.
2. Adjust content to match your project.
3. Publish only the pages you want to expose publicly.
MD,
            ],
            [
                'key' => 'getting-started',
                'title' => 'Getting started',
                'path' => 'getting-started',
                'sort_order' => 20,
                'description' => 'First steps for creating documentation content.',
                'body' => <<<'MD'
# Getting started

Use documentation pages when content needs a stable URL, a version and a logical position in a manual.

## Create a collection

A collection groups related documentation. Examples are product manuals, API guides or internal procedures.

## Create a version

Versions let you keep content available for older releases.

:::tip
Use short version slugs like `v1`, `v2` or `2026.1` so public URLs remain readable.
:::

## Add pages

Pages are ordered with `sort_order` and can be nested under parent pages.
MD,
            ],
            [
                'key' => 'writing-content',
                'title' => 'Writing content',
                'path' => 'writing-content',
                'sort_order' => 30,
                'description' => 'Parent chapter for markdown writing examples.',
                'body' => <<<'MD'
# Writing content

Documentation content uses Markdown. This keeps content portable and avoids arbitrary HTML in database content.

## Content principles

- Keep headings descriptive.
- Prefer short paragraphs.
- Use lists for steps and requirements.
- Use admonitions for important context.

:::warning
Raw HTML is intentionally not part of the first version of the documentation module.
:::
MD,
            ],
            [
                'key' => 'markdown-basics',
                'title' => 'Markdown basics',
                'path' => 'writing-content/markdown-basics',
                'parent' => 'writing-content',
                'sort_order' => 40,
                'description' => 'Markdown syntax examples for documentation pages.',
                'body' => <<<'MD'
# Markdown basics

Markdown supports common formatting features.

## Text formatting

Use **bold text** for emphasis and _italic text_ for subtle emphasis.

## Lists

Ordered list:

1. Open the documentation module.
2. Choose a version.
3. Create or edit a page.

Unordered list:

- Collections
- Versions
- Pages

## Code blocks

```php
return [
    'docs' => true,
];
```

## Links

Use regular Markdown links such as [Laravel](https://laravel.com).
MD,
            ],
            [
                'key' => 'headings-and-toc',
                'title' => 'Headings and table of contents',
                'path' => 'writing-content/headings-and-toc',
                'parent' => 'writing-content',
                'sort_order' => 50,
                'description' => 'Heading examples that generate the page table of contents.',
                'body' => <<<'MD'
# Headings and table of contents

Headings are detected automatically and become the page table of contents.

## Main section

Use level two headings for major sections.

### Subsection

Use level three headings for details within a section.

## Another main section

Each heading receives a stable anchor for in-page navigation.

### Practical advice

Keep heading text unique where possible. This helps readers scan the right-side table of contents.
MD,
            ],
            [
                'key' => 'admonitions',
                'title' => 'Admonitions',
                'path' => 'writing-content/admonitions',
                'parent' => 'writing-content',
                'sort_order' => 60,
                'description' => 'Examples for note, tip, warning and danger blocks.',
                'body' => <<<'MD'
# Admonitions

Admonitions help highlight important information without custom HTML.

:::note
Use notes for neutral context or background information.
:::

:::tip
Use tips for recommended shortcuts, best practices or helpful suggestions.
:::

:::warning
Use warnings for risky actions or common mistakes.
:::

:::danger
Use danger blocks for destructive actions, security risks or production-impacting changes.
:::
MD,
            ],
            [
                'key' => 'media-references',
                'title' => 'Images and media references',
                'path' => 'writing-content/images-and-media',
                'parent' => 'writing-content',
                'sort_order' => 70,
                'description' => 'How documentation pages reference CMS media assets.',
                'body' => <<<'MD'
# Images and media references

Documentation pages can reference CMS media assets with a portable media syntax.

## Syntax

```markdown
![Dashboard screenshot](media:123)
```

The renderer resolves `media:123` to the public URL for media asset `123`.

:::tip
Use clear alternative text. It improves accessibility and helps readers understand images when they cannot be loaded.
:::

## Portability

Site package export/import maps media references to package import keys and back to target media IDs.
MD,
            ],
            [
                'key' => 'structure-and-navigation',
                'title' => 'Structure and navigation',
                'path' => 'structure-and-navigation',
                'sort_order' => 80,
                'description' => 'How nested pages become the documentation navigation tree.',
                'body' => <<<'MD'
# Structure and navigation

The public documentation layout shows two navigation areas.

## Left navigation

The left navigation contains the full page tree for the selected version.

## Right navigation

The right navigation contains the headings for the current page.

## Mobile layout

On smaller screens the navigation remains accessible while keeping the page content readable.

:::note
Page nesting is controlled by the parent page field in the admin editor.
:::
MD,
            ],
            [
                'key' => 'translations-and-review',
                'title' => 'Translations and review flow',
                'path' => 'translations-and-review',
                'sort_order' => 90,
                'description' => 'Translation and AI review behavior for documentation pages.',
                'body' => <<<'MD'
# Translations and review flow

Documentation pages support language variants through `translation_key`.

## Manual translations

Create a language variant and edit the content manually.

## AI assisted translations

AI-generated translations are created as drafts and marked for review.

:::warning
AI-generated content should always be reviewed before publication.
:::

## Publishing translated content

Publish each language variant only after the content and links are verified.
MD,
            ],
            [
                'key' => 'publishing-and-seo',
                'title' => 'Publishing and SEO',
                'path' => 'publishing-and-seo',
                'sort_order' => 100,
                'description' => 'How draft status, SEO metadata and public URLs work.',
                'body' => <<<'MD'
# Publishing and SEO

Docs pages use the same publication idea as the rest of the CMS: draft content is not public.

## Drafts

Draft pages can be prepared safely in the admin area.

## Published pages

Published pages are available on public documentation URLs.

## SEO metadata

Each page can define a meta title, meta description and noindex setting.

:::danger
Do not publish demo content on production sites unless it is intentionally part of the public website.
:::
MD,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revisionSnapshot(CmsDocPage $page): array
    {
        return $page->only([
            'cms_doc_version_id',
            'parent_id',
            'title',
            'slug',
            'path',
            'locale',
            'status',
            'body_format',
            'body',
            'seo_title',
            'seo_description',
            'noindex',
            'sort_order',
            'settings',
        ]);
    }

    private function uniqueCollectionSlug(string $source): string
    {
        $slug = Str::slug($source) ?: 'docs-demo';
        $candidate = $slug;
        $index = 2;

        while (CmsDocCollection::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$index;
            $index++;
        }

        return $candidate;
    }

    private function uniqueVersionSlug(CmsDocCollection $collection, string $source): string
    {
        $slug = Str::slug($source) ?: 'v1-demo';
        $candidate = $slug;
        $index = 2;

        while (CmsDocVersion::query()->where('cms_doc_collection_id', $collection->id)->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$index;
            $index++;
        }

        return $candidate;
    }
}
