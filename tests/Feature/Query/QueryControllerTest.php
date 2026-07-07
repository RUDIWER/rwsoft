<?php

namespace Tests\Feature\Query;

use App\Models\Query\Query;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Query\Concerns\SetsUpQueryTenant;
use Tests\TestCase;

class QueryControllerTest extends TestCase
{
    use SetsUpQueryTenant;

    public function test_inspect_sql_endpoint_returns_found_bindings(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.queries.builder.inspect'), [
                'query' => 'select id, name from users where id = :user_id and name like :needle',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('bindings.0', 'user_id')
            ->assertJsonPath('bindings.1', 'needle');
    }

    public function test_inspect_sql_endpoint_rejects_non_select_statement(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.queries.builder.inspect'), [
                'query' => 'update users set name = :name where id = :id',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('valid', false);

        $this->assertStringContainsString(
            'Alleen SELECT of CTE queries zijn toegestaan.',
            (string) $response->json('message'),
        );
    }

    public function test_inspect_sql_endpoint_rejects_sql_comments(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.queries.builder.inspect'), [
                'query' => 'select id from users -- comment',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('valid', false);

        $this->assertStringContainsString(
            'SQL comments zijn niet toegestaan.',
            (string) $response->json('message'),
        );
    }

    public function test_inspect_sql_endpoint_rejects_limit_clause(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.queries.builder.inspect'), [
                'query' => 'select id from users limit 10',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('valid', false);

        $this->assertStringContainsString(
            'Gebruik geen LIMIT in SQL queries.',
            (string) $response->json('message'),
        );
    }

    public function test_store_allows_table_output_without_query_group(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->post(route('admin.queries.builder.store-new'), [
                'description' => 'Nieuwe tabel query',
                'slug' => 'nieuwe-tabel-query',
                'query_mode' => 'sql',
                'output_mode' => 'table',
                'query' => 'select id from users',
            ]);

        $query = Query::query()->where('slug', 'nieuwe-tabel-query')->first();

        $this->assertNotNull($query);
        $response->assertRedirect(route('admin.queries.builder.edit', (int) $query?->id));
        $this->assertNull($query?->query_group_id);
    }

    public function test_store_rejects_duplicate_slug_for_existing_query(): void
    {
        $user = $this->createAdminUser();
        Query::query()->create([
            'slug' => 'existing-query-slug',
            'description' => 'Existing query slug',
            'query_mode' => 'sql',
            'output_mode' => 'table',
            'query' => 'select 1 as id',
            'is_active' => true,
        ]);

        $query = Query::query()->create([
            'slug' => 'editable-query-slug',
            'description' => 'Editable query slug',
            'query_mode' => 'sql',
            'output_mode' => 'table',
            'query' => 'select 1 as id',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('admin.queries.builder.edit', $query->id))
            ->post(route('admin.queries.builder.store', $query->id), [
                'description' => 'Editable query slug',
                'slug' => 'existing-query-slug',
                'query_mode' => 'sql',
                'output_mode' => 'table',
                'query' => 'select 1 as id',
                'is_active' => true,
            ]);

        $response
            ->assertRedirect(route('admin.queries.builder.edit', $query->id))
            ->assertSessionHasErrors(['slug']);

        $this->assertDatabaseHas('queries', [
            'id' => $query->id,
            'slug' => 'editable-query-slug',
        ]);
    }

    public function test_store_persists_builder_flags_and_rows(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->post(route('admin.queries.builder.store-new'), [
                'description' => 'Builder query test',
                'slug' => 'builder-query-test',
                'query_mode' => 'builder',
                'output_mode' => 'table',
                'table_name' => 'users',
                'all_fields' => true,
                'distinct_select' => true,
                'query' => 'SELECT users.* FROM users',
                'test_query' => 'SELECT users.* FROM users',
                'selected_fields' => ['users.id'],
                'join_rows' => [[
                    'joinType' => 'LEFT',
                    'originTable' => 'users',
                    'relTable' => 'acl_role_user',
                    'relFieldT1' => 'id',
                    'relFieldT2' => 'user_id',
                ]],
                'where_rows' => [[
                    'whereFieldAndOr' => 'AND',
                    'whereField' => 'users.id',
                    'whereFieldCondition' => '>',
                    'varOrValue' => 'Vaste waarde',
                    'value' => '0',
                ]],
                'group_by' => true,
                'group_rows' => ['users.id'],
                'aggregate_rows' => [[
                    'func' => 'COUNT',
                    'field' => 'users.id',
                    'alias' => 'total_users',
                    'distinct' => false,
                ]],
                'having_rows' => [[
                    'whereFieldAndOr' => 'AND',
                    'whereField' => 'total_users',
                    'whereFieldCondition' => '>',
                    'varOrValue' => 'Waarde',
                    'value' => '0',
                ]],
                'is_active' => true,
            ]);

        $queryId = (int) Query::query()->where('slug', 'builder-query-test')->value('id');

        $response->assertRedirect(route('admin.queries.builder.edit', $queryId));
        $this->assertDatabaseHas('queries', [
            'id' => $queryId,
            'query_mode' => 'builder',
            'table_name' => 'users',
            'all_fields' => 1,
            'distinct_select' => 1,
            'group_by' => 1,
        ]);
    }

    public function test_store_builder_generates_query_with_concat_formula_and_having(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->post(route('admin.queries.builder.store-new'), [
                'description' => 'Builder formule query',
                'slug' => 'builder-formule-query',
                'query_mode' => 'builder',
                'output_mode' => 'table',
                'table_name' => 'users',
                'all_fields' => false,
                'distinct_select' => false,
                'query' => 'select this_should_not_be_used',
                'test_query' => 'select this_should_not_be_used',
                'selected_fields' => ['users.id'],
                'where_rows' => [
                    [
                        'id' => 1,
                        'whereFieldAndOr' => 'AND',
                        'whereField' => 'users.id',
                        'whereFieldCondition' => '>',
                        'varOrValue' => 'Vaste waarde',
                        'value' => '0',
                    ],
                ],
                'group_by' => true,
                'group_rows' => ['users.id'],
                'aggregate_rows' => [
                    [
                        'func' => 'CONCAT',
                        'fields' => ['users.name', 'users.email'],
                        'separator' => ' - ',
                        'alias' => 'name_email',
                    ],
                    [
                        'func' => 'SUM',
                        'field' => 'users.id',
                        'alias' => 'total_users',
                    ],
                    [
                        'func' => 'FORMULA',
                        'formula' => 'SUM(users.id) / NULLIF(COUNT(users.id), 0)',
                        'alias' => 'avg_user_formula',
                    ],
                ],
                'having_rows' => [
                    [
                        'id' => 1,
                        'subRow' => false,
                        'parentId' => null,
                        'paddingLeft' => 0,
                        'whereFieldAndOr' => 'AND',
                        'whereField' => 'total_users',
                        'whereFieldCondition' => '>',
                        'varOrValue' => 'Parameter',
                        'variabele' => 'min_total',
                        'testValue' => '5',
                    ],
                    [
                        'id' => 2,
                        'subRow' => true,
                        'parentId' => 1,
                        'paddingLeft' => 20,
                        'whereFieldAndOr' => 'OR',
                        'whereField' => 'total_users',
                        'whereFieldCondition' => '=',
                        'varOrValue' => 'Vaste waarde',
                        'value' => '999',
                    ],
                ],
                'is_active' => true,
            ]);

        $query = Query::query()->where('slug', 'builder-formule-query')->first();

        $this->assertNotNull($query);
        $response->assertRedirect(route('admin.queries.builder.edit', (int) $query?->id));

        $this->assertStringContainsString("CONCAT_WS('-', users.name, users.email) AS name_email", (string) $query?->query);
        $this->assertStringContainsString('SUM(users.id) AS total_users', (string) $query?->query);
        $this->assertStringContainsString('SUM(users.id) / NULLIF(COUNT(users.id), 0) AS avg_user_formula', (string) $query?->query);
        $this->assertStringContainsString('HAVING (total_users > :min_total OR total_users = 999)', (string) $query?->query);
        $this->assertStringContainsString('HAVING (total_users > 5 OR total_users = 999)', (string) $query?->test_query);
        $this->assertStringNotContainsString('this_should_not_be_used', (string) $query?->query);
    }

    public function test_store_builder_fails_with_row_level_errors_for_invalid_formula_and_having_parameter(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->from(route('admin.queries.builder.create'))
            ->post(route('admin.queries.builder.store-new'), [
                'description' => 'Builder invalid validation query',
                'slug' => 'builder-invalid-validation-query',
                'query_mode' => 'builder',
                'output_mode' => 'table',
                'table_name' => 'users',
                'selected_fields' => ['users.id'],
                'group_by' => true,
                'group_rows' => ['users.id'],
                'aggregate_rows' => [
                    [
                        'func' => 'FORMULA',
                        'formula' => 'unknown_alias + 1',
                        'alias' => 'broken_formula',
                    ],
                ],
                'having_rows' => [
                    [
                        'whereFieldAndOr' => 'AND',
                        'whereField' => 'broken_formula',
                        'whereFieldCondition' => '>',
                        'varOrValue' => 'Parameter',
                        'variabele' => 'invalid parameter',
                    ],
                ],
                'is_active' => true,
            ]);

        $response
            ->assertRedirect(route('admin.queries.builder.create'))
            ->assertSessionHasErrors([
                'aggregate_rows.0.formula',
                'having_rows.0.variabele',
            ]);

        $this->assertDatabaseMissing('queries', [
            'slug' => 'builder-invalid-validation-query',
        ]);
    }

    public function test_store_builder_fails_when_having_subrow_parent_is_invalid(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->from(route('admin.queries.builder.create'))
            ->post(route('admin.queries.builder.store-new'), [
                'description' => 'Builder invalid hierarchy query',
                'slug' => 'builder-invalid-hierarchy-query',
                'query_mode' => 'builder',
                'output_mode' => 'table',
                'table_name' => 'users',
                'selected_fields' => ['users.id'],
                'group_by' => true,
                'group_rows' => ['users.id'],
                'aggregate_rows' => [
                    [
                        'func' => 'COUNT',
                        'field' => 'users.id',
                        'alias' => 'total_users',
                    ],
                ],
                'having_rows' => [
                    [
                        'id' => 10,
                        'subRow' => true,
                        'parentId' => 999,
                        'whereFieldAndOr' => 'AND',
                        'whereField' => 'total_users',
                        'whereFieldCondition' => '>',
                        'varOrValue' => 'Vaste waarde',
                        'value' => '0',
                    ],
                ],
                'is_active' => true,
            ]);

        $response
            ->assertRedirect(route('admin.queries.builder.create'))
            ->assertSessionHasErrors([
                'having_rows.0.parentId',
            ]);

        $this->assertDatabaseMissing('queries', [
            'slug' => 'builder-invalid-hierarchy-query',
        ]);
    }

    public function test_store_builder_supports_between_and_is_null_conditions(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->post(route('admin.queries.builder.store-new'), [
                'description' => 'Builder operator query',
                'slug' => 'builder-operator-query',
                'query_mode' => 'builder',
                'output_mode' => 'table',
                'table_name' => 'users',
                'selected_fields' => ['users.id'],
                'where_rows' => [
                    [
                        'id' => 1,
                        'subRow' => false,
                        'whereFieldAndOr' => 'AND',
                        'whereField' => 'users.id',
                        'whereFieldCondition' => 'BETWEEN',
                        'varOrValue' => 'Parameter',
                        'variabele' => 'from_id',
                        'variabele_to' => 'to_id',
                        'testValue' => '1',
                        'testValueTo' => '25',
                    ],
                    [
                        'id' => 2,
                        'subRow' => false,
                        'whereFieldAndOr' => 'AND',
                        'whereField' => 'users.deleted_at',
                        'whereFieldCondition' => 'IS NULL',
                        'varOrValue' => 'Vaste waarde',
                    ],
                ],
                'is_active' => true,
            ]);

        $query = Query::query()->where('slug', 'builder-operator-query')->first();

        $this->assertNotNull($query);
        $response->assertRedirect(route('admin.queries.builder.edit', (int) $query?->id));

        $this->assertStringContainsString('WHERE users.id BETWEEN :from_id AND :to_id', (string) $query?->query);
        $this->assertStringContainsString('AND users.deleted_at IS NULL', (string) $query?->query);
        $this->assertStringContainsString('WHERE users.id BETWEEN 1 AND 25', (string) $query?->test_query);
    }

    public function test_store_builder_fails_for_between_without_second_parameter_in_where(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->from(route('admin.queries.builder.create'))
            ->post(route('admin.queries.builder.store-new'), [
                'description' => 'Builder between invalid query',
                'slug' => 'builder-between-invalid-query',
                'query_mode' => 'builder',
                'output_mode' => 'table',
                'table_name' => 'users',
                'selected_fields' => ['users.id'],
                'where_rows' => [
                    [
                        'id' => 1,
                        'subRow' => false,
                        'whereFieldAndOr' => 'AND',
                        'whereField' => 'users.id',
                        'whereFieldCondition' => 'BETWEEN',
                        'varOrValue' => 'Parameter',
                        'variabele' => 'from_id',
                    ],
                ],
                'is_active' => true,
            ]);

        $response
            ->assertRedirect(route('admin.queries.builder.create'))
            ->assertSessionHasErrors([
                'where_rows.0.variabele_to',
            ]);

        $this->assertDatabaseMissing('queries', [
            'slug' => 'builder-between-invalid-query',
        ]);
    }

    public function test_store_persists_report_settings_and_template_metadata(): void
    {
        Storage::fake('private');

        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->post(route('admin.queries.builder.store-new'), [
                'description' => 'Rapport query met template',
                'slug' => 'rapport-query-met-template',
                'query_mode' => 'sql',
                'output_mode' => 'report',
                'report_data_source' => 'query',
                'report_output_format' => 'same_format',
                'query' => 'select id from users',
                'report_template_upload' => UploadedFile::fake()->create('template.xlsx', 12),
            ]);

        $query = Query::query()->where('slug', 'rapport-query-met-template')->first();

        $this->assertNotNull($query);
        $response->assertRedirect(route('admin.queries.builder.edit', (int) $query?->id));

        $this->assertSame('report', (string) $query?->output_mode);
        $this->assertSame('query', (string) $query?->report_data_source);
        $this->assertSame('same_format', (string) $query?->report_output_format);
        $this->assertSame('template.xlsx', (string) $query?->report_template_filename);
        $this->assertNotNull($query?->report_template_path);
        $this->assertNotNull($query?->report_template_size_kb);

        Storage::disk('private')->assertExists((string) $query?->report_template_path);
    }

    public function test_store_accepts_csv_report_output_format_without_template_upload(): void
    {
        $user = $this->createAdminUser();

        $response = $this
            ->actingAs($user)
            ->post(route('admin.queries.builder.store-new'), [
                'description' => 'Rapport query csv',
                'slug' => 'rapport-query-csv',
                'query_mode' => 'sql',
                'output_mode' => 'report',
                'report_data_source' => 'query',
                'report_output_format' => 'csv',
                'query' => 'select id from users',
            ]);

        $query = Query::query()->where('slug', 'rapport-query-csv')->first();

        $this->assertNotNull($query);
        $response->assertRedirect(route('admin.queries.builder.edit', (int) $query?->id));
        $this->assertSame('csv', (string) $query?->report_output_format);
        $this->assertNull($query?->report_template_path);
    }

    public function test_template_endpoint_downloads_existing_report_template(): void
    {
        Storage::fake('private');

        $user = $this->createAdminUser();
        Storage::disk('private')->put('query-reports/templates/test-template.xlsx', 'xlsx-content');

        $query = Query::query()->create([
            'slug' => 'template-download-test',
            'description' => 'Template download test',
            'query_mode' => 'sql',
            'output_mode' => 'report',
            'query' => 'select 1',
            'report_template_path' => 'query-reports/templates/test-template.xlsx',
            'report_template_extension' => 'xlsx',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.queries.builder.template', ['query' => $query->id]));

        $response
            ->assertOk()
            ->assertDownload('template-download-test.xlsx');
    }

    public function test_template_endpoint_redirects_when_template_is_missing(): void
    {
        Storage::fake('private');

        $user = $this->createAdminUser();
        $query = Query::query()->create([
            'slug' => 'template-missing-test',
            'description' => 'Template missing test',
            'query_mode' => 'sql',
            'output_mode' => 'report',
            'query' => 'select 1',
            'report_template_path' => 'query-reports/templates/missing.xlsx',
            'report_template_extension' => 'xlsx',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.queries.builder.template', ['query' => $query->id]));

        $response
            ->assertRedirect(route('admin.queries.builder.edit', ['query' => $query->id]))
            ->assertSessionHas('error', 'Geen rapport template beschikbaar voor deze query.');
    }

    public function test_delete_removes_query_when_no_active_references_exist(): void
    {
        $user = $this->createAdminUser();
        $query = Query::query()->create([
            'slug' => 'delete-allowed-query',
            'description' => 'Delete allowed query',
            'query_mode' => 'sql',
            'output_mode' => 'table',
            'query' => 'select 1 as id',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('admin.queries.builder.delete', ['query' => $query->id]));

        $response
            ->assertRedirect(route('admin.queries.builder.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('queries', [
            'id' => $query->id,
        ]);
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->make([
            'two_factor_secret' => encrypt('query-controller-test-secret'),
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
