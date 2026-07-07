<?php

namespace Tests\Feature\Query;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Query\ChartGroup;
use App\Models\Query\Query;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Query\Concerns\SetsUpQueryTenant;
use Tests\TestCase;

class QueryChartControllerTest extends TestCase
{
    use SetsUpQueryTenant;

    public function test_show_renders_chart_view_for_chart_output_query(): void
    {
        $user = $this->createAdminUser();
        $chartGroup = ChartGroup::query()->create([
            'name' => 'Grafiek groep A',
            'description' => 'Test',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $query = $this->createChartQuery($chartGroup->id);

        $response = $this
            ->actingAs($user)
            ->withHeaders($this->inertiaHeaders('/admin/run/queries/'.$query->id.'/chart'))
            ->get(route('admin.run.queries.chart.show', [
                'query' => $query->id,
                'bindings' => json_encode(['user_id' => $user->id], JSON_THROW_ON_ERROR),
            ]));

        $response->assertOk();
        $this->assertSame('Admin/Query/QueryChartView', $response->json('component'));
        $this->assertSame((int) $query->id, (int) $response->json('props.query.id'));
        $this->assertSame('chart', (string) $response->json('props.query.output_mode'));
    }

    public function test_preview_returns_aggregated_preview_data_for_valid_chart_config(): void
    {
        $user = $this->createAdminUser();
        $chartGroup = ChartGroup::query()->create([
            'name' => 'Grafiek groep B',
            'description' => 'Test',
            'sort_order' => 20,
            'is_active' => true,
        ]);
        $query = $this->createChartQuery($chartGroup->id);

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.run.queries.chart.preview', [
                'query' => $query->id,
            ]), [
                'config' => $query->chart_config,
                'bindings' => [
                    'user_id' => $user->id,
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('preview.labels.0', $user->name)
            ->assertJsonPath('preview.series.0.name', 'Totaal')
            ->assertJsonPath('preview.series.0.data.0', 1)
            ->assertJsonPath('meta.series_count', 1);
    }

    public function test_show_returns_404_for_non_chart_output_query(): void
    {
        $user = $this->createAdminUser();
        $query = Query::query()->create([
            'slug' => 'chart-show-non-chart-query',
            'description' => 'Geen chart query',
            'query_mode' => 'sql',
            'output_mode' => 'table',
            'query' => 'select id from users',
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->get(route('admin.run.queries.chart.show', [
                'query' => $query->id,
            ]))
            ->assertNotFound();
    }

    private function createChartQuery(int $chartGroupId): Query
    {
        return Query::query()->create([
            'slug' => 'chart-preview-query-'.str()->lower(str()->random(8)),
            'description' => 'Chart preview query',
            'query_mode' => 'sql',
            'output_mode' => 'chart',
            'query' => 'select id, name, email from users where id = :user_id',
            'chart_group_id' => $chartGroupId,
            'chart_config' => [
                'builder' => [
                    'dataset' => [
                        'x_field' => 'name',
                        'metric_field' => '',
                        'aggregate' => 'count',
                        'series_field' => '',
                        'sort_direction' => 'desc',
                        'limit' => 25,
                    ],
                    'chart' => [
                        'type' => 'bar',
                        'orientation' => 'vertical',
                        'stacked' => false,
                        'show_legend' => true,
                    ],
                    'presentation' => [
                        'title' => 'Test grafiek',
                        'subtitle' => '',
                        'show_source_table_button' => true,
                        'allow_chart_type_change' => true,
                        'show_pdf_print_button' => false,
                    ],
                ],
            ],
            'is_active' => true,
        ]);
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->make([
            'two_factor_secret' => encrypt('query-chart-test-secret'),
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
                ],
            );
        }

        $this->grantQueryTestSiteMembership($user);

        return $user;
    }

    /** @return array<string, string> */
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
