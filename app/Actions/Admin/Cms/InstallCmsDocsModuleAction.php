<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsDocCollection;
use App\Models\Cms\CmsDocVersion;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsModule;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPlaceableBlockRevision;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InstallCmsDocsModuleAction
{
    public const KEY = 'docs';

    public const VERSION = 1;

    public function __construct(private readonly SyncPublicTextKeysAction $syncPublicTextKeys) {}

    /**
     * @return array{module:int,permissions:int,templates:int,collections:int,versions:int,public_texts:int,translations:int}
     */
    public function handle(): array
    {
        $this->assertSchemaReady();
        $this->ensureDocsSchema();

        return DB::connection('tenant')->transaction(function (): array {
            $this->ensureModuleRecord();
            $permissions = $this->ensurePermissions();
            $blocks = $this->ensureDocsPlaceableBlocks();
            $this->cleanupObsoleteTemplates();
            $this->cleanupDuplicateTemplates();
            $templates = $this->ensureTemplates($blocks);
            $defaults = $this->ensureDefaultCollectionAndVersion();
            $publicTexts = $this->syncPublicTextKeys->handle();

            return [
                'module' => 1,
                'permissions' => $permissions,
                'templates' => $templates,
                'collections' => $defaults['collections'],
                'versions' => $defaults['versions'],
                'public_texts' => (int) $publicTexts['texts_created'],
                'translations' => (int) $publicTexts['translations_created'],
            ];
        });
    }

    private function assertSchemaReady(): void
    {
        foreach (['cms_modules', 'cms_templates', 'cms_layouts'] as $table) {
            if (! Schema::connection('tenant')->hasTable($table)) {
                throw ValidationException::withMessages([
                    'module' => __('cms_admin_ui.validation.cms_module_schema_missing'),
                ]);
            }
        }

        if (! Schema::connection('tenant')->hasColumn('cms_templates', 'module_key')) {
            throw ValidationException::withMessages([
                'module' => __('cms_admin_ui.validation.cms_module_schema_missing'),
            ]);
        }
    }

    private function ensureDocsSchema(): void
    {
        if (! Schema::connection('tenant')->hasTable('cms_doc_collections')) {
            Schema::connection('tenant')->create('cms_doc_collections', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('description', 1000)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::connection('tenant')->hasTable('cms_doc_versions')) {
            Schema::connection('tenant')->create('cms_doc_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_doc_collection_id')->constrained('cms_doc_collections')->cascadeOnDelete();
                $table->string('label');
                $table->string('slug');
                $table->boolean('is_default')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->unique(['cms_doc_collection_id', 'slug']);
            });
        }

        if (! Schema::connection('tenant')->hasTable('cms_doc_pages')) {
            Schema::connection('tenant')->create('cms_doc_pages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_doc_version_id')->constrained('cms_doc_versions')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('cms_doc_pages')->nullOnDelete();
                $table->unsignedBigInteger('author_id')->nullable()->index();
                $table->string('title');
                $table->string('slug');
                $table->string('path')->index();
                $table->string('locale', 12)->index();
                $table->string('translation_key', 64)->nullable()->index();
                $table->foreignId('translated_from_doc_page_id')->nullable()->constrained('cms_doc_pages')->nullOnDelete();
                $table->string('status', 32)->default('draft')->index();
                $table->string('body_format', 32)->default('markdown');
                $table->longText('body')->nullable();
                $table->longText('plain_text')->nullable();
                $table->string('seo_title')->nullable();
                $table->string('seo_description', 1000)->nullable();
                $table->boolean('noindex')->default(false)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamp('published_at')->nullable()->index();
                $table->json('settings')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->unique(['cms_doc_version_id', 'locale', 'path']);
                $table->unique(['cms_doc_version_id', 'locale', 'translation_key'], 'cms_doc_pages_version_locale_translation_unique');
                $table->index(['cms_doc_version_id', 'locale', 'status', 'sort_order'], 'cms_doc_pages_tree_index');
            });
        }
    }

    private function ensureModuleRecord(): void
    {
        $module = CmsModule::query()->firstOrNew(['key' => self::KEY]);
        $settings = is_array($module->settings) ? $module->settings : [];

        $module->fill([
            'name' => 'Documentation',
            'status' => 'active',
            'settings' => array_merge($settings, [
                'module_version' => self::VERSION,
                'synced_at' => now()->toISOString(),
            ]),
            'installed_at' => $module->installed_at ?: now(),
        ])->save();
    }

    private function ensurePermissions(): int
    {
        if (! Schema::hasTable('acl_permissions')) {
            return 0;
        }

        $now = now();
        $permissions = [
            ['route_name' => 'admin.cms.docs.index', 'description' => '[CMS] Documentation overview', 'action_id' => 19, 'menu' => true, 'url' => 'admin/cms/docs'],
            ['route_name' => 'admin.cms.docs.collections.create', 'description' => '[CMS] Documentation collection create', 'action_id' => 28, 'menu' => false, 'url' => 'admin/cms/docs/collections/create'],
            ['route_name' => 'admin.cms.docs.collections.pages', 'description' => '[CMS] Documentation collection pages', 'action_id' => 19, 'menu' => false, 'url' => 'admin/cms/docs/collections/{collection}/pages'],
            ['route_name' => 'admin.cms.docs.collections.edit', 'description' => '[CMS] Documentation collection edit', 'action_id' => 5, 'menu' => false, 'url' => 'admin/cms/docs/collections/{collection}/edit'],
            ['route_name' => 'admin.cms.docs.collections.store', 'description' => '[CMS] Documentation collection save', 'action_id' => 4, 'menu' => false, 'url' => 'admin/cms/docs/collections/{collection}/store'],
            ['route_name' => 'admin.cms.docs.versions.create', 'description' => '[CMS] Documentation version create', 'action_id' => 28, 'menu' => false, 'url' => 'admin/cms/docs/versions/create'],
            ['route_name' => 'admin.cms.docs.versions.edit', 'description' => '[CMS] Documentation version edit', 'action_id' => 5, 'menu' => false, 'url' => 'admin/cms/docs/versions/{version}/edit'],
            ['route_name' => 'admin.cms.docs.versions.store', 'description' => '[CMS] Documentation version save', 'action_id' => 4, 'menu' => false, 'url' => 'admin/cms/docs/versions/{version}/store'],
            ['route_name' => 'admin.cms.docs.pages.create', 'description' => '[CMS] Documentation page create', 'action_id' => 28, 'menu' => false, 'url' => 'admin/cms/docs/pages/create'],
            ['route_name' => 'admin.cms.docs.pages.edit', 'description' => '[CMS] Documentation page edit', 'action_id' => 5, 'menu' => false, 'url' => 'admin/cms/docs/pages/{page}/edit'],
            ['route_name' => 'admin.cms.docs.pages.store', 'description' => '[CMS] Documentation page save', 'action_id' => 4, 'menu' => false, 'url' => 'admin/cms/docs/pages/{page}/store'],
            ['route_name' => 'admin.cms.docs.pages.translations.store', 'description' => '[CMS] Documentation page translate', 'action_id' => 31, 'menu' => false, 'url' => 'admin/cms/docs/pages/{page}/translations'],
            ['route_name' => 'admin.cms.docs.pages.bulk-status', 'description' => '[CMS] Documentation pages publish or unpublish', 'action_id' => 21, 'menu' => false, 'url' => 'admin/cms/docs/pages/bulk-status'],
        ];

        foreach ($permissions as $permission) {
            DB::table('acl_permissions')->updateOrInsert(
                ['route_name' => $permission['route_name']],
                [
                    'description' => $permission['description'],
                    'module_id' => 2,
                    'action_id' => $permission['action_id'],
                    'type_id' => 1,
                    'query_id' => null,
                    'menu' => $permission['menu'],
                    'url' => $permission['url'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $this->ensureAdminRoleAccess(collect($permissions)->pluck('route_name')->all(), $now);

        return count($permissions);
    }

    /**
     * @param  array<int, string>  $routeNames
     */
    private function ensureAdminRoleAccess(array $routeNames, mixed $now): void
    {
        if (! Schema::hasTable('acl_roles') || ! Schema::hasTable('acl_permission_role')) {
            return;
        }

        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');

        if (! $adminRoleId) {
            return;
        }

        $permissionIds = DB::table('acl_permissions')
            ->whereIn('route_name', $routeNames)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('acl_permission_role')->updateOrInsert(
                ['acl_role_id' => $adminRoleId, 'acl_permission_id' => $permissionId],
                ['active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    /**
     * @return array<string, array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}>
     */
    private function ensureDocsPlaceableBlocks(): array
    {
        return collect($this->docsBlockKeys())
            ->mapWithKeys(fn (string $key): array => [$key => $this->ensureDocsPlaceableBlock($key)])
            ->all();
    }

    /**
     * @return array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}
     */
    private function ensureDocsPlaceableBlock(string $key): array
    {
        $definition = (array) config("cms_blocks.types.{$key}", []);
        $now = now();
        $zones = array_values(array_filter((array) ($definition['zones'] ?? ['content']), fn (mixed $zone): bool => is_string($zone) && $zone !== ''));
        $schema = [
            'category' => $definition['category'] ?? null,
            'fields' => array_values((array) ($definition['fields'] ?? [])),
            'editor_fields' => array_values((array) Arr::get($definition, 'editor.fields', [])),
            'editor_visible' => (bool) ($definition['editor_visible'] ?? true),
            'preview' => is_array($definition['preview'] ?? null) ? $definition['preview'] : [],
        ];
        $payload = [
            'name' => $this->blockName($key),
            'description' => null,
            'category' => (string) ($definition['category'] ?? 'system'),
            'source' => 'system',
            'status' => 'published',
            'allowed_zones' => $zones,
            'rendering_mode' => (string) ($definition['rendering_mode'] ?? 'platform_blade'),
            'renderer_key' => $key,
            'template_source' => null,
            'css_source' => null,
            'schema' => $schema,
            'defaults' => is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [],
            'capabilities' => [
                'can_edit_template' => false,
                'can_edit_css' => false,
                'can_edit_fields' => false,
                'can_edit_allowed_zones' => false,
                'can_edit_renderer' => false,
                'can_edit_defaults' => false,
            ],
            'behavior_config' => [],
            'context_config' => [],
            'admin_component_key' => null,
            'package_key' => self::KEY,
            'sort_order' => 0,
            'is_locked' => true,
            'requires_permission' => null,
            'published_at' => $now,
        ];

        $block = CmsPlaceableBlock::query()->withTrashed()->firstOrNew(['key' => $key]);
        $block->fill($payload);
        $block->deleted_at = null;
        $block->save();

        $revision = CmsPlaceableBlockRevision::query()->firstOrNew([
            'cms_placeable_block_id' => $block->id,
            'revision_number' => 1,
        ]);
        $revision->fill(array_merge($payload, [
            'status' => 'published',
            'title' => $payload['name'],
            'snapshot_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'author_id' => null,
            'metadata' => ['source' => 'docs-module'],
        ]));
        $revision->save();

        return ['block' => $block, 'revision' => $revision];
    }

    /**
     * @param  array<string, array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}>  $blocks
     */
    private function ensureTemplates(array $blocks): int
    {
        $synced = 0;
        $templateKeys = $this->templateDefinitions();

        foreach ($this->templateLocales() as $locale) {
            $layout = $this->layoutForLocale($locale);

            if (! $layout instanceof CmsLayout) {
                continue;
            }

            foreach ($templateKeys as $templateKey => $name) {
                $template = CmsTemplate::query()
                    ->where('template_key', $templateKey)
                    ->where('locale', $locale)
                    ->where(function ($query): void {
                        $query->where('module_key', self::KEY)->orWhereNull('module_key');
                    })
                    ->orderByRaw('module_key = ? desc', [self::KEY])
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->first();

                if (! $template instanceof CmsTemplate) {
                    $template = new CmsTemplate;
                    $template->import_key = $this->templateImportKey($templateKey, $locale);
                }

                $templateSettings = is_array($template->settings) ? $template->settings : [];

                $template->fill([
                    'name' => $template->name ?: $name.' ('.strtoupper($locale).')',
                    'locale' => $locale,
                    'translation_key' => $template->translation_key ?: 'module.docs.'.$templateKey,
                    'layout_id' => $this->validLayoutId($template->layout_id, $locale) ?? (int) $layout->id,
                    'template_class' => 'module',
                    'template_key' => $templateKey,
                    'module_key' => self::KEY,
                    'is_default' => true,
                    'is_active' => true,
                    'cache_strategy' => $template->cache_strategy ?: 'inherit',
                    'settings' => array_merge($templateSettings, [
                        'html_anchor' => $templateSettings['html_anchor'] ?? 'tmpl-docs-'.Str::slug($templateKey).'-'.$locale,
                        'source' => $templateSettings['source'] ?? 'docs_module_install',
                    ]),
                    'data_contract' => is_array($template->data_contract) ? $template->data_contract : [],
                ])->save();

                $this->ensureTemplateContent($template, $blocks);
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * @param  array<string, array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}>  $blocks
     */
    private function ensureTemplateContent(CmsTemplate $template, array $blocks): void
    {
        $section = CmsSection::query()->firstOrCreate(
            [
                'owner_type' => CmsTemplate::class,
                'owner_id' => $template->id,
                'zone' => 'content',
                'import_key' => $this->templateImportKey((string) $template->template_key, (string) $template->locale).':content',
            ],
            [
                'name' => 'Documentation content',
                'sort_order' => 0,
                'is_active' => true,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'settings' => [
                    'layout_type' => 'grid',
                    'width_mode' => 'content',
                    'spacing' => 'normal',
                ],
            ],
        );

        foreach ($this->templateBlockKeys((string) $template->template_key) as $index => $blockKey) {
            $this->ensureTemplatePlacement($section, $blockKey, $blocks, $index * 10);
        }
    }

    /**
     * @param  array<string, array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}>  $blocks
     */
    private function ensureTemplatePlacement(CmsSection $section, string $blockKey, array $blocks, int $sortOrder): void
    {
        if (! isset($blocks[$blockKey])) {
            return;
        }

        $placeableBlock = $blocks[$blockKey]['block'];
        $revision = $blocks[$blockKey]['revision'];
        $block = CmsBlock::query()->firstOrCreate(
            ['import_key' => $section->import_key.':'.$blockKey],
            [
                'cms_placeable_block_id' => $placeableBlock->id,
                'placeable_block_revision_id' => $revision->id,
                'type' => $blockKey,
                'name' => $this->blockName($blockKey),
                'content' => [],
                'settings' => [],
                'is_shared' => false,
                'is_dynamic' => false,
                'cache_strategy' => 'none',
                'created_by' => null,
            ],
        );

        $block->forceFill([
            'cms_placeable_block_id' => $placeableBlock->id,
            'placeable_block_revision_id' => $revision->id,
            'type' => $blockKey,
        ])->save();

        CmsBlockPlacement::query()->updateOrCreate(
            [
                'cms_section_id' => $section->id,
                'cms_block_id' => $block->id,
                'import_key' => $section->import_key.':'.$blockKey.':placement',
            ],
            array_merge([
                'sort_order' => $sortOrder,
                'is_active' => true,
                ...$this->placementVisibility($blockKey),
                'layout_config' => $this->placementLayoutConfig($blockKey),
                'height_mode' => 'auto',
                'height_value' => null,
                'cache_strategy' => 'none',
                'settings' => [],
            ], $this->placementSpans($blockKey)),
        );
    }

    private function cleanupDuplicateTemplates(): void
    {
        foreach ($this->templateLocales() as $locale) {
            foreach (array_keys($this->templateDefinitions()) as $templateKey) {
                $templates = CmsTemplate::query()
                    ->where('template_key', $templateKey)
                    ->where('locale', $locale)
                    ->where('module_key', self::KEY)
                    ->orderBy('id')
                    ->get();

                if ($templates->count() <= 1) {
                    continue;
                }

                $keeper = $this->templateKeeper($templates, $locale);

                $templates
                    ->reject(fn (CmsTemplate $template): bool => $template->is($keeper))
                    ->each(fn (CmsTemplate $template): ?bool => $this->deleteTemplateWithOwnContent($template));

                if (! $keeper->is_default) {
                    $keeper->forceFill(['is_default' => true])->save();
                }
            }
        }
    }

    private function cleanupObsoleteTemplates(): void
    {
        CmsTemplate::withTrashed()
            ->where('module_key', self::KEY)
            ->whereIn('template_key', ['docs.index', 'docs.version'])
            ->get()
            ->each(fn (CmsTemplate $template): ?bool => $this->deleteTemplateWithOwnContent($template));
    }

    private function deleteTemplateWithOwnContent(CmsTemplate $template): ?bool
    {
        $sections = CmsSection::query()
            ->where('owner_type', CmsTemplate::class)
            ->where('owner_id', $template->id)
            ->with('placements.childPlacements')
            ->get();
        $placementIds = $sections
            ->flatMap(fn (CmsSection $section) => $section->placements->flatMap(fn (CmsBlockPlacement $placement) => collect([$placement->id])->merge($placement->childPlacements->pluck('id'))))
            ->map(fn (mixed $id): int => (int) $id)
            ->values();
        $blockIds = CmsBlockPlacement::query()
            ->whereIn('id', $placementIds)
            ->pluck('cms_block_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        CmsBlockPlacement::query()->whereIn('parent_placement_id', $placementIds)->delete();
        CmsBlockPlacement::query()->whereIn('id', $placementIds)->delete();
        CmsSection::query()->whereIn('id', $sections->pluck('id'))->delete();

        $blockIds->each(function (int $blockId): void {
            $hasOtherPlacements = CmsBlockPlacement::query()->where('cms_block_id', $blockId)->exists();

            if (! $hasOtherPlacements) {
                CmsBlock::query()
                    ->whereKey($blockId)
                    ->where('is_shared', false)
                    ->delete();
            }
        });

        return $template->forceDelete();
    }

    /**
     * @param  Collection<int, CmsTemplate>  $templates
     */
    private function templateKeeper($templates, string $locale): CmsTemplate
    {
        $layout = $this->layoutForLocale($locale);

        if ($layout instanceof CmsLayout) {
            $template = $templates->first(fn (CmsTemplate $candidate): bool => (int) $candidate->layout_id === (int) $layout->id);

            if ($template instanceof CmsTemplate) {
                return $template;
            }
        }

        return $templates->firstWhere('is_default', true) ?? $templates->first();
    }

    private function layoutForLocale(string $locale): ?CmsLayout
    {
        return $this->homePageLayout($locale)
            ?? CmsLayout::query()->active()->defaultForLocale($locale)->first()
            ?? CmsLayout::query()->active()->where('locale', $locale)->orderBy('id')->first();
    }

    private function homePageLayout(string $locale): ?CmsLayout
    {
        $homePage = CmsPage::query()
            ->with('detailTemplate.layout')
            ->where('locale', $locale)
            ->where('is_home', true)
            ->where('status', 'published')
            ->first();

        $layout = $homePage?->detailTemplate?->layout;

        return $homePage?->detailTemplate instanceof CmsTemplate
            && $homePage->detailTemplate->is_active
            && $layout instanceof CmsLayout
            && $layout->is_active
            && $layout->locale === $locale
            ? $layout
            : null;
    }

    private function validLayoutId(?int $layoutId, string $locale): ?int
    {
        if (! $layoutId) {
            return null;
        }

        return CmsLayout::query()
            ->active()
            ->where('locale', $locale)
            ->whereKey($layoutId)
            ->exists()
                ? $layoutId
                : null;
    }

    /**
     * @return list<string>
     */
    private function templateLocales(): array
    {
        return CmsLayout::query()
            ->active()
            ->orderBy('locale')
            ->pluck('locale')
            ->map(fn (mixed $locale): string => trim((string) $locale))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function templateDefinitions(): array
    {
        return [
            'docs.detail' => 'Documentation detail',
        ];
    }

    /**
     * @return list<string>
     */
    private function docsBlockKeys(): array
    {
        return ['docs_mobile_actions', 'docs_navigation', 'docs_content', 'docs_page_toc'];
    }

    /**
     * @return list<string>
     */
    private function templateBlockKeys(string $templateKey): array
    {
        return $this->docsBlockKeys();
    }

    /**
     * @return array<string, int>
     */
    private function placementSpans(string $blockKey): array
    {
        return match ($blockKey) {
            'docs_navigation' => ['mobile_span' => 12, 'tablet_span' => 12, 'desktop_span' => 3],
            'docs_content' => ['mobile_span' => 12, 'tablet_span' => 12, 'desktop_span' => 6],
            'docs_page_toc' => ['mobile_span' => 12, 'tablet_span' => 12, 'desktop_span' => 3],
            default => ['mobile_span' => 12, 'tablet_span' => 12, 'desktop_span' => 12],
        };
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function placementLayoutConfig(string $blockKey): array
    {
        return match ($blockKey) {
            'docs_navigation' => [
                'mobile' => ['x' => 0, 'y' => 1, 'w' => 12, 'h' => 1],
                'tablet' => ['x' => 0, 'y' => 1, 'w' => 12, 'h' => 1],
                'desktop' => ['x' => 0, 'y' => 0, 'w' => 3, 'h' => 1],
            ],
            'docs_content' => [
                'mobile' => ['x' => 0, 'y' => 2, 'w' => 12, 'h' => 1],
                'tablet' => ['x' => 0, 'y' => 2, 'w' => 12, 'h' => 1],
                'desktop' => ['x' => 3, 'y' => 0, 'w' => 6, 'h' => 1],
            ],
            'docs_page_toc' => [
                'mobile' => ['x' => 0, 'y' => 3, 'w' => 12, 'h' => 1],
                'tablet' => ['x' => 0, 'y' => 1, 'w' => 12, 'h' => 1],
                'desktop' => ['x' => 9, 'y' => 0, 'w' => 3, 'h' => 1],
            ],
            default => [
                'mobile' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 1],
                'tablet' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 1],
                'desktop' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 1],
            ],
        };
    }

    /**
     * @return array{visible_mobile: bool, visible_tablet: bool, visible_desktop: bool}
     */
    private function placementVisibility(string $blockKey): array
    {
        if ($blockKey === 'docs_mobile_actions') {
            return [
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => false,
            ];
        }

        return [
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
        ];
    }

    private function templateImportKey(string $templateKey, string $locale): string
    {
        return 'system.module.docs.'.str_replace('.', '-', $templateKey).'.'.$locale;
    }

    private function blockName(string $key): string
    {
        $definition = (array) config("cms_blocks.types.{$key}", []);
        $labelKey = (string) ($definition['label_key'] ?? '');

        return $labelKey !== '' ? (string) __('cms_admin_ui.'.$labelKey) : Str::headline($key);
    }

    /**
     * @return array{collections:int,versions:int}
     */
    private function ensureDefaultCollectionAndVersion(): array
    {
        if (CmsDocCollection::query()->exists()) {
            return ['collections' => 0, 'versions' => 0];
        }

        $collection = CmsDocCollection::query()->create([
            'name' => 'Documentation',
            'slug' => 'documentation',
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
            'settings' => ['source' => 'docs_module_install'],
        ]);

        CmsDocVersion::query()->create([
            'cms_doc_collection_id' => $collection->id,
            'label' => 'v1',
            'slug' => 'v1',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
            'settings' => ['source' => 'docs_module_install'],
        ]);

        return ['collections' => 1, 'versions' => 1];
    }
}
