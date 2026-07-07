<?php

namespace Tests\Feature\Cms;

use App\Http\Middleware\AuthAdminUsers;
use App\Http\Middleware\AuthorizeAdminRoute;
use App\Http\Middleware\EnsureSiteMembership;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\ResolveTenantSite;
use App\Models\Cms\CmsLanguage;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CmsLanguageBackofficeTest extends TestCase
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

        $this->ensureLanguagesExist();
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

    public function test_language_order_can_be_saved(): void
    {
        $user = $this->createAdminUser();
        $currentIds = CmsLanguage::query()
            ->orderBy('sort_order')
            ->orderBy('locale')
            ->pluck('id')
            ->all();
        $newOrder = collect($currentIds)
            ->reverse()
            ->values()
            ->all();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.languages.reorder'), [
                'languages' => $newOrder,
            ])
            ->assertRedirect(route('admin.cms.languages.index'))
            ->assertSessionDoesntHaveErrors();

        foreach ($newOrder as $index => $languageId) {
            $this->assertSame(
                ($index + 1) * 10,
                CmsLanguage::query()->findOrFail($languageId)->sort_order,
            );
        }
    }

    public function test_language_store_does_not_change_order(): void
    {
        $user = $this->createAdminUser();
        $language = CmsLanguage::query()->where('locale', 'nl')->firstOrFail();
        $originalSortOrder = $language->sort_order;

        $this
            ->actingAs($user)
            ->post(route('admin.cms.languages.store', ['id' => $language->id]), [
                'locale' => 'nl',
                'name' => 'Nederlands aangepast',
                'native_name' => 'Nederlands',
                'direction' => 'ltr',
                'is_active' => true,
                'sort_order' => 999,
            ])
            ->assertRedirect(route('admin.cms.languages.edit', ['id' => $language->id]))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $language->refresh();

        $this->assertSame('Nederlands aangepast', $language->name);
        $this->assertSame($originalSortOrder, $language->sort_order);
    }

    private function ensureLanguagesExist(): void
    {
        foreach ($this->languageRows() as $language) {
            CmsLanguage::query()->updateOrCreate(
                ['locale' => $language['locale']],
                $language,
            );
        }
    }

    /**
     * @return array<int, array{locale: string, name: string, native_name: string, direction: string, is_active: bool, sort_order: int}>
     */
    private function languageRows(): array
    {
        return [
            ['locale' => 'nl', 'name' => 'Nederlands', 'native_name' => 'Nederlands', 'direction' => 'ltr', 'is_active' => true, 'sort_order' => 10],
            ['locale' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true, 'sort_order' => 20],
            ['locale' => 'fr', 'name' => 'Frans', 'native_name' => 'Français', 'direction' => 'ltr', 'is_active' => true, 'sort_order' => 30],
        ];
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('cms-language-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $roleId = DB::table('acl_roles')->where('key', 'super_admin')->value('id');

        if (! $roleId) {
            $roleId = DB::table('acl_roles')->insertGetId([
                'key' => 'super_admin',
                'name' => 'Super administrator',
                'description' => 'Test super admin role',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('acl_role_user')->updateOrInsert(
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
}
