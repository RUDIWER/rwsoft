<?php

namespace Tests\Feature\Platform;

use App\Actions\Platform\SeedTenantCmsDefaultsAction;
use App\Http\Controllers\Admin\Cms\CmsMediaController;
use App\Models\Platform\Site;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class TenantCmsDefaultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'central',
            'database.connections.central' => array_merge(config('database.connections.mysql'), [
                'database' => 'rwsoft',
            ]),
            'database.connections.tenant' => array_merge(config('database.connections.mysql'), [
                'database' => 'rwsoft',
            ]),
        ]);

        DB::purge('central');
        DB::purge('tenant');
        DB::reconnect('central');
        DB::reconnect('tenant');
        DB::connection('tenant')->beginTransaction();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        parent::tearDown();
    }

    public function test_seed_tenant_cms_defaults_creates_homepage_and_settings(): void
    {
        $site = $this->site('cms-defaults-'.uniqid(), 'Tenant Defaults School');

        app(SeedTenantCmsDefaultsAction::class)->handle($site);

        $homepage = DB::connection('tenant')
            ->table('cms_pages')
            ->where('locale', config('app.locale', 'nl'))
            ->where('is_home', true)
            ->first();

        $this->assertNotNull($homepage);
        $this->assertSame('Home', $homepage?->title);
        $this->assertSame('published', $homepage?->status);

        $siteName = DB::connection('tenant')
            ->table('cms_settings')
            ->where('group', 'general')
            ->where('key', 'site_name')
            ->value('value');

        $homepageSetting = DB::connection('tenant')
            ->table('cms_settings')
            ->where('group', 'general')
            ->where('key', 'homepage_id')
            ->value('value');

        $this->assertSame('Tenant Defaults School', json_decode((string) $siteName, true)['value'] ?? null);
        $this->assertSame((int) $homepage?->id, json_decode((string) $homepageSetting, true)['value'] ?? null);
    }

    public function test_media_directory_is_prefixed_with_current_site_id(): void
    {
        $site = $this->site('media-dir-'.uniqid(), 'Media Directory School');
        $site->id = 12345;
        TenantContext::setSite($site);

        $method = new ReflectionMethod(CmsMediaController::class, 'mediaDirectory');
        $method->setAccessible(true);

        $directory = $method->invoke(app(CmsMediaController::class));

        $this->assertSame('sites/12345/cms/media', $directory);
    }

    private function site(string $slug, string $name): Site
    {
        $site = new Site([
            'name' => $name,
            'slug' => $slug,
            'tenant_database' => 'rwsoft',
            'status' => 'active',
        ]);
        $site->id = 999999;

        return $site;
    }
}
