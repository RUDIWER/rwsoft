<?php

namespace Tests\Feature\Cms;

use App\Http\Middleware\AuthAdminUsers;
use App\Http\Middleware\AuthorizeAdminRoute;
use App\Http\Middleware\EnsureSiteMembership;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenantSite;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsLanguage;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsTemplate;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsCategoryBackofficeTest extends TestCase
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
            ],
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
            'name' => 'Extra category archive',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'category',
            'template_key' => 'category.archive',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $detailTemplate = CmsTemplate::query()->create([
            'name' => 'Extra category detail',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'category',
            'template_key' => 'category.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $category = CmsCategory::query()->create([
            'type' => 'post',
            'title' => 'Inzichten '.Str::random(6),
            'slug' => 'inzichten-'.Str::lower(Str::random(6)),
            'locale' => 'nl',
            'translation_key' => (string) Str::ulid(),
            'is_active' => true,
        ]);

        $defaultArchiveTemplateId = (int) CmsTemplate::query()
            ->where('template_class', 'category')
            ->where('template_key', 'category.archive')
            ->where('locale', 'nl')
            ->where('is_default', true)
            ->value('id');
        $defaultDetailTemplateId = (int) CmsTemplate::query()
            ->where('template_class', 'category')
            ->where('template_key', 'category.detail')
            ->where('locale', 'nl')
            ->where('is_default', true)
            ->value('id');

        $this
            ->actingAs($user)
            ->get(route('admin.cms.categories.edit', ['id' => $category->id]), $this->inertiaHeaders('/admin/cms/categories/'.$category->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Categories/Edit')
            ->assertJsonPath('props.category.preview_archive_url', url('/nl/blogs/categories/'.$category->slug))
            ->assertJsonPath('props.category.preview_detail_url', url('/nl/blogs/categories/'.$category->slug.'/info'))
            ->assertJsonFragment(['id' => $archiveTemplate->id, 'is_default' => false])
            ->assertJsonFragment(['id' => $detailTemplate->id, 'is_default' => false])
            ->assertJsonFragment(['id' => $defaultArchiveTemplateId, 'is_default' => true])
            ->assertJsonFragment(['id' => $defaultDetailTemplateId, 'is_default' => true]);
    }

    private function createAdminUser(): User
    {
        return User::factory()->create();
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
}
