<?php

namespace Tests\Feature\Query;

use App\Models\Query\Query;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Query\Concerns\SetsUpQueryTenant;
use Tests\TestCase;

class QueryLegacyReportControllerTest extends TestCase
{
    use SetsUpQueryTenant;

    public function test_legacy_report_selections_redirects_to_query_run_page(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('table');

        $response = $this
            ->actingAs($user)
            ->get(route('admin.report-selections', [
                'id' => $query->id,
                'user_id' => 10,
            ]));

        $response->assertRedirect(route('admin.run.queries.show', [
            'query' => $query->id,
            'user_id' => 10,
        ]));
    }

    public function test_legacy_reports_create_redirects_to_table_preview_for_table_output(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('table');

        $response = $this
            ->actingAs($user)
            ->get(route('admin.reports.create', [
                'id' => $query->id,
            ]));

        $response->assertRedirect(route('admin.run.queries.show', [
            'query' => $query->id,
            '__force_table' => 1,
        ]));
    }

    public function test_legacy_reports_create_redirects_to_export_for_excel_output(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('excel');

        $response = $this
            ->actingAs($user)
            ->get(route('admin.reports.create', [
                'id' => $query->id,
                'student_id' => 7,
            ]));

        $response->assertRedirect(route('admin.run.queries.export', [
            'query' => $query->id,
            'student_id' => 7,
        ]));
    }

    public function test_legacy_reports_create_redirects_to_report_for_report_output(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('report');

        $response = $this
            ->actingAs($user)
            ->get(route('admin.reports.create', [
                'id' => $query->id,
                'school_id' => 4,
            ]));

        $response->assertRedirect(route('admin.run.queries.report', [
            'query' => $query->id,
            'school_id' => 4,
        ]));
    }

    public function test_legacy_reports_download_uses_report_id_parameter(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('excel');

        $response = $this
            ->actingAs($user)
            ->get(route('admin.reports.download', [
                'report_id' => $query->id,
                'student_id' => 12,
            ]));

        $response->assertRedirect(route('admin.run.queries.export', [
            'query' => $query->id,
            'student_id' => 12,
        ]));
    }

    public function test_legacy_reports_template_redirects_to_builder_template_route(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('report');

        $response = $this
            ->actingAs($user)
            ->get(route('admin.reports.template', [
                'id' => $query->id,
            ]));

        $response->assertRedirect(route('admin.queries.builder.template', [
            'query' => $query->id,
        ]));
    }

    private function createQuery(string $outputMode): Query
    {
        return Query::query()->create([
            'slug' => 'legacy-query-'.str()->lower(str()->random(8)),
            'description' => 'Legacy query test',
            'query_mode' => 'sql',
            'output_mode' => $outputMode,
            'query' => 'select 1',
            'is_active' => true,
        ]);
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->make([
            'two_factor_secret' => encrypt('query-legacy-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);
        $user->setConnection(config('database.default'));
        $user->save();

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

        $this->grantQueryTestSiteMembership($user);

        return $user;
    }
}
