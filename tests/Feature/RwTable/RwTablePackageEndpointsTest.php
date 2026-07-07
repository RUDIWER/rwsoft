<?php

namespace Tests\Feature\RwTable;

use App\Actions\Admin\Base\RwTableAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Rwsoft\RwTableLaravel\Actions\RwTableAction as PackageRwTableAction;
use Tests\TestCase;

class RwTablePackageEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rwtable_package_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.rw-table-charts.index'));
        $this->assertTrue(Route::has('admin.rw-table-charts.store'));
        $this->assertTrue(Route::has('admin.rw-table-charts.destroy'));
        $this->assertTrue(Route::has('admin.rw-table-exports.index'));
        $this->assertTrue(Route::has('admin.rw-table-exports.store'));
        $this->assertTrue(Route::has('admin.rw-table-exports.delete'));
        $this->assertTrue(Route::has('admin.rw-table-exports.destroy'));
    }

    public function test_guest_cannot_access_rwtable_package_routes(): void
    {
        $response = $this->get('/admin/rw-table-charts/users-table');

        $response->assertRedirect('/login');
    }

    public function test_rwtable_configuration_defaults_are_loaded(): void
    {
        $this->assertSame('admin', config('rwtable.routes.prefix'));
        $this->assertSame('admin.', config('rwtable.routes.name_prefix'));
        $this->assertTrue((bool) config('rwtable.routes.enabled'));
        $this->assertSame('/^[A-Za-z0-9_\\.]+$/', config('rwtable.security.allowed_field_pattern'));
    }

    public function test_application_bridge_uses_package_rwtable_action(): void
    {
        $this->assertTrue(is_subclass_of(RwTableAction::class, PackageRwTableAction::class));
    }

    public function test_authenticated_user_can_create_update_list_and_delete_chart_configuration(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $createResponse = $this->post('/admin/rw-table-charts/users-table', [
            'description' => 'Mijn chart',
            'config' => [
                'type' => 'bar',
                'xAxis' => 'status',
                'operation' => 'count',
            ],
        ]);

        $createResponse->assertOk();
        $chartId = $createResponse->json('chart.id');

        $this->assertDatabaseHas('rw_table_charts', [
            'id' => $chartId,
            'user_id' => $user->id,
            'table_identifier' => 'users-table',
            'description' => 'Mijn chart',
        ]);

        $listResponse = $this->get('/admin/rw-table-charts/users-table');
        $listResponse->assertOk();
        $listResponse->assertJsonCount(1, 'charts');

        $updateResponse = $this->post('/admin/rw-table-charts/users-table', [
            'id' => $chartId,
            'description' => 'Mijn chart bijgewerkt',
            'config' => [
                'type' => 'line',
                'xAxis' => 'status',
                'operation' => 'count',
            ],
        ]);

        $updateResponse->assertOk();

        $this->assertDatabaseHas('rw_table_charts', [
            'id' => $chartId,
            'description' => 'Mijn chart bijgewerkt',
        ]);

        $deleteResponse = $this->delete("/admin/rw-table-charts/{$chartId}");
        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('rw_table_charts', [
            'id' => $chartId,
        ]);
    }

    public function test_authenticated_user_can_create_update_list_and_delete_export_configuration(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $createResponse = $this->post('/admin/rw-table-exports/users-table', [
            'description' => 'Mijn export',
            'config' => [
                'columns' => ['id', 'name', 'email'],
            ],
        ]);

        $createResponse->assertOk();
        $exportId = $createResponse->json('export.id');

        $this->assertDatabaseHas('rw_table_exports', [
            'id' => $exportId,
            'user_id' => $user->id,
            'table_identifier' => 'users-table',
            'description' => 'Mijn export',
        ]);

        $listResponse = $this->get('/admin/rw-table-exports/users-table');
        $listResponse->assertOk();
        $listResponse->assertJsonCount(1, 'exports');

        $updateResponse = $this->post('/admin/rw-table-exports/users-table', [
            'id' => $exportId,
            'description' => 'Mijn export bijgewerkt',
            'config' => [
                'columns' => ['id', 'email'],
            ],
        ]);

        $updateResponse->assertOk();

        $this->assertDatabaseHas('rw_table_exports', [
            'id' => $exportId,
            'description' => 'Mijn export bijgewerkt',
        ]);

        $deleteResponse = $this->delete("/admin/rw-table-exports/{$exportId}");
        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('rw_table_exports', [
            'id' => $exportId,
        ]);

        $secondCreateResponse = $this->post('/admin/rw-table-exports/users-table', [
            'description' => 'Mijn export tweede',
            'config' => [
                'columns' => ['id'],
            ],
        ]);
        $secondCreateResponse->assertOk();
        $secondExportId = $secondCreateResponse->json('export.id');

        $deleteByDestroyAlias = $this->delete("/admin/rw-table-exports/{$secondExportId}/destroy");
        $deleteByDestroyAlias->assertOk();

        $this->assertDatabaseMissing('rw_table_exports', [
            'id' => $secondExportId,
        ]);
    }

    public function test_user_can_only_access_own_rwtable_records(): void
    {
        $owner = $this->createAdminUser();
        $otherUser = $this->createAdminUser();

        $chartCreateResponse = $this->actingAs($owner)->post('/admin/rw-table-charts/users-table', [
            'description' => 'Owner chart',
            'config' => [
                'type' => 'bar',
            ],
        ]);
        $chartCreateResponse->assertOk();
        $chartId = $chartCreateResponse->json('chart.id');

        $exportCreateResponse = $this->actingAs($owner)->post('/admin/rw-table-exports/users-table', [
            'description' => 'Owner export',
            'config' => [
                'columns' => ['id'],
            ],
        ]);
        $exportCreateResponse->assertOk();
        $exportId = $exportCreateResponse->json('export.id');

        $listChartsAsOther = $this->actingAs($otherUser)->get('/admin/rw-table-charts/users-table');
        $listChartsAsOther->assertOk();
        $listChartsAsOther->assertJsonCount(0, 'charts');

        $listExportsAsOther = $this->actingAs($otherUser)->get('/admin/rw-table-exports/users-table');
        $listExportsAsOther->assertOk();
        $listExportsAsOther->assertJsonCount(0, 'exports');

        $deleteChartAsOther = $this->actingAs($otherUser)->delete("/admin/rw-table-charts/{$chartId}");
        $deleteChartAsOther->assertNotFound();

        $deleteExportAsOther = $this->actingAs($otherUser)->delete("/admin/rw-table-exports/{$exportId}/destroy");
        $deleteExportAsOther->assertNotFound();
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('rwtable-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $roleId = DB::table('acl_roles')->where('key', 'super_admin')->value('id');

        if ($roleId) {
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
        }

        return $user;
    }
}
