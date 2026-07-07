<?php

namespace Tests\Feature\Cms;

use App\Http\Middleware\AuthAdminUsers;
use App\Http\Middleware\AuthorizeAdminRoute;
use App\Http\Middleware\EnsureSiteMembership;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenantSite;
use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormSubmission;
use App\Models\Cms\CmsFormSubmissionValue;
use App\Models\Cms\CmsLanguage;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsTemplate;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\CmsFreelancerDemoSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsBackofficeTest extends TestCase
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

    public function test_cms_dashboard_and_indexes_render_inertia_pages(): void
    {
        $user = $this->createAdminUser();
        $pageCount = CmsPage::query()->count();
        $postCount = CmsPost::query()->count();
        $mediaCount = CmsMediaAsset::query()->count();
        $formCount = CmsForm::query()->count();
        $activeFormCount = CmsForm::query()->where('is_active', true)->count();
        $submissionCount = CmsFormSubmission::query()->count();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.dashboard'), $this->inertiaHeaders('/admin/cms'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Dashboard')
            ->assertJsonPath('props.stats.pages', $pageCount)
            ->assertJsonPath('props.stats.posts', $postCount)
            ->assertJsonPath('props.stats.media_assets', $mediaCount)
            ->assertJsonPath('props.stats.forms', $formCount)
            ->assertJsonPath('props.stats.active_forms', $activeFormCount)
            ->assertJsonPath('props.stats.form_submissions', $submissionCount);

        $this
            ->actingAs($user)
            ->get(route('admin.cms.pages.index'), $this->inertiaHeaders('/admin/cms/pages'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Pages/Index');

        $this
            ->actingAs($user)
            ->get(route('admin.cms.posts.index'), $this->inertiaHeaders('/admin/cms/posts'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Posts/Index');
    }

    public function test_page_and_post_can_be_stored_with_validated_payload(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.pages.store', ['id' => 0]), $this->pagePayload([
                'title' => 'Test pagina',
                'slug' => 'test-pagina',
                'is_home' => true,
                'content_blocks' => [['type' => 'text', 'text' => 'Welkom']],
            ]))
            ->assertRedirect(route('admin.cms.pages.index'))
            ->assertSessionHas('status');

        $page = CmsPage::query()->where('slug', 'test-pagina')->first();

        $this->assertNotNull($page);
        $this->assertSame($user->id, $page?->author_id);
        $this->assertTrue((bool) $page?->is_home);
        $this->assertEquals([['type' => 'text', 'text' => 'Welkom', 'width_mode' => 'content']], $page?->content_blocks);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.posts.store', ['id' => 0]), $this->postPayload([
                'title' => 'Test bericht',
                'slug' => 'test-bericht',
                'is_featured' => true,
                'content_blocks' => [['type' => 'text', 'text' => 'Nieuws']],
            ]))
            ->assertRedirect(route('admin.cms.posts.index'))
            ->assertSessionHas('status');

        $post = CmsPost::query()->where('slug', 'test-bericht')->first();

        $this->assertNotNull($post);
        $this->assertSame($user->id, $post?->author_id);
        $this->assertTrue((bool) $post?->is_featured);
        $this->assertEquals([['type' => 'text', 'text' => 'Nieuws', 'width_mode' => 'content']], $post?->content_blocks);
    }

    public function test_page_edit_payload_contains_placement_style_revisions(): void
    {
        $user = $this->createAdminUser();
        $page = CmsPage::query()->create([
            'title' => 'Style page',
            'slug' => 'style-page-'.uniqid(),
            'locale' => 'nl',
            'detail_template_id' => $this->pageDetailTemplateId(),
            'translation_key' => (string) Str::ulid(),
            'status' => 'draft',
            'is_searchable' => true,
            'sort_order' => 0,
        ]);
        $section = $page->sections()->create([
            'zone' => 'content',
            'name' => 'Content',
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
                'name' => 'Style page block',
                'content' => ['title' => 'Style page block', 'text' => 'Style page content'],
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
            'style_config' => ['developer' => ['css_source' => '.page-draft-css { color: red; }']],
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $revision = $placement->styleRevisions()->create([
            'revision_number' => 1,
            'status' => 'published',
            'title' => 'Page placement style revision',
            'style_config' => ['developer' => ['css_source' => '.page-published-css { color: green; }']],
            'css_source' => '.page-published-css { color: green; }',
            'snapshot_hash' => hash('sha256', 'page-published-css'),
            'metadata' => [],
            'published_at' => now(),
        ]);
        $placement->forceFill(['published_style_revision_id' => $revision->id])->save();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.pages.edit', ['id' => $page->id]), $this->inertiaHeaders('/admin/cms/pages/'.$page->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('props.canManageCodeBlocks', true)
            ->assertJsonPath('props.pageItem.sections.content.0.placements.0.published_style_revision.id', $revision->id)
            ->assertJsonPath('props.pageItem.sections.content.0.placements.0.style_revisions.0.id', $revision->id)
            ->assertJsonPath('props.pageItem.sections.content.0.placements.0.style_revisions.0.is_current', true);
    }

    public function test_template_edit_payload_contains_placement_style_revisions(): void
    {
        $user = $this->createAdminUser();
        $layout = CmsLayout::query()->create([
            'name' => 'Template style layout',
            'locale' => 'nl',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $template = CmsTemplate::query()->create([
            'name' => 'Template style payload',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $section = $template->sections()->create([
            'zone' => 'content',
            'name' => 'Template content',
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
                'name' => 'Template style block',
                'content' => ['title' => 'Template style block', 'text' => 'Template style content'],
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
            'style_config' => ['developer' => ['css_source' => '.template-draft-css { color: red; }']],
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $revision = $placement->styleRevisions()->create([
            'revision_number' => 1,
            'status' => 'published',
            'title' => 'Template placement style revision',
            'style_config' => ['developer' => ['css_source' => '.template-published-css { color: green; }']],
            'css_source' => '.template-published-css { color: green; }',
            'snapshot_hash' => hash('sha256', 'template-published-css'),
            'metadata' => [],
            'published_at' => now(),
        ]);
        $placement->forceFill(['published_style_revision_id' => $revision->id])->save();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.templates.edit', ['id' => $template->id]), $this->inertiaHeaders('/admin/cms/templates/'.$template->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('props.canManageCodeBlocks', true)
            ->assertJsonPath('props.templateItem.sections.content.0.placements.0.published_style_revision.id', $revision->id)
            ->assertJsonPath('props.templateItem.sections.content.0.placements.0.style_revisions.0.id', $revision->id)
            ->assertJsonPath('props.templateItem.sections.content.0.placements.0.style_revisions.0.is_current', true);
    }

    public function test_existing_template_locale_and_type_cannot_be_changed_by_payload(): void
    {
        $user = $this->createAdminUser();
        $layout = CmsLayout::query()->create([
            'name' => 'Immutable template layout',
            'locale' => 'nl',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $template = CmsTemplate::query()->create([
            'name' => 'Immutable template',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.templates.store', ['id' => $template->id]), [
                'name' => 'Immutable template edited',
                'locale' => 'en',
                'layout_id' => $layout->id,
                'template_class' => 'blog',
                'template_key' => 'blog.detail',
                'is_default' => false,
                'is_active' => true,
                'cache_strategy' => 'inherit',
                'settings' => [],
                'sections' => [],
            ])
            ->assertRedirect(route('admin.cms.templates.edit', ['id' => $template->id]))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $template->refresh();

        $this->assertSame('Immutable template edited', $template->name);
        $this->assertSame('nl', $template->locale);
        $this->assertSame('page', $template->template_class);
        $this->assertSame('page.detail', $template->template_key);
    }

    public function test_template_content_grid_layout_config_is_persisted(): void
    {
        $user = $this->createAdminUser();
        $layout = CmsLayout::query()->create([
            'name' => 'Grid template layout',
            'locale' => 'nl',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $template = CmsTemplate::query()->create([
            'name' => 'Grid template',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $blockDefinition = $this->publishedPlaceableBlock('text', ['content']);
        $layoutConfig = [
            'desktop' => ['x' => 2, 'y' => 0, 'w' => 8, 'h' => 2],
            'tablet' => ['x' => 1, 'y' => 1, 'w' => 10, 'h' => 2],
            'mobile' => ['x' => 0, 'y' => 2, 'w' => 12, 'h' => 3],
        ];

        $this
            ->actingAs($user)
            ->post(route('admin.cms.templates.store', ['id' => $template->id]), [
                'name' => 'Grid template edited',
                'locale' => 'nl',
                'layout_id' => $layout->id,
                'template_class' => 'page',
                'template_key' => 'page.detail',
                'is_default' => false,
                'is_active' => true,
                'cache_strategy' => 'inherit',
                'settings' => [],
                'sections' => [
                    'content' => [[
                        'name' => 'Template content grid',
                        'settings' => [
                            'layout_type' => 'grid',
                            'width_mode' => 'display',
                        ],
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
                            'tablet_span' => 10,
                            'desktop_span' => 8,
                            'layout_config' => $layoutConfig,
                            'height_mode' => 'auto',
                            'cache_strategy' => 'inherit',
                            'settings' => [],
                            'block' => [
                                'cms_placeable_block_id' => $blockDefinition->id,
                                'type' => 'text',
                                'title' => 'Grid block',
                                'text' => 'Grid content',
                            ],
                        ]],
                    ]],
                ],
            ])
            ->assertRedirect(route('admin.cms.templates.edit', ['id' => $template->id]))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $placement = CmsBlockPlacement::query()
            ->whereHas('section', fn ($query) => $query
                ->where('owner_type', $template->getMorphClass())
                ->where('owner_id', $template->id)
                ->where('zone', 'content'))
            ->firstOrFail();

        $this->assertEquals($layoutConfig, $placement->layout_config);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.cms.templates.edit', ['id' => $template->id]), $this->inertiaHeaders('/admin/cms/templates/'.$template->id.'/edit'))
            ->assertOk();

        $this->assertEquals(
            $layoutConfig,
            data_get($response->json(), 'props.templateItem.sections.content.0.placements.0.layout_config')
        );
    }

    public function test_category_edit_exposes_template_defaults_and_preview_urls(): void
    {
        $user = $this->createAdminUser();
        $layout = CmsLayout::query()->create([
            'name' => 'Categorie layout',
            'locale' => 'nl',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $archiveTemplate = CmsTemplate::query()->create([
            'name' => 'Default category archive',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'category',
            'template_key' => 'category.archive',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $detailTemplate = CmsTemplate::query()->create([
            'name' => 'Default category detail',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'category',
            'template_key' => 'category.detail',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $category = CmsCategory::query()->create([
            'type' => 'post',
            'title' => 'Inzichten',
            'slug' => 'inzichten',
            'locale' => 'nl',
            'translation_key' => (string) Str::ulid(),
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->get(route('admin.cms.categories.edit', ['id' => $category->id]), $this->inertiaHeaders('/admin/cms/categories/'.$category->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Categories/Edit')
            ->assertJsonPath('props.category.preview_archive_url', url('/blogs/categories/inzichten'))
            ->assertJsonPath('props.category.preview_detail_url', url('/blogs/categories/inzichten/info'))
            ->assertJsonPath('props.archiveTemplateOptions.0.id', $archiveTemplate->id)
            ->assertJsonPath('props.archiveTemplateOptions.0.is_default', true)
            ->assertJsonPath('props.detailTemplateOptions.0.id', $detailTemplate->id)
            ->assertJsonPath('props.detailTemplateOptions.0.is_default', true);
    }

    public function test_page_and_post_slugs_are_unique_per_locale(): void
    {
        $user = $this->createAdminUser();

        CmsPage::query()->create($this->pagePayload([
            'title' => 'Bestaande pagina',
            'slug' => 'dubbele-slug',
            'author_id' => $user->id,
        ]));
        CmsPost::query()->create($this->postPayload([
            'title' => 'Bestaand bericht',
            'slug' => 'dubbele-slug',
            'author_id' => $user->id,
        ]));

        $this
            ->actingAs($user)
            ->from(route('admin.cms.pages.create'))
            ->post(route('admin.cms.pages.store', ['id' => 0]), $this->pagePayload([
                'title' => 'Dubbele pagina',
                'slug' => 'dubbele-slug',
            ]))
            ->assertRedirect(route('admin.cms.pages.create'))
            ->assertSessionHasErrors(['slug']);

        $this
            ->actingAs($user)
            ->from(route('admin.cms.posts.create'))
            ->post(route('admin.cms.posts.store', ['id' => 0]), $this->postPayload([
                'title' => 'Dubbel bericht',
                'slug' => 'dubbele-slug',
            ]))
            ->assertRedirect(route('admin.cms.posts.create'))
            ->assertSessionHasErrors(['slug']);

        $response = $this
            ->actingAs($user)
            ->post(route('admin.cms.pages.store', ['id' => 0]), $this->pagePayload([
                'title' => 'Franse pagina',
                'slug' => 'dubbele-slug',
                'locale' => 'fr',
            ]));

        $page = CmsPage::query()
            ->where('slug', 'dubbele-slug')
            ->where('locale', 'fr')
            ->firstOrFail();

        $response
            ->assertRedirect(route('admin.cms.pages.edit', ['id' => $page->id]))
            ->assertSessionDoesntHaveErrors(['slug']);
    }

    public function test_page_parent_cannot_be_self_or_descendant(): void
    {
        $user = $this->createAdminUser();
        $parent = CmsPage::query()->create($this->pagePayload([
            'title' => 'Parent',
            'slug' => 'parent',
            'author_id' => $user->id,
        ]));
        $child = CmsPage::query()->create($this->pagePayload([
            'title' => 'Child',
            'slug' => 'child',
            'parent_id' => $parent->id,
            'author_id' => $user->id,
        ]));

        $this
            ->actingAs($user)
            ->from(route('admin.cms.pages.edit', ['id' => $parent->id]))
            ->post(route('admin.cms.pages.store', ['id' => $parent->id]), $this->pagePayload([
                'title' => 'Parent',
                'slug' => 'parent',
                'parent_id' => $child->id,
            ]))
            ->assertRedirect(route('admin.cms.pages.edit', ['id' => $parent->id]))
            ->assertSessionHasErrors(['parent_id']);
    }

    public function test_content_blocks_are_validated_and_normalized(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->from(route('admin.cms.pages.create'))
            ->post(route('admin.cms.pages.store', ['id' => 0]), $this->pagePayload([
                'title' => 'Ongeldige knop',
                'slug' => 'ongeldige-knop',
                'content_blocks' => [
                    ['type' => 'button', 'label' => 'Klik', 'url' => 'javascript:alert(1)'],
                ],
            ]))
            ->assertRedirect(route('admin.cms.pages.create'))
            ->assertSessionHasErrors(['content_blocks.0.url']);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.posts.store', ['id' => 0]), $this->postPayload([
                'title' => 'Genormaliseerd bericht',
                'slug' => 'genormaliseerd-bericht',
                'content_blocks' => [
                    [
                        'type' => 'tabs',
                        'items' => [
                            ['title' => 'Eerste tab', 'text' => 'Eerste inhoud'],
                            ['title' => 'Tweede tab', 'text' => 'Tweede inhoud', 'unknown' => 'wordt niet opgeslagen'],
                            ['title' => '', 'text' => ''],
                        ],
                        'unknown' => 'wordt niet opgeslagen',
                    ],
                    [
                        'type' => 'carousel',
                        'items' => [
                            ['title' => 'Eerste slide', 'text' => 'Eerste inhoud'],
                            ['title' => 'Tweede slide', 'text' => 'Tweede inhoud'],
                        ],
                        'previous_label' => 'Vorige',
                        'next_label' => 'Volgende',
                        'unknown' => 'wordt niet opgeslagen',
                    ],
                    [
                        'type' => 'faq',
                        'items' => [
                            ['question' => 'Vraag?', 'answer' => 'Antwoord', 'title' => 'wordt niet opgeslagen'],
                            ['question' => '', 'answer' => ''],
                        ],
                    ],
                ],
            ]))
            ->assertRedirect(route('admin.cms.posts.index'))
            ->assertSessionDoesntHaveErrors(['content_blocks']);

        $post = CmsPost::query()->where('slug', 'genormaliseerd-bericht')->first();

        $this->assertEquals([
            [
                'type' => 'tabs',
                'items' => [
                    ['title' => 'Eerste tab', 'text' => 'Eerste inhoud'],
                    ['title' => 'Tweede tab', 'text' => 'Tweede inhoud'],
                ],
                'width_mode' => 'content',
            ],
            [
                'type' => 'carousel',
                'items' => [
                    ['title' => 'Eerste slide', 'text' => 'Eerste inhoud'],
                    ['title' => 'Tweede slide', 'text' => 'Tweede inhoud'],
                ],
                'previous_label' => 'Vorige',
                'next_label' => 'Volgende',
                'width_mode' => 'content',
            ],
            [
                'type' => 'faq',
                'items' => [
                    ['question' => 'Vraag?', 'answer' => 'Antwoord'],
                ],
                'width_mode' => 'content',
            ],
        ], $post?->content_blocks);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.posts.store', ['id' => 0]), $this->postPayload([
                'title' => 'Accordion geldig',
                'slug' => 'accordion-geldig',
                'content_blocks' => [
                    [
                        'type' => 'accordion',
                        'items' => [
                            ['title' => 'Vraag', 'text' => 'Antwoord', 'unknown' => 'wordt niet opgeslagen'],
                            ['title' => '', 'text' => ''],
                        ],
                    ],
                ],
            ]))
            ->assertRedirect(route('admin.cms.posts.index'))
            ->assertSessionDoesntHaveErrors(['content_blocks']);

        $accordionPost = CmsPost::query()->where('slug', 'accordion-geldig')->first();

        $this->assertEquals([
            [
                'type' => 'accordion',
                'items' => [
                    ['title' => 'Vraag', 'text' => 'Antwoord'],
                ],
                'width_mode' => 'content',
            ],
        ], $accordionPost?->content_blocks);
    }

    public function test_cms_form_can_be_stored_with_fields(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.forms.index'), $this->inertiaHeaders('/admin/cms/forms'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Forms/Index');

        $this
            ->actingAs($user)
            ->post(route('admin.cms.forms.store', ['id' => 0]), $this->formPayload([
                'key' => 'contactformulier-test',
                'title' => 'Contactformulier test',
                'fields' => [
                    [
                        'type' => 'text',
                        'key' => 'naam',
                        'label' => 'Naam',
                        'is_required' => true,
                        'is_active' => true,
                        'width' => 'half',
                    ],
                    [
                        'type' => 'select',
                        'key' => 'onderwerp',
                        'label' => 'Onderwerp',
                        'options' => [
                            ['key' => 'question', 'label' => 'Vraag'],
                            ['key' => 'quote', 'label' => 'Offerte'],
                        ],
                        'is_required' => false,
                        'is_active' => true,
                        'width' => 'full',
                    ],
                ],
            ]))
            ->assertRedirect(route('admin.cms.forms.index'))
            ->assertSessionHas('status');

        $form = CmsForm::query()->where('key', 'contactformulier-test')->with('fields')->first();

        $this->assertNotNull($form);
        $this->assertCount(2, $form?->fields);
        $this->assertSame(['required'], $form?->fields->firstWhere('key', 'naam')?->validation_rules);
        $this->assertSame([
            ['key' => 'question', 'label' => 'Vraag'],
            ['key' => 'quote', 'label' => 'Offerte'],
        ], $form?->fields->firstWhere('key', 'onderwerp')?->options);
    }

    public function test_cms_form_validation_and_submissions_index(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->from(route('admin.cms.forms.create'))
            ->post(route('admin.cms.forms.store', ['id' => 0]), $this->formPayload([
                'key' => 'ongeldig-formulier',
                'fields' => [
                    ['type' => 'select', 'key' => 'keuze', 'label' => 'Keuze', 'options' => []],
                ],
            ]))
            ->assertRedirect(route('admin.cms.forms.create'))
            ->assertSessionHasErrors(['fields.0.options']);

        $form = CmsForm::query()->create($this->formPayload([
            'key' => 'inzendingen-test',
            'title' => 'Inzendingen test',
        ]));
        $field = $form->fields()->create([
            'type' => 'text',
            'key' => 'naam',
            'translation_key' => (string) Str::ulid(),
            'label' => 'Naam',
            'sort_order' => 10,
            'is_required' => true,
            'is_active' => true,
            'width' => 'full',
        ]);
        $submission = CmsFormSubmission::query()->create([
            'cms_form_id' => $form->id,
            'locale' => $form->locale,
            'form_translation_key' => $form->translation_key,
            'status' => 'new',
            'submitted_at' => now(),
        ]);
        CmsFormSubmissionValue::query()->create([
            'cms_form_submission_id' => $submission->id,
            'cms_form_field_id' => $field->id,
            'field_key' => 'naam',
            'field_translation_key' => $field->translation_key,
            'value' => 'Rudi',
        ]);

        $this
            ->actingAs($user)
            ->get(route('admin.cms.form-submissions.index'), $this->inertiaHeaders('/admin/cms/form-submissions'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/FormSubmissions/Index')
            ->assertJsonPath('props.submissions.0.form_title', 'Inzendingen test')
            ->assertJsonPath('props.submissions.0.values.0.value', 'Rudi');
    }

    public function test_cms_freelancer_demo_seeder_matches_current_schema(): void
    {
        $this->seed(CmsFreelancerDemoSeeder::class);

        $this->assertNotNull(CmsPage::query()->where('slug', 'home')->where('locale', 'nl')->first());
        $this->assertNotNull(CmsPage::query()->where('slug', 'portfolio')->where('locale', 'nl')->first());
        $this->assertNotNull(CmsPost::query()->where('slug', 'waarom-laravel-en-vue-sterk-zijn-voor-maatwerk-saas')->first());
        $this->assertNotNull(CmsForm::query()->where('key', 'project-intake')->first());
        $this->assertTrue(DB::table('cms_menus')->where('location', 'header')->exists());
        $this->assertTrue(DB::table('cms_form_submissions')->where('ip_address', '203.0.113.10')->exists());
        $this->assertTrue(Storage::disk('public')->exists('cms/demo/hero-dashboard.webp'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pagePayload(array $overrides = []): array
    {
        $locale = (string) ($overrides['locale'] ?? 'nl');

        return array_merge([
            'parent_id' => null,
            'detail_template_id' => $this->pageDetailTemplateId($locale),
            'title' => 'Pagina',
            'slug' => 'pagina',
            'locale' => $locale,
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
        ], $overrides);
    }

    private function pageDetailTemplateId(string $locale = 'nl'): int
    {
        $layout = CmsLayout::query()->firstOrCreate(
            ['name' => 'Test page layout '.$locale, 'locale' => $locale],
            [
                'is_default' => true,
                'is_active' => true,
                'cache_strategy' => 'inherit',
                'settings' => [],
            ]
        );

        return (int) CmsTemplate::query()->firstOrCreate(
            [
                'name' => 'Test page detail '.$locale,
                'locale' => $locale,
                'template_class' => 'page',
                'template_key' => 'page.detail',
            ],
            [
                'layout_id' => $layout->id,
                'is_default' => true,
                'is_active' => true,
                'cache_strategy' => 'inherit',
                'settings' => [],
            ]
        )->id;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function postPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Bericht',
            'slug' => 'bericht',
            'locale' => 'nl',
            'status' => 'draft',
            'excerpt' => null,
            'content_blocks' => [],
            'seo_title' => null,
            'seo_description' => null,
            'canonical_url' => null,
            'og_image_path' => null,
            'noindex' => false,
            'is_featured' => false,
            'is_searchable' => true,
            'published_at' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formPayload(array $overrides = []): array
    {
        return array_merge([
            'key' => 'formulier',
            'title' => 'Formulier',
            'locale' => 'nl',
            'translation_key' => (string) Str::ulid(),
            'description' => null,
            'notification_email' => null,
            'submit_button_label' => 'Verzenden',
            'success_message' => 'Bedankt. Je formulier is verzonden.',
            'is_active' => true,
            'fields' => [],
        ], $overrides);
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
        $user = User::factory()->create([
            'is_platform_admin' => true,
            'two_factor_secret' => encrypt('cms-backoffice-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $roleId = DB::connection('central')->table('acl_roles')->where('key', 'super_admin')->value('id');

        if (! $roleId) {
            $roleId = DB::connection('central')->table('acl_roles')->insertGetId([
                'key' => 'super_admin',
                'name' => 'Super administrator',
                'description' => 'Test super admin role',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::connection('central')->table('acl_role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'acl_role_id' => $roleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $user;
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
}
