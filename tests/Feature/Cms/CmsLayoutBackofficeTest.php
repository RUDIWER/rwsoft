<?php

namespace Tests\Feature\Cms;

use App\Actions\Admin\Cms\CreateCmsLayoutTranslationAction;
use App\Actions\Admin\Cms\SaveCmsLayoutSectionsAction;
use App\Actions\Admin\Cms\SaveCmsSectionsAction;
use App\Http\Middleware\AuthAdminUsers;
use App\Http\Middleware\AuthorizeAdminRoute;
use App\Http\Middleware\EnsureSiteMembership;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenantSite;
use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsLanguage;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsTemplate;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class CmsLayoutBackofficeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.mysql.database' => 'rwsoft',
            'database.connections.central.driver' => 'mysql',
            'database.connections.central.host' => config('database.connections.mysql.host'),
            'database.connections.central.port' => config('database.connections.mysql.port'),
            'database.connections.central.database' => 'rwsoft',
            'database.connections.central.username' => config('database.connections.mysql.username'),
            'database.connections.central.password' => config('database.connections.mysql.password'),
            'database.connections.tenant.driver' => 'mysql',
            'database.connections.tenant.host' => config('database.connections.mysql.host'),
            'database.connections.tenant.port' => config('database.connections.mysql.port'),
            'database.connections.tenant.database' => 'rwsoft_site_rwsoft',
            'database.connections.tenant.username' => config('database.connections.mysql.username'),
            'database.connections.tenant.password' => config('database.connections.mysql.password'),
        ]);

        DB::purge('central');
        DB::purge('tenant');
        DB::reconnect('central');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');
        DB::connection('central')->beginTransaction();
        DB::connection('tenant')->beginTransaction();

        $this->withoutMiddleware([
            AuthAdminUsers::class,
            AuthorizeAdminRoute::class,
            EnsureSiteMembership::class,
            EnsureTwoFactorIsEnabled::class,
            ResolveTenantSite::class,
        ]);

        CmsLanguage::query()->updateOrCreate(
            ['locale' => 'nl'],
            [
                'name' => 'Nederlands',
                'native_name' => 'Nederlands',
                'direction' => 'ltr',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    protected function tearDown(): void
    {
        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }

        TenantContext::clear();

        parent::tearDown();
    }

    public function test_layout_admin_pages_render_inertia_components(): void
    {
        $user = $this->createAdminUser();
        $layout = CmsLayout::query()->create([
            'name' => 'Basislayout',
            'locale' => 'nl',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);

        $this
            ->actingAs($user)
            ->get(route('admin.cms.layouts.index'), $this->inertiaHeaders('/admin/cms/layouts'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Layouts/Index')
            ->assertJsonFragment(['name' => 'Basislayout']);

        $this
            ->actingAs($user)
            ->get(route('admin.cms.layouts.edit', ['id' => $layout->id]), $this->inertiaHeaders('/admin/cms/layouts/'.$layout->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Layouts/Edit')
            ->assertJsonPath('props.layoutItem.id', $layout->id)
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->where('props.headSystemBlockPreviews.site_head_meta', fn (string $value): bool => str_contains($value, '<meta charset="utf-8">'))
                ->etc());
    }

    public function test_layout_can_be_stored_and_default_is_unique_per_locale(): void
    {
        $user = $this->createAdminUser();
        $oldDefault = CmsLayout::query()->create([
            'name' => 'Oude default',
            'locale' => 'nl',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('admin.cms.layouts.store', ['id' => 0]), [
                'name' => 'Nieuwe default',
                'locale' => 'nl',
                'is_default' => true,
                'is_active' => false,
                'cache_strategy' => 'block',
                'settings' => ['scroll_mode' => 'browser'],
            ])
            ->assertSessionHas('status');

        $newDefault = CmsLayout::query()->where('name', 'Nieuwe default')->firstOrFail();

        $response->assertRedirect(route('admin.cms.layouts.edit', ['id' => $newDefault->id]));

        $this->assertTrue((bool) $newDefault->is_default);
        $this->assertTrue((bool) $newDefault->is_active);
        $this->assertSame('block', $newDefault->cache_strategy);
        $this->assertFalse((bool) $oldDefault->fresh()->is_default);
    }

    public function test_page_can_be_assigned_to_detail_template(): void
    {
        $user = $this->createAdminUser();
        $layout = CmsLayout::query()->create([
            'name' => 'Pagina template layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $template = CmsTemplate::query()->create([
            'name' => 'Pagina detail template',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $slug = 'layout-pagina-'.uniqid();

        $response = $this
            ->actingAs($user)
            ->post(route('admin.cms.pages.store', ['id' => 0]), [
                'parent_id' => null,
                'detail_template_id' => $template->id,
                'title' => 'Layout pagina',
                'slug' => $slug,
                'locale' => 'nl',
                'status' => 'draft',
                'template' => null,
                'short_description' => null,
                'content_blocks' => [],
                'seo_title' => null,
                'seo_description' => null,
                'canonical_url' => null,
                'og_image_path' => null,
                'noindex' => false,
                'is_home' => false,
                'is_searchable' => true,
                'sort_order' => 0,
                'published_at' => null,
            ]);

        $page = CmsPage::query()->where('slug', $slug)->firstOrFail();

        $response
            ->assertRedirect(route('admin.cms.pages.edit', ['id' => $page->id]))
            ->assertSessionHas('status');

        $this->assertSame($template->id, $page->detail_template_id);
    }

    public function test_layout_store_persists_stacked_header_and_footer_blocks(): void
    {
        $user = $this->createAdminUser();
        $siteHeaderBlock = $this->publishedPlaceableBlock('site_header', ['header']);
        $footerTextBlock = $this->publishedPlaceableBlock('text', ['footer']);
        $layout = CmsLayout::query()->create([
            'name' => 'Stack layout',
            'locale' => 'nl',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.layouts.store', ['id' => $layout->id]), [
                'name' => 'Stack layout updated',
                'locale' => 'nl',
                'is_default' => true,
                'is_active' => true,
                'cache_strategy' => 'none',
                'settings' => ['scroll_mode' => 'browser'],
                'sections' => [
                    'header' => [[
                        'name' => 'Top header',
                        'settings' => ['scroll_behavior' => 'normal'],
                        'is_active' => true,
                        'visible_mobile' => true,
                        'visible_tablet' => true,
                        'visible_desktop' => true,
                        'placements' => [[
                            'is_active' => true,
                            'visible_mobile' => true,
                            'visible_tablet' => true,
                            'visible_desktop' => true,
                            'mobile_span' => 12,
                            'tablet_span' => 12,
                            'desktop_span' => 12,
                            'height_mode' => 'auto',
                            'cache_strategy' => 'inherit',
                            'block' => [
                                'cms_placeable_block_id' => $siteHeaderBlock->id,
                                'type' => 'site_header',
                            ],
                        ]],
                    ]],
                    'footer' => [[
                        'name' => 'Footer stack',
                        'settings' => ['scroll_behavior' => 'auto_hide'],
                        'is_active' => true,
                        'visible_mobile' => true,
                        'visible_tablet' => true,
                        'visible_desktop' => true,
                        'placements' => [
                            [
                                'is_active' => true,
                                'visible_mobile' => true,
                                'visible_tablet' => true,
                                'visible_desktop' => true,
                                'mobile_span' => 12,
                                'tablet_span' => 12,
                                'desktop_span' => 12,
                                'height_mode' => 'auto',
                                'cache_strategy' => 'inherit',
                                'block' => [
                                    'cms_placeable_block_id' => $footerTextBlock->id,
                                    'type' => 'text',
                                    'title' => 'Scroll footer',
                                    'text' => 'Scroll footer tekst',
                                ],
                            ],
                            [
                                'is_active' => true,
                                'visible_mobile' => true,
                                'visible_tablet' => true,
                                'visible_desktop' => true,
                                'mobile_span' => 12,
                                'tablet_span' => 12,
                                'desktop_span' => 12,
                                'height_mode' => 'auto',
                                'cache_strategy' => 'inherit',
                                'block' => [
                                    'cms_placeable_block_id' => $footerTextBlock->id,
                                    'type' => 'text',
                                    'title' => 'Sticky footer',
                                    'text' => 'Sticky footer tekst',
                                ],
                            ],
                        ],
                    ]],
                ],
            ])
            ->assertRedirect(route('admin.cms.layouts.edit', ['id' => $layout->id]))
            ->assertSessionHas('status');

        $layout->refresh();
        $footerSection = CmsSection::query()
            ->where('owner_type', CmsLayout::class)
            ->where('owner_id', $layout->id)
            ->where('zone', 'footer')
            ->firstOrFail();

        $this->assertSame('Stack layout updated', $layout->name);
        $this->assertSame(1, CmsSection::query()->where('owner_id', $layout->id)->where('zone', 'header')->where('is_active', true)->count());
        $this->assertSame(2, CmsBlockPlacement::query()->where('cms_section_id', $footerSection->id)->where('is_active', true)->count());
        $this->assertSame('normal', CmsSection::query()->where('owner_id', $layout->id)->where('zone', 'header')->firstOrFail()->settings['scroll_behavior']);
        $this->assertSame('auto_hide', $footerSection->settings['scroll_behavior']);
    }

    public function test_layout_store_persists_header_grid_layout_config(): void
    {
        $user = $this->createAdminUser();
        $siteLogoBlock = $this->publishedPlaceableBlock('site_logo', ['header']);
        $layout = CmsLayout::query()->create([
            'name' => 'Grid layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.layouts.store', ['id' => $layout->id]), [
                'name' => 'Grid layout',
                'locale' => 'nl',
                'is_default' => false,
                'is_active' => true,
                'cache_strategy' => 'none',
                'settings' => ['scroll_mode' => 'browser'],
                'sections' => [
                    'header' => [[
                        'name' => 'Grid header',
                        'is_active' => true,
                        'visible_mobile' => true,
                        'visible_tablet' => true,
                        'visible_desktop' => true,
                        'settings' => ['spacing' => 'none'],
                        'placements' => [[
                            'is_active' => true,
                            'visible_mobile' => true,
                            'visible_tablet' => true,
                            'visible_desktop' => true,
                            'mobile_span' => 12,
                            'tablet_span' => 6,
                            'desktop_span' => 4,
                            'layout_config' => [
                                'desktop' => ['x' => 2, 'y' => 1, 'w' => 4, 'h' => 2],
                                'tablet' => ['x' => 1, 'y' => 0, 'w' => 6, 'h' => 1],
                                'mobile' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 1],
                            ],
                            'height_mode' => 'auto',
                            'cache_strategy' => 'inherit',
                            'block' => [
                                'cms_placeable_block_id' => $siteLogoBlock->id,
                                'type' => 'site_logo',
                                'media_asset_id' => null,
                                'alt_text' => 'Grid logo',
                                'link_url' => '/',
                            ],
                        ]],
                    ]],
                ],
            ])
            ->assertRedirect(route('admin.cms.layouts.edit', ['id' => $layout->id]));

        $placement = CmsBlockPlacement::query()
            ->whereHas('section', fn ($query) => $query->where('owner_id', $layout->id)->where('zone', 'header'))
            ->firstOrFail();

        $this->assertSame(2, $placement->layout_config['desktop']['x']);
        $this->assertSame(1, $placement->layout_config['desktop']['y']);
        $this->assertSame(4, $placement->layout_config['desktop']['w']);
        $this->assertSame(2, $placement->layout_config['desktop']['h']);

        $this
            ->actingAs($user)
            ->get(route('admin.cms.layouts.edit', ['id' => $layout->id]), $this->inertiaHeaders('/admin/cms/layouts/'.$layout->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('props.layoutItem.sections.header.0.placements.0.layout_config.desktop.x', 2)
            ->assertJsonPath('props.layoutItem.sections.header.0.placements.0.layout_config.desktop.y', 1)
            ->assertJsonPath('props.layoutItem.sections.header.0.placements.0.layout_config.desktop.w', 4)
            ->assertJsonPath('props.layoutItem.sections.header.0.placements.0.block.cms_placeable_block_id', $siteLogoBlock->id);
    }

    public function test_layout_translation_does_not_copy_site_logo_alt_text_override(): void
    {
        $siteLogoBlock = $this->publishedPlaceableBlock('site_logo', ['header']);
        $sourceLayout = CmsLayout::query()->create([
            'name' => 'Nederlandse layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $section = $sourceLayout->sections()->create([
            'zone' => 'header',
            'name' => 'Header',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);
        $block = CmsBlock::query()->create([
            'cms_placeable_block_id' => $siteLogoBlock->id,
            'placeable_block_revision_id' => $siteLogoBlock->latestPublishedRevision?->id,
            'type' => 'site_logo',
            'name' => 'Logo',
            'content' => [
                'media_asset_id' => 123,
                'alt_text' => 'Nederlandse logo override',
                'link_url' => '/',
            ],
            'settings' => [],
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
        ]);
        $section->placements()->create([
            'cms_block_id' => $block->id,
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'height_mode' => 'auto',
            'height_value' => null,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);

        $translation = app(CreateCmsLayoutTranslationAction::class)->handle($sourceLayout, 'fr');
        $translatedBlock = $translation->sections()->firstOrFail()->placements()->firstOrFail()->block()->firstOrFail();

        $this->assertSame('site_logo', $translatedBlock->type);
        $this->assertArrayNotHasKey('alt_text', $translatedBlock->content ?? []);
        $this->assertSame(123, $translatedBlock->content['media_asset_id'] ?? null);
        $this->assertSame('/', $translatedBlock->content['link_url'] ?? null);
    }

    public function test_layout_store_persists_style_presets_and_developer_css_draft(): void
    {
        $user = $this->createAdminUser();
        $blockDefinition = $this->publishedPlaceableBlock('text', ['header']);
        $layout = CmsLayout::query()->create([
            'name' => 'Style preset layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.layouts.store', ['id' => $layout->id]), [
                'name' => 'Style preset layout',
                'locale' => 'nl',
                'is_default' => false,
                'is_active' => true,
                'cache_strategy' => 'none',
                'settings' => ['scroll_mode' => 'browser'],
                'sections' => [
                    'header' => [[
                        'name' => 'Styled header',
                        'is_active' => true,
                        'visible_mobile' => true,
                        'visible_tablet' => true,
                        'visible_desktop' => true,
                        'placements' => [[
                            'is_active' => true,
                            'visible_mobile' => true,
                            'visible_tablet' => true,
                            'visible_desktop' => true,
                            'mobile_span' => 12,
                            'tablet_span' => 12,
                            'desktop_span' => 12,
                            'height_mode' => 'auto',
                            'cache_strategy' => 'inherit',
                            'style_config' => [
                                'devices' => [
                                    'desktop' => [
                                        'appearance' => [
                                            'background_color' => '#f8fafc',
                                            'padding' => 'md',
                                            'radius' => 'lg',
                                            'border' => 'subtle',
                                            'shadow' => 'sm',
                                        ],
                                    ],
                                ],
                                'developer' => [
                                    'css_source' => '.cms-placement-draft { color: hotpink; }',
                                ],
                            ],
                            'block' => [
                                'cms_placeable_block_id' => $blockDefinition->id,
                                'type' => 'text',
                                'title' => 'Styled title',
                                'text' => 'Styled text',
                            ],
                        ]],
                    ]],
                ],
            ])
            ->assertRedirect(route('admin.cms.layouts.edit', ['id' => $layout->id]));

        $placement = CmsBlockPlacement::query()
            ->whereHas('section', fn ($query) => $query->where('owner_id', $layout->id)->where('zone', 'header'))
            ->firstOrFail();

        $this->assertSame('#f8fafc', $placement->style_config['devices']['desktop']['appearance']['background_color']);
        $this->assertSame('md', $placement->style_config['devices']['desktop']['appearance']['padding']);
        $this->assertSame('lg', $placement->style_config['devices']['desktop']['appearance']['radius']);
        $this->assertSame('subtle', $placement->style_config['devices']['desktop']['appearance']['border']);
        $this->assertSame('sm', $placement->style_config['devices']['desktop']['appearance']['shadow']);
        $this->assertSame('.cms-placement-draft { color: hotpink; }', $placement->style_config['developer']['css_source']);

        $this
            ->actingAs($user)
            ->get(route('admin.cms.layouts.edit', ['id' => $layout->id]), $this->inertiaHeaders('/admin/cms/layouts/'.$layout->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('props.layoutItem.sections.header.0.placements.0.style_config.devices.desktop.appearance.background_color', '#f8fafc')
            ->assertJsonPath('props.layoutItem.sections.header.0.placements.0.style_config.devices.desktop.appearance.padding', 'md')
            ->assertJsonPath('props.layoutItem.sections.header.0.placements.0.style_config.developer.css_source', '.cms-placement-draft { color: hotpink; }');
    }

    public function test_layout_style_revision_publish_links_published_revision(): void
    {
        $user = $this->createAdminUser();
        $layout = CmsLayout::query()->create([
            'name' => 'Style publish layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $section = $layout->sections()->create([
            'zone' => 'header',
            'name' => 'Publish header',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);
        $placement = $section->placements()->create([
            'cms_block_id' => CmsBlock::query()->create([
                'type' => 'text',
                'name' => 'Publish block',
                'content' => ['title' => 'Publish title', 'text' => 'Publish text'],
                'settings' => [],
                'is_shared' => false,
                'is_dynamic' => false,
                'cache_strategy' => 'none',
            ])->id,
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'style_config' => ['appearance' => ['background_color' => null]],
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);

        $this
            ->actingAs($user)
            ->from(route('admin.cms.layouts.edit', ['id' => $layout->id]))
            ->post(route('admin.cms.block-placements.style-revisions.publish', ['placement' => $placement->id]), [
                'css_source' => '.published-placement-css { color: green; }',
                'style_config' => [
                    'devices' => [
                        'desktop' => [
                            'appearance' => [
                                'background_color' => '#f8fafc',
                                'padding' => 'md',
                                'radius' => 'inherit',
                                'border' => 'none',
                                'shadow' => 'none',
                            ],
                        ],
                    ],
                    'developer' => [
                        'css_source' => '.published-placement-css { color: green; }',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.cms.layouts.edit', ['id' => $layout->id]))
            ->assertSessionHas('status');

        $placement->refresh();

        $this->assertNotNull($placement->published_style_revision_id);
        $this->assertSame('#f8fafc', $placement->style_config['devices']['desktop']['appearance']['background_color']);
        $this->assertSame('.published-placement-css { color: green; }', $placement->style_config['developer']['css_source']);
        $this->assertSame('.published-placement-css { color: green; }', $placement->publishedStyleRevision->css_source);
        $this->assertSame('published', $placement->publishedStyleRevision->status);
        $this->assertSame(1, $placement->publishedStyleRevision->revision_number);
    }

    public function test_layout_edit_payload_contains_style_revisions(): void
    {
        $user = $this->createAdminUser();
        $layout = CmsLayout::query()->create([
            'name' => 'Style revision payload layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $section = $layout->sections()->create([
            'zone' => 'header',
            'name' => 'Revision payload header',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);
        $placement = $section->placements()->create([
            'cms_block_id' => CmsBlock::query()->create([
                'type' => 'text',
                'name' => 'Revision payload block',
                'content' => ['title' => 'Revision payload title', 'text' => 'Revision payload text'],
                'settings' => [],
                'is_shared' => false,
                'is_dynamic' => false,
                'cache_strategy' => 'none',
            ])->id,
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'style_config' => [],
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $revision = $placement->styleRevisions()->create([
            'revision_number' => 1,
            'status' => 'published',
            'title' => 'Style revision 1',
            'style_config' => ['developer' => ['css_source' => '.payload-css { color: blue; }']],
            'css_source' => '.payload-css { color: blue; }',
            'snapshot_hash' => hash('sha256', 'payload-css'),
            'metadata' => [],
            'published_at' => now(),
        ]);
        $placement->forceFill(['published_style_revision_id' => $revision->id])->save();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.layouts.edit', ['id' => $layout->id]), $this->inertiaHeaders('/admin/cms/layouts/'.$layout->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('props.layoutItem.sections.header.0.placements.0.style_revisions.0.id', $revision->id)
            ->assertJsonPath('props.layoutItem.sections.header.0.placements.0.style_revisions.0.revision_number', 1)
            ->assertJsonPath('props.layoutItem.sections.header.0.placements.0.style_revisions.0.css_source', '.payload-css { color: blue; }')
            ->assertJsonPath('props.layoutItem.sections.header.0.placements.0.style_revisions.0.is_current', true);

        $this
            ->actingAs($user)
            ->getJson(route('admin.cms.block-placements.style-revisions.index', ['placement' => $placement->id]))
            ->assertOk()
            ->assertJsonPath('revisions.0.id', $revision->id)
            ->assertJsonPath('revisions.0.style_config.developer.css_source', '.payload-css { color: blue; }')
            ->assertJsonPath('revisions.0.css_source', '.payload-css { color: blue; }')
            ->assertJsonPath('revisions.0.is_current', true);
    }

    public function test_layout_style_revision_republish_links_existing_revision(): void
    {
        $user = $this->createAdminUser();
        $layout = CmsLayout::query()->create([
            'name' => 'Style republish layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $section = $layout->sections()->create([
            'zone' => 'header',
            'name' => 'Republish header',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);
        $placement = $section->placements()->create([
            'cms_block_id' => CmsBlock::query()->create([
                'type' => 'text',
                'name' => 'Republish block',
                'content' => ['title' => 'Republish title', 'text' => 'Republish text'],
                'settings' => [],
                'is_shared' => false,
                'is_dynamic' => false,
                'cache_strategy' => 'inherit',
            ])->id,
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'style_config' => [],
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $firstRevision = $placement->styleRevisions()->create([
            'revision_number' => 1,
            'status' => 'published',
            'title' => 'Style revision 1',
            'style_config' => ['developer' => ['css_source' => '.first-css { color: red; }']],
            'css_source' => '.first-css { color: red; }',
            'snapshot_hash' => hash('sha256', 'first-css'),
            'metadata' => [],
            'published_at' => now(),
        ]);
        $secondRevision = $placement->styleRevisions()->create([
            'revision_number' => 2,
            'status' => 'published',
            'title' => 'Style revision 2',
            'style_config' => ['developer' => ['css_source' => '.second-css { color: green; }']],
            'css_source' => '.second-css { color: green; }',
            'snapshot_hash' => hash('sha256', 'second-css'),
            'metadata' => [],
            'published_at' => now(),
        ]);
        $placement->forceFill(['published_style_revision_id' => $firstRevision->id])->save();

        $this
            ->actingAs($user)
            ->from(route('admin.cms.layouts.edit', ['id' => $layout->id]))
            ->post(route('admin.cms.block-placements.style-revisions.republish', [
                'placement' => $placement->id,
                'revision' => $secondRevision->id,
            ]))
            ->assertRedirect(route('admin.cms.layouts.edit', ['id' => $layout->id]))
            ->assertSessionHas('status');

        $placement->refresh();

        $this->assertSame($secondRevision->id, $placement->published_style_revision_id);
        $this->assertSame('.second-css { color: green; }', $placement->style_config['developer']['css_source']);
    }

    public function test_layout_style_revision_republish_rejects_other_placement_revision(): void
    {
        $user = $this->createAdminUser();
        $layout = CmsLayout::query()->create([
            'name' => 'Style republish safety layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $section = $layout->sections()->create([
            'zone' => 'header',
            'name' => 'Republish safety header',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);
        $block = CmsBlock::query()->create([
            'type' => 'text',
            'name' => 'Republish safety block',
            'content' => ['title' => 'Republish safety title', 'text' => 'Republish safety text'],
            'settings' => [],
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
        ]);
        $placement = $section->placements()->create([
            'cms_block_id' => $block->id,
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'style_config' => [],
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $otherPlacement = $section->placements()->create([
            'cms_block_id' => $block->id,
            'sort_order' => 1,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'style_config' => [],
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $otherRevision = $otherPlacement->styleRevisions()->create([
            'revision_number' => 1,
            'status' => 'published',
            'title' => 'Other style revision',
            'style_config' => ['developer' => ['css_source' => '.other-css { color: red; }']],
            'css_source' => '.other-css { color: red; }',
            'snapshot_hash' => hash('sha256', 'other-css'),
            'metadata' => [],
            'published_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.block-placements.style-revisions.republish', [
                'placement' => $placement->id,
                'revision' => $otherRevision->id,
            ]))
            ->assertNotFound();

        $this->assertNull($placement->fresh()->published_style_revision_id);
    }

    public function test_layout_style_revision_publish_rejects_closing_style_tag(): void
    {
        $user = $this->createAdminUser();
        $layout = CmsLayout::query()->create([
            'name' => 'Style safety layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $section = $layout->sections()->create([
            'zone' => 'header',
            'name' => 'Safety header',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);
        $placement = $section->placements()->create([
            'cms_block_id' => CmsBlock::query()->create([
                'type' => 'text',
                'name' => 'Safety block',
                'content' => ['title' => 'Safety title', 'text' => 'Safety text'],
                'settings' => [],
                'is_shared' => false,
                'is_dynamic' => false,
                'cache_strategy' => 'inherit',
            ])->id,
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'style_config' => [],
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);

        $this
            ->actingAs($user)
            ->from(route('admin.cms.layouts.edit', ['id' => $layout->id]))
            ->post(route('admin.cms.block-placements.style-revisions.publish', ['placement' => $placement->id]), [
                'css_source' => '.safe { color: green; }</style><script>alert(1)</script>',
                'style_config' => [],
            ])
            ->assertRedirect(route('admin.cms.layouts.edit', ['id' => $layout->id]))
            ->assertSessionHasErrors('css_source');

        $this->assertNull($placement->fresh()->published_style_revision_id);
        $this->assertSame(0, $placement->styleRevisions()->count());
    }

    public function test_layout_store_can_link_placeable_block_revision(): void
    {
        $user = $this->createAdminUser();
        $blockDefinition = CmsPlaceableBlock::query()->create([
            'key' => 'header_notice',
            'name' => 'Header notice',
            'category' => 'content',
            'source' => 'user',
            'status' => 'published',
            'allowed_zones' => ['header'],
            'rendering_mode' => 'safe_blade',
            'renderer_key' => 'text',
            'template_source' => '<aside class="cms-block-notice">{{ block.title }}</aside>',
            'css_source' => '.cms-block-notice { color: red; }',
            'schema' => ['fields' => ['title', 'text']],
            'defaults' => [],
            'capabilities' => [],
            'behavior_config' => [],
            'context_config' => [],
            'published_at' => now(),
        ]);
        $revision = $blockDefinition->revisions()->create([
            'revision_number' => 1,
            'status' => 'published',
            'title' => 'Header notice',
            'category' => 'content',
            'source' => 'user',
            'allowed_zones' => ['header'],
            'rendering_mode' => 'safe_blade',
            'renderer_key' => 'text',
            'template_source' => '<aside class="cms-block-notice">{{ block.title }}</aside>',
            'css_source' => '.cms-block-notice { color: red; }',
            'schema' => ['fields' => ['title', 'text']],
            'defaults' => [],
            'capabilities' => [],
            'behavior_config' => [],
            'context_config' => [],
            'snapshot_hash' => hash('sha256', 'header_notice'),
            'metadata' => [],
            'published_at' => now(),
        ]);
        $layout = CmsLayout::query()->create([
            'name' => 'Block layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.layouts.store', ['id' => $layout->id]), [
                'name' => 'Block layout',
                'locale' => 'nl',
                'is_default' => false,
                'is_active' => true,
                'cache_strategy' => 'none',
                'settings' => ['scroll_mode' => 'browser'],
                'sections' => [
                    'header' => [[
                        'name' => 'Variant header',
                        'settings' => ['scroll_behavior' => 'sticky'],
                        'is_active' => true,
                        'visible_mobile' => true,
                        'visible_tablet' => true,
                        'visible_desktop' => true,
                        'placements' => [[
                            'is_active' => true,
                            'visible_mobile' => true,
                            'visible_tablet' => true,
                            'visible_desktop' => true,
                            'mobile_span' => 12,
                            'tablet_span' => 12,
                            'desktop_span' => 12,
                            'height_mode' => 'auto',
                            'cache_strategy' => 'inherit',
                            'block' => [
                                'cms_placeable_block_id' => $blockDefinition->id,
                                'type' => 'text',
                                'title' => 'Originele titel',
                                'text' => 'Originele tekst',
                            ],
                        ]],
                    ]],
                ],
            ])
            ->assertRedirect(route('admin.cms.layouts.edit', ['id' => $layout->id]));

        $placement = CmsBlockPlacement::query()
            ->whereHas('section', fn ($query) => $query->where('owner_id', $layout->id)->where('zone', 'header'))
            ->with('block')
            ->firstOrFail();

        $this->assertSame($blockDefinition->id, $placement->block->cms_placeable_block_id);
        $this->assertSame($revision->id, $placement->block->placeable_block_revision_id);
        $this->assertSame('text', $placement->block->type);
    }

    public function test_layout_translation_copy_creates_inactive_deep_copy(): void
    {
        $user = $this->createAdminUser();
        CmsLanguage::query()->updateOrCreate(
            ['locale' => 'en'],
            [
                'name' => 'English',
                'native_name' => 'English',
                'direction' => 'ltr',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );
        $layout = CmsLayout::query()->create([
            'name' => 'Nederlandse layout',
            'locale' => 'nl',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $section = $layout->sections()->create([
            'zone' => 'footer',
            'name' => 'Footer sectie',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => ['scroll_behavior' => 'normal'],
        ]);
        $block = $section->placements()->create([
            'cms_block_id' => CmsBlock::query()->create([
                'type' => 'text',
                'name' => 'Footer tekst',
                'content' => ['title' => 'Contact', 'text' => 'Neem contact op.'],
                'settings' => [],
                'is_shared' => false,
                'is_dynamic' => false,
                'cache_strategy' => 'none',
            ])->id,
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
        ])->block;

        $this
            ->actingAs($user)
            ->post(route('admin.cms.layouts.translations.store', ['id' => $layout->id]), [
                'target_locale' => 'en',
                'use_ai' => false,
            ])
            ->assertRedirect();

        $translation = CmsLayout::query()
            ->where('locale', 'en')
            ->where('translation_key', $layout->refresh()->translation_key)
            ->firstOrFail();
        $translatedSection = CmsSection::query()->where('owner_id', $translation->id)->where('zone', 'footer')->firstOrFail();
        $translatedPlacement = CmsBlockPlacement::query()->where('cms_section_id', $translatedSection->id)->firstOrFail();

        $this->assertSame($layout->refresh()->translation_key, $translation->translation_key);
        $this->assertSame($layout->id, $translation->translated_from_layout_id);
        $this->assertFalse((bool) $translation->is_active);
        $this->assertFalse((bool) $translation->is_default);
        $this->assertNotSame($block->id, $translatedPlacement->cms_block_id);
        $this->assertSame('Contact', $translatedPlacement->block->content['title']);
        $this->assertSame('Neem contact op.', $translatedPlacement->block->content['text']);
        $this->assertSame('normal', $translatedSection->settings['scroll_behavior']);
    }

    public function test_layout_section_sync_preserves_omitted_code_zones(): void
    {
        $layout = CmsLayout::query()->create([
            'name' => 'Code zone layout',
            'locale' => 'nl',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $headSection = $layout->sections()->create([
            'zone' => 'head',
            'name' => 'Head snippets',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);
        $headPlacement = $headSection->placements()->create([
            'cms_block_id' => CmsBlock::query()->create([
                'type' => 'custom_head_code',
                'name' => 'Analytics',
                'content' => ['code' => '<script>window.analytics = true;</script>'],
                'settings' => [],
                'is_shared' => false,
                'is_dynamic' => false,
                'cache_strategy' => 'none',
            ])->id,
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => ['width_mode' => 'content'],
        ]);

        app(SaveCmsLayoutSectionsAction::class)->handle($layout, [
            'header' => [],
            'footer' => [],
        ]);

        $this->assertTrue((bool) $headSection->fresh()->is_active);
        $this->assertTrue((bool) $headPlacement->fresh()->is_active);
        $this->assertSame('custom_head_code', $headPlacement->fresh()->block->type);
    }

    public function test_layout_store_persists_ordered_head_stack(): void
    {
        $user = $this->createAdminUser();
        $layout = CmsLayout::query()->create([
            'name' => 'Head stack layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.layouts.store', ['id' => $layout->id]), [
                'name' => 'Head stack layout',
                'locale' => 'nl',
                'is_default' => false,
                'is_active' => true,
                'cache_strategy' => 'none',
                'settings' => ['scroll_mode' => 'browser'],
                'sections' => [
                    'head' => [[
                        'name' => 'Head',
                        'is_active' => true,
                        'visible_mobile' => true,
                        'visible_tablet' => true,
                        'visible_desktop' => true,
                        'settings' => ['spacing' => 'none'],
                        'placements' => [
                            $this->layoutPlacementPayload(['type' => 'site_head_meta']),
                            $this->layoutPlacementPayload([
                                'type' => 'custom_head_code',
                                'code' => '<meta name="custom-one" content="1">',
                            ]),
                            $this->layoutPlacementPayload(['type' => 'site_head_favicons']),
                            $this->layoutPlacementPayload(['type' => 'site_head_system_assets']),
                            $this->layoutPlacementPayload([
                                'type' => 'custom_head_code',
                                'code' => '<meta name="custom-two" content="2">',
                            ]),
                            $this->layoutPlacementPayload(['type' => 'site_head_theme']),
                        ],
                    ]],
                ],
            ])
            ->assertRedirect(route('admin.cms.layouts.edit', ['id' => $layout->id]));

        $headSection = $layout->sections()->where('zone', 'head')->firstOrFail();
        $types = $headSection->placements()->with('block')->orderBy('sort_order')->get()->map(
            fn (CmsBlockPlacement $placement): string => $placement->block->type
        )->all();

        $this->assertSame([
            'site_head_meta',
            'custom_head_code',
            'site_head_favicons',
            'site_head_system_assets',
            'custom_head_code',
            'site_head_theme',
        ], $types);
    }

    public function test_layout_store_rejects_incomplete_head_stack(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->from(route('admin.cms.layouts.index'))
            ->post(route('admin.cms.layouts.store', ['id' => 0]), [
                'name' => 'Incomplete head stack',
                'locale' => 'nl',
                'is_default' => false,
                'is_active' => true,
                'cache_strategy' => 'none',
                'settings' => ['scroll_mode' => 'browser'],
                'sections' => [
                    'head' => [[
                        'name' => 'Head',
                        'is_active' => true,
                        'visible_mobile' => true,
                        'visible_tablet' => true,
                        'visible_desktop' => true,
                        'settings' => ['spacing' => 'none'],
                        'placements' => [
                            $this->layoutPlacementPayload(['type' => 'site_head_meta']),
                        ],
                    ]],
                ],
            ])
            ->assertRedirect(route('admin.cms.layouts.index'))
            ->assertSessionHasErrors('sections.head');
    }

    public function test_generic_section_sync_saves_page_content_sections(): void
    {
        $textBlock = $this->publishedPlaceableBlock('text', ['content']);
        $logoStripBlock = $this->publishedPlaceableBlock('logo_strip', ['content']);
        $page = CmsPage::query()->create([
            'title' => 'Section pagina',
            'slug' => 'section-pagina',
            'locale' => 'nl',
            'status' => 'draft',
            'content_blocks' => [],
            'settings' => [],
        ]);

        app(SaveCmsSectionsAction::class)->handle($page, [
            'content' => [[
                'name' => 'Intro',
                'is_active' => true,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'settings' => [
                    'layout_type' => 'hero',
                    'width_mode' => 'display',
                    'spacing' => 'spacious',
                    'background' => [
                        'color' => '#2563eb',
                    ],
                ],
                'placements' => [[
                    'is_active' => true,
                    'visible_mobile' => true,
                    'visible_tablet' => true,
                    'visible_desktop' => true,
                    'mobile_span' => 12,
                    'tablet_span' => 12,
                    'desktop_span' => 11,
                    'height_mode' => 'auto',
                    'cache_strategy' => 'inherit',
                    'settings' => ['width_mode' => 'display'],
                    'block' => [
                        'cms_placeable_block_id' => $textBlock->id,
                        'type' => 'text',
                        'title' => 'Intro titel',
                        'text' => 'Intro tekst',
                    ],
                ], [
                    'is_active' => true,
                    'visible_mobile' => true,
                    'visible_tablet' => true,
                    'visible_desktop' => true,
                    'mobile_span' => 12,
                    'tablet_span' => 12,
                    'desktop_span' => 12,
                    'height_mode' => 'auto',
                    'cache_strategy' => 'inherit',
                    'settings' => ['width_mode' => 'content'],
                    'block' => [
                        'cms_placeable_block_id' => $logoStripBlock->id,
                        'type' => 'logo_strip',
                        'title' => "Partnerlogo's",
                        'media_asset_ids' => [12, '13', 'ongeldig', 0],
                    ],
                ]],
            ]],
        ], ['content']);

        $section = $page->sections()->where('zone', 'content')->firstOrFail();
        $placements = $section->placements()->with('block')->orderBy('sort_order')->get();
        $placement = $placements->first();
        $logoPlacement = $placements->last();

        $this->assertSame('Intro', $section->name);
        $this->assertSame('hero', $section->settings['layout_type']);
        $this->assertSame('display', $section->settings['width_mode']);
        $this->assertSame('spacious', $section->settings['spacing']);
        $this->assertSame('#2563eb', $section->settings['background']['color']);
        $this->assertArrayNotHasKey('width_mode', $placement->settings);
        $this->assertSame(11, (int) $placement->desktop_span);
        $this->assertSame('text', $placement->block->type);
        $this->assertSame('Intro titel', $placement->block->content['title']);
        $this->assertSame('Intro tekst', $placement->block->content['text']);
        $this->assertSame('logo_strip', $logoPlacement->block->type);
        $this->assertSame('Partnerlogo\'s', $logoPlacement->block->content['title']);
        $this->assertSame([12, 13], $logoPlacement->block->content['media_asset_ids']);
    }

    public function test_generic_section_sync_saves_nested_slot_placements(): void
    {
        if (! Schema::connection('tenant')->hasColumn('cms_block_placements', 'parent_placement_id')) {
            $this->markTestSkipped('Nested slot placement columns are not migrated in this tenant database yet.');
        }

        $featureCardBlock = $this->publishedPlaceableBlock('feature_card', ['content']);
        $buttonBlock = $this->publishedPlaceableBlock('button', ['content']);
        $slotSchema = [[
            'key' => 'actions',
            'label' => 'Actions',
            'allowed_block_keys' => ['button'],
            'min_items' => 0,
            'max_items' => 2,
            'layout' => 'inline',
            'responsive' => 'wrap_mobile',
        ]];

        $featureCardBlock->forceFill([
            'schema' => array_merge($featureCardBlock->schema, ['slots' => $slotSchema]),
        ])->save();
        $featureCardBlock->revisions()->where('status', 'published')->update([
            'schema' => array_merge($featureCardBlock->schema, ['slots' => $slotSchema]),
        ]);

        $page = CmsPage::query()->create([
            'title' => 'Nested slots page',
            'slug' => 'nested-slots-page',
            'locale' => 'nl',
            'status' => 'draft',
            'content_blocks' => [],
            'settings' => [],
        ]);

        app(SaveCmsSectionsAction::class)->handle($page, [
            'content' => [[
                'name' => 'Cards',
                'is_active' => true,
                'settings' => [],
                'placements' => [[
                    'is_active' => true,
                    'settings' => [],
                    'block' => [
                        'cms_placeable_block_id' => $featureCardBlock->id,
                        'type' => 'feature_card',
                        'title' => 'Feature title',
                        'text' => 'Feature body',
                    ],
                    'slots' => [
                        'actions' => [
                            'placements' => [[
                                'is_active' => true,
                                'settings' => [],
                                'block' => [
                                    'cms_placeable_block_id' => $buttonBlock->id,
                                    'type' => 'button',
                                    'label' => 'Read more',
                                    'url' => '/read-more',
                                ],
                            ]],
                        ],
                    ],
                ]],
            ]],
        ], ['content']);

        $section = $page->sections()->where('zone', 'content')->firstOrFail();
        $parentPlacement = CmsBlockPlacement::query()
            ->where('cms_section_id', $section->id)
            ->whereNull('parent_placement_id')
            ->firstOrFail();
        $childPlacement = CmsBlockPlacement::query()
            ->with('block')
            ->where('parent_placement_id', $parentPlacement->id)
            ->where('slot_key', 'actions')
            ->firstOrFail();

        $this->assertSame('feature_card', $parentPlacement->block->type);
        $this->assertNull($childPlacement->cms_section_id);
        $this->assertSame('button', $childPlacement->block->type);
        $this->assertSame('Read more', $childPlacement->block->content['label']);
        $this->assertSame('/read-more', $childPlacement->block->content['url']);
    }

    public function test_layout_editor_returns_translation_status_payload(): void
    {
        $user = $this->createAdminUser();
        CmsLanguage::query()->updateOrCreate(
            ['locale' => 'en'],
            [
                'name' => 'English',
                'native_name' => 'English',
                'direction' => 'ltr',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );
        $translationKey = 'layout-test-'.uniqid();
        $layout = CmsLayout::query()->create([
            'name' => 'Basis layout',
            'locale' => 'nl',
            'translation_key' => $translationKey,
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        CmsLayout::query()->create([
            'name' => 'English layout',
            'locale' => 'en',
            'translation_key' => $translationKey,
            'translated_from_layout_id' => $layout->id,
            'is_default' => false,
            'is_active' => false,
            'cache_strategy' => 'inherit',
        ]);

        $this
            ->actingAs($user)
            ->get(route('admin.cms.layouts.edit', ['id' => $layout->id]), $this->inertiaHeaders('/admin/cms/layouts/'.$layout->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('props.translations.0.locale', 'en')
            ->assertJsonPath('props.translations.1.locale', 'nl')
            ->assertJsonPath('props.missingLanguages.0.locale', 'fr');
    }

    public function test_existing_layout_locale_cannot_be_changed_by_payload(): void
    {
        $user = $this->createAdminUser();
        $translationKey = 'layout-duplicate-'.uniqid();
        CmsLayout::query()->create([
            'name' => 'Nederlandse layout',
            'locale' => 'nl',
            'translation_key' => $translationKey,
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $englishLayout = CmsLayout::query()->create([
            'name' => 'English layout',
            'locale' => 'en',
            'translation_key' => $translationKey,
            'is_default' => false,
            'is_active' => false,
            'cache_strategy' => 'inherit',
        ]);

        $this
            ->actingAs($user)
            ->from(route('admin.cms.layouts.edit', ['id' => $englishLayout->id]))
            ->post(route('admin.cms.layouts.store', ['id' => $englishLayout->id]), [
                'name' => 'English layout edited',
                'locale' => 'nl',
                'is_default' => false,
                'is_active' => true,
                'cache_strategy' => 'none',
                'settings' => ['scroll_mode' => 'browser'],
                'sections' => [],
            ])
            ->assertRedirect(route('admin.cms.layouts.edit', ['id' => $englishLayout->id]))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $englishLayout->refresh();

        $this->assertSame('English layout edited', $englishLayout->name);
        $this->assertSame('en', $englishLayout->locale);
        $this->assertFalse((bool) $englishLayout->is_default);
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function layoutPlacementPayload(array $block): array
    {
        if (! array_key_exists('cms_placeable_block_id', $block)) {
            $type = (string) ($block['type'] ?? 'text');
            $block['cms_placeable_block_id'] = $this->publishedPlaceableBlock($type, ['head'])->id;
        }

        return [
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
            'block' => $block,
        ];
    }

    /**
     * @param  array<int, string>  $allowedZones
     */
    private function publishedPlaceableBlock(string $rendererKey, array $allowedZones): CmsPlaceableBlock
    {
        $definition = (array) config("cms_blocks.types.{$rendererKey}", []);
        $fields = array_values(array_filter(
            (array) ($definition['fields'] ?? []),
            fn (mixed $field): bool => is_string($field) && $field !== ''
        ));
        $schema = ['fields' => $fields];
        $renderingMode = (string) ($definition['rendering_mode'] ?? 'safe_blade');
        $templateSource = $renderingMode === 'safe_blade'
            ? (string) ($definition['safe_blade_template'] ?? '<div>{{ block.title }}</div>')
            : null;

        $block = CmsPlaceableBlock::query()->updateOrCreate(
            ['key' => $rendererKey],
            [
                'name' => ucwords(str_replace('_', ' ', $rendererKey)),
                'category' => (string) ($definition['category'] ?? 'content'),
                'source' => 'system',
                'status' => 'published',
                'allowed_zones' => $allowedZones,
                'rendering_mode' => $renderingMode,
                'renderer_key' => $rendererKey,
                'template_source' => $templateSource,
                'css_source' => null,
                'schema' => $schema,
                'defaults' => is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [],
                'capabilities' => [],
                'behavior_config' => [],
                'context_config' => [],
                'published_at' => now(),
            ]
        );

        $block->revisions()->updateOrCreate(
            ['revision_number' => 1],
            [
                'status' => 'published',
                'title' => $block->name,
                'category' => $block->category,
                'source' => $block->source,
                'allowed_zones' => $allowedZones,
                'rendering_mode' => $renderingMode,
                'renderer_key' => $rendererKey,
                'template_source' => $templateSource,
                'css_source' => null,
                'schema' => $schema,
                'defaults' => is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [],
                'capabilities' => [],
                'behavior_config' => [],
                'context_config' => [],
                'snapshot_hash' => hash('sha256', $rendererKey),
                'metadata' => [],
                'published_at' => now(),
            ]
        );

        return $block->refresh();
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(string $path): array
    {
        $request = Request::create($path, 'GET');
        $version = app(HandleInertiaRequests::class)->version($request);

        return [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) ($version ?? ''),
        ];
    }

    private function createAdminUser(): User
    {
        return User::factory()->create([
            'is_platform_admin' => true,
            'two_factor_secret' => encrypt('cms-layout-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
