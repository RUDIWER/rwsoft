<?php

namespace Tests\Feature\PublicSite;

use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Models\Cms\CmsPage;
use App\Models\Platform\Site;
use App\Models\Platform\SiteDomain;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicCmsTenantIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'central',
            'database.connections.central' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database.connections.tenant_tenant_a' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database.connections.tenant_tenant_b' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        foreach (['central', 'tenant_tenant_a', 'tenant_tenant_b'] as $connection) {
            DB::purge($connection);
            DB::reconnect($connection);
        }

        $this->createCentralSchema();
        $this->createTenantSchema('tenant_tenant_a');
        $this->createTenantSchema('tenant_tenant_b');

        $this->app->bind(ConfigureTenantDatabaseAction::class, fn (): ConfigureTenantDatabaseAction => new class extends ConfigureTenantDatabaseAction
        {
            public function handle(Site $site): void
            {
                DB::setDefaultConnection('tenant_'.$site->slug);
                TenantContext::setSite($site);
            }
        });
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        DB::setDefaultConnection('central');

        parent::tearDown();
    }

    public function test_tenant_a_content_does_not_leak_to_tenant_b(): void
    {
        $siteA = Site::query()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant_a',
            'tenant_database' => 'tenant_a',
            'status' => 'active',
        ]);
        $siteB = Site::query()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant_b',
            'tenant_database' => 'tenant_b',
            'status' => 'active',
        ]);

        SiteDomain::query()->create([
            'site_id' => $siteA->id,
            'host' => 'tenant-a.test',
            'is_primary' => true,
        ]);
        SiteDomain::query()->create([
            'site_id' => $siteB->id,
            'host' => 'tenant-b.test',
            'is_primary' => true,
        ]);

        $this->seedTenantHomePage('tenant_tenant_a', 'Tenant A homepage', 'Alleen zichtbaar voor tenant A.');
        $this->seedTenantHomePage('tenant_tenant_b', 'Tenant B homepage', 'Alleen zichtbaar voor tenant B.');

        $this
            ->get('http://tenant-a.test/')
            ->assertOk()
            ->assertSee('Tenant A homepage')
            ->assertSee('Alleen zichtbaar voor tenant A.')
            ->assertDontSee('Tenant B homepage')
            ->assertDontSee('Alleen zichtbaar voor tenant B.');

        $this
            ->get('http://tenant-b.test/')
            ->assertOk()
            ->assertSee('Tenant B homepage')
            ->assertSee('Alleen zichtbaar voor tenant B.')
            ->assertDontSee('Tenant A homepage')
            ->assertDontSee('Alleen zichtbaar voor tenant A.');
    }

    private function createCentralSchema(): void
    {
        Schema::connection('central')->create('sites', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 120)->unique();
            $table->string('tenant_database', 160);
            $table->string('status', 32);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->text('provisioning_error')->nullable();
            $table->timestamps();
        });

        Schema::connection('central')->create('site_domains', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('host')->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('force_https')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    private function createTenantSchema(string $connection): void
    {
        Schema::connection($connection)->create('cms_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('source_path');
            $table->string('target_url');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->string('locale', 12)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('key');
            $table->string('label')->nullable();
            $table->string('type')->default('text');
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_setting_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cms_setting_id');
            $table->string('locale', 12);
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_layouts', function (Blueprint $table): void {
            $table->id();
            $table->string('import_key')->nullable();
            $table->string('name');
            $table->string('locale', 12)->default('nl');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('cache_strategy')->default('inherit');
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_pages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('detail_template_id');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('title');
            $table->string('slug');
            $table->string('locale', 12)->default('nl');
            $table->string('status')->default('published');
            $table->string('template')->nullable();
            $table->text('short_description')->nullable();
            $table->json('content_blocks')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('noindex')->default(false);
            $table->boolean('is_home')->default(false);
            $table->boolean('is_searchable')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('import_key')->nullable();
            $table->string('name');
            $table->string('locale', 12)->default('nl');
            $table->string('translation_key')->nullable();
            $table->unsignedBigInteger('translated_from_template_id')->nullable();
            $table->unsignedBigInteger('layout_id')->nullable();
            $table->string('template_class', 32);
            $table->string('template_key', 64);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('cache_strategy', 32)->default('inherit');
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('import_key')->nullable();
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('zone');
            $table->string('name')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('visible_mobile')->default(true);
            $table->boolean('visible_tablet')->default(true);
            $table->boolean('visible_desktop')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_blocks', function (Blueprint $table): void {
            $table->id();
            $table->string('import_key')->nullable();
            $table->string('type');
            $table->string('name')->nullable();
            $table->json('content')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->boolean('is_dynamic')->default(false);
            $table->string('cache_strategy')->default('inherit');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_block_placements', function (Blueprint $table): void {
            $table->id();
            $table->string('import_key')->nullable();
            $table->unsignedBigInteger('cms_section_id');
            $table->unsignedBigInteger('cms_block_id');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('visible_mobile')->default(true);
            $table->boolean('visible_tablet')->default(true);
            $table->boolean('visible_desktop')->default(true);
            $table->integer('mobile_span')->default(12);
            $table->integer('tablet_span')->default(12);
            $table->integer('desktop_span')->default(12);
            $table->string('height_mode')->default('auto');
            $table->string('height_value')->nullable();
            $table->string('cache_strategy')->default('inherit');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_block_overrides', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cms_page_id');
            $table->unsignedBigInteger('cms_block_placement_id');
            $table->json('content')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_block_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cms_page_id');
            $table->unsignedBigInteger('cms_block_placement_id');
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_shared_block_scopes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cms_block_placement_id');
            $table->string('scope_type');
            $table->string('scope_value')->nullable();
            $table->string('locale', 12)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_menus', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_menu_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cms_menu_id');
            $table->string('locale', 12);
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cms_menu_id');
            $table->string('locale', 12)->nullable();
            $table->string('translation_key', 64)->nullable();
            $table->unsignedBigInteger('translated_from_menu_item_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('cms_page_id')->nullable();
            $table->unsignedBigInteger('cms_post_id')->nullable();
            $table->string('type')->default('custom');
            $table->string('label');
            $table->string('url')->nullable();
            $table->string('target')->nullable();
            $table->string('rel')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($connection)->create('cms_media_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('visibility')->default('public');
            $table->string('path');
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->string('extension', 24);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('hash')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->integer('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    private function seedTenantHomePage(string $connection, string $title, string $content): void
    {
        DB::connection($connection)->table('cms_settings')->insert([
            [
                'group' => 'general',
                'key' => 'default_locale',
                'label' => 'default_locale',
                'type' => 'text',
                'value' => json_encode(['value' => 'nl']),
                'is_public' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'general',
                'key' => 'site_name',
                'label' => 'site_name',
                'type' => 'text',
                'value' => json_encode(['value' => $title]),
                'is_public' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $layoutId = DB::connection($connection)->table('cms_layouts')->insertGetId([
            'name' => $title.' layout',
            'locale' => 'nl',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateId = DB::connection($connection)->table('cms_templates')->insertGetId([
            'name' => $title.' detail template',
            'locale' => 'nl',
            'layout_id' => $layoutId,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => true,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $templateSectionId = DB::connection($connection)->table('cms_sections')->insertGetId([
            'owner_type' => 'App\\Models\\Cms\\CmsTemplate',
            'owner_id' => $templateId,
            'zone' => 'content',
            'name' => 'Template content',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contentSlotBlockId = DB::connection($connection)->table('cms_blocks')->insertGetId([
            'type' => 'content_slot',
            'name' => $title.' content slot',
            'content' => json_encode(['renderer_key' => 'content_slot', 'slot_key' => 'content']),
            'settings' => json_encode([]),
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection($connection)->table('cms_block_placements')->insert([
            'cms_section_id' => $templateSectionId,
            'cms_block_id' => $contentSlotBlockId,
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
            'settings' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pageId = DB::connection($connection)->table('cms_pages')->insertGetId([
            'detail_template_id' => $templateId,
            'title' => $title,
            'slug' => 'home',
            'locale' => 'nl',
            'status' => 'published',
            'short_description' => $content,
            'content_blocks' => json_encode([]),
            'noindex' => false,
            'is_home' => true,
            'is_searchable' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sectionId = DB::connection($connection)->table('cms_sections')->insertGetId([
            'owner_type' => CmsPage::class,
            'owner_id' => $pageId,
            'zone' => 'content',
            'name' => 'Content',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blockId = DB::connection($connection)->table('cms_blocks')->insertGetId([
            'type' => 'text',
            'name' => $title,
            'content' => json_encode(['renderer_key' => 'text', 'title' => $title, 'text' => $content]),
            'settings' => json_encode([]),
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection($connection)->table('cms_block_placements')->insert([
            'cms_section_id' => $sectionId,
            'cms_block_id' => $blockId,
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
            'settings' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
