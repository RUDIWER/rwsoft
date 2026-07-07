<?php

namespace Tests\Feature\Query;

use App\Actions\Admin\Base\Query\RunSqlQueryAction;
use App\Actions\Admin\Base\Query\ValidateSqlQueryAction;
use App\Models\Query\Query;
use App\Models\Query\QueryBuilderSelectTable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\Query\Concerns\SetsUpQueryTenant;
use Tests\TestCase;
use ZipArchive;

class QueryRunControllerTest extends TestCase
{
    use SetsUpQueryTenant;

    public function test_data_endpoint_returns_422_when_query_is_inactive(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('select id from users', false);

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.run.queries.data', $query->id), []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Deze query is niet actief.');
    }

    public function test_data_endpoint_returns_422_when_required_binding_is_missing(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('select id from users where id = :user_id');

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.run.queries.data', $query->id), [
                'page' => 1,
                'rowsPerPage' => 25,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('missing_bindings.0', 'user_id');
    }

    public function test_data_endpoint_returns_rows_when_binding_is_present(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('select id, name from users where id = :user_id');

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.run.queries.data', $query->id), [
                'page' => 1,
                'rowsPerPage' => 25,
                'bindings' => [
                    'user_id' => $user->id,
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $user->id);
    }

    public function test_data_endpoint_applies_column_filters(): void
    {
        $user = $this->createAdminUser();

        $this->createUser([
            'name' => 'Filter Target User',
        ]);

        $this->createUser([
            'name' => 'Another User',
        ]);

        $query = $this->createQuery('select id, name from users');

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.run.queries.data', $query->id), [
                'page' => 1,
                'rowsPerPage' => 25,
                'filters' => [
                    'name' => 'Filter Target',
                ],
                'filterModes' => [
                    'name' => 'contains',
                ],
                'filterTypes' => [
                    'name' => 'text',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Filter Target User');
    }

    public function test_data_endpoint_resolves_current_school_year_system_binding(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('select :CURRENTSCHOOLYEAR as current_school_year');

        $response = $this
            ->actingAs($user)
            ->postJson(route('admin.run.queries.data', $query->id), [
                'page' => 1,
                'rowsPerPage' => 25,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.current_school_year', (int) now()->format('Y'));
    }

    public function test_data_endpoint_supports_json_contains_not_expression_with_comma_values(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('JSON_CONTAINS test vereist MySQL.');
        }

        $sql = "select 1 as keep where NOT JSON_CONTAINS(JSON_ARRAY('a,b', 'x'), JSON_QUOTE(:needle))";
        $validation = ValidateSqlQueryAction::handle($sql);

        $this->assertTrue($validation['is_valid']);

        $matching = RunSqlQueryAction::handle(
            (string) $validation['sql'],
            ['needle' => 'a,b'],
            1,
            25,
        );

        $this->assertSame(0, (int) $matching['total']);

        $nonMatching = RunSqlQueryAction::handle(
            (string) $validation['sql'],
            ['needle' => 'z,z'],
            1,
            25,
        );

        $this->assertSame(1, (int) $nonMatching['total']);
        $this->assertSame(1, (int) ($nonMatching['data'][0]['keep'] ?? 0));
    }

    public function test_binding_source_options_endpoint_returns_options_for_active_source(): void
    {
        $user = $this->createAdminUser();
        $source = QueryBuilderSelectTable::query()->create([
            'name' => 'Gebruikers',
            'table_name' => 'users',
            'select_field' => 'id',
            'label_fields' => ['name', 'email'],
            'search_fields' => ['name', 'email'],
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('admin.run.queries.binding-source-options', [
                'source_table_id' => $source->id,
                'q' => $user->email,
            ]));

        $response
            ->assertOk()
            ->assertJsonPath('options.0.value', $user->id);
    }

    public function test_binding_source_options_endpoint_returns_404_for_inactive_source(): void
    {
        $user = $this->createAdminUser();
        $source = QueryBuilderSelectTable::query()->create([
            'name' => 'Inactief',
            'table_name' => 'users',
            'select_field' => 'id',
            'label_fields' => ['name'],
            'search_fields' => ['name'],
            'sort_order' => 10,
            'is_active' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('admin.run.queries.binding-source-options', [
                'source_table_id' => $source->id,
            ]));

        $response->assertStatus(404);
    }

    public function test_export_endpoint_downloads_csv_for_excel_output(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('select id, name from users where id = :user_id', true, [
            'output_mode' => 'excel',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.run.queries.export', [
                'query' => $query->id,
                'user_id' => $user->id,
            ]));

        $response
            ->assertOk()
            ->assertDownload();
    }

    public function test_report_endpoint_downloads_csv_for_query_data_source(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('select id, name from users where id = :user_id', true, [
            'output_mode' => 'report',
            'report_data_source' => 'query',
            'report_output_format' => 'csv',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.run.queries.report', [
                'query' => $query->id,
                'user_id' => $user->id,
            ]));

        $response
            ->assertOk()
            ->assertDownload();
    }

    public function test_report_endpoint_downloads_rendered_sheet_for_same_format_output(): void
    {
        Storage::fake('private');

        $user = $this->createAdminUser();
        $templateRelativePath = 'query-reports/templates/run-template.xlsx';
        $templateAbsolutePath = Storage::disk('private')->path($templateRelativePath);

        @mkdir(dirname($templateAbsolutePath), 0777, true);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '{{ first.id }}');
        $sheet->setCellValue('A2', '{{ rows:data by=role groupLabel=Rol }}');
        $sheet->setCellValue('A3', '{{ row.name }}');
        $sheet->setCellValue('B3', '=ROW()');
        $sheet->setCellValue('C3', '{{ image:row.avatar width=40 height=20 }}');
        $sheet->setCellValue('A4', '{{ /rows }}');

        $imagePath = Storage::disk('private')->path('query-reports/templates/avatar.png');
        @mkdir(dirname($imagePath), 0777, true);
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2nK6cAAAAASUVORK5CYII='));

        $writer = new Xlsx($spreadsheet);
        $writer->save($templateAbsolutePath);
        $spreadsheet->disconnectWorksheets();

        $query = $this->createQuery('select id from users', true, [
            'output_mode' => 'report',
            'report_data_source' => 'query',
            'report_output_format' => 'same_format',
            'report_template_path' => $templateRelativePath,
            'report_template_extension' => 'xlsx',
            'query' => "select {$user->id} as id, 'Jan' as name, 'admin' as role, 'query-reports/templates/avatar.png' as avatar",
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.run.queries.report', [
                'query' => $query->id,
            ]));

        if ($response->getStatusCode() !== 200) {
            $this->fail((string) $response->getSession()->get('error'));
        }

        $response
            ->assertOk()
            ->assertDownload();
    }

    public function test_report_endpoint_downloads_rendered_document_for_same_format_docx(): void
    {
        Storage::fake('private');

        $user = $this->createAdminUser();
        $templateRelativePath = 'query-reports/templates/run-template.docx';
        $this->createMinimalDocxTemplate(
            Storage::disk('private')->path($templateRelativePath),
            '{{ first.name }}{{ rows:data }}{{ row.name }}{{ /rows }}',
        );

        $this->assertTrue(Storage::disk('private')->exists($templateRelativePath));

        $query = $this->createQuery('select id from users', true, [
            'output_mode' => 'report',
            'report_data_source' => 'query',
            'report_output_format' => 'same_format',
            'report_template_path' => $templateRelativePath,
            'report_template_extension' => 'docx',
            'query' => "select 1 as id, 'Jan' as name",
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.run.queries.report', [
                'query' => $query->id,
            ]));

        $response
            ->assertOk()
            ->assertDownload();
    }

    public function test_report_endpoint_redirects_for_unsupported_same_format_template(): void
    {
        Storage::fake('private');

        $user = $this->createAdminUser();
        Storage::disk('private')->put('query-reports/templates/run-template.txt', 'plain-text-template');

        $query = $this->createQuery('select id from users', true, [
            'output_mode' => 'report',
            'report_data_source' => 'query',
            'report_output_format' => 'same_format',
            'report_template_path' => 'query-reports/templates/run-template.txt',
            'report_template_extension' => 'txt',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.run.queries.report', [
                'query' => $query->id,
            ]));

        $response
            ->assertRedirect(route('admin.run.queries.show', ['query' => $query->id]))
            ->assertSessionHas('error', 'Dit templateformaat wordt nog niet ondersteund. Gebruik voorlopig xlsx, ods, docx of odt.');
    }

    public function test_report_endpoint_redirects_when_same_format_template_is_missing(): void
    {
        Storage::fake('private');

        $user = $this->createAdminUser();
        $query = $this->createQuery('select id from users', true, [
            'output_mode' => 'report',
            'report_data_source' => 'query',
            'report_output_format' => 'same_format',
            'report_template_path' => 'query-reports/templates/missing.docx',
            'report_template_extension' => 'docx',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.run.queries.report', [
                'query' => $query->id,
            ]));

        $response
            ->assertRedirect(route('admin.run.queries.show', ['query' => $query->id]))
            ->assertSessionHas('error', 'Geen rapport template beschikbaar voor deze query.');
    }

    public function test_report_endpoint_redirects_for_external_data_source(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('select id from users', true, [
            'output_mode' => 'report',
            'report_data_source' => 'external',
            'report_output_format' => 'same_format',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.run.queries.report', [
                'query' => $query->id,
            ]));

        $response
            ->assertRedirect(route('admin.run.queries.show', ['query' => $query->id]))
            ->assertSessionHas('error');
    }

    public function test_report_endpoint_redirects_for_pdf_when_template_is_missing(): void
    {
        $user = $this->createAdminUser();
        $query = $this->createQuery('select id from users', true, [
            'output_mode' => 'report',
            'report_data_source' => 'query',
            'report_output_format' => 'pdf',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.run.queries.report', [
                'query' => $query->id,
            ]));

        $response
            ->assertRedirect(route('admin.run.queries.show', ['query' => $query->id]))
            ->assertSessionHas('error', 'Geen rapport template beschikbaar voor deze query.');
    }

    public function test_report_endpoint_downloads_pdf_for_supported_template(): void
    {
        if (trim((string) shell_exec('which soffice')) === '') {
            $this->markTestSkipped('PDF test vereist soffice.');
        }

        Storage::fake('private');

        $user = $this->createAdminUser();
        $templateRelativePath = 'query-reports/templates/run-template-pdf.xlsx';
        $templateAbsolutePath = Storage::disk('private')->path($templateRelativePath);
        @mkdir(dirname($templateAbsolutePath), 0777, true);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', '{{ first.id }}');
        $sheet->setCellValue('A2', '{{ rows:data }}');
        $sheet->setCellValue('A3', '{{ row.name }}');
        $sheet->setCellValue('A4', '{{ /rows }}');
        $writer = new Xlsx($spreadsheet);
        $writer->save($templateAbsolutePath);
        $spreadsheet->disconnectWorksheets();

        $query = $this->createQuery('select 1 as id, "Pdf Test" as name', true, [
            'output_mode' => 'report',
            'report_data_source' => 'query',
            'report_output_format' => 'pdf',
            'report_template_path' => $templateRelativePath,
            'report_template_extension' => 'xlsx',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('admin.run.queries.report', [
                'query' => $query->id,
            ]));

        $response
            ->assertOk()
            ->assertDownload();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createQuery(string $sql, bool $isActive = true, array $overrides = []): Query
    {
        return Query::query()->create(array_merge([
            'slug' => 'query-'.str()->lower(str()->random(8)),
            'description' => 'Test query',
            'query_mode' => 'sql',
            'output_mode' => 'table',
            'query' => $sql,
            'is_active' => $isActive,
        ], $overrides));
    }

    private function createAdminUser(): User
    {
        $user = $this->createUser([
            'two_factor_secret' => encrypt('query-run-test-secret'),
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

        $this->grantQueryTestSiteMembership($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(array $attributes = []): User
    {
        $user = User::factory()->make($attributes);
        $user->setConnection(config('database.default'));
        $user->save();

        return $user;
    }

    private function createMinimalDocxTemplate(string $path, string $bodyText): void
    {
        @mkdir(dirname($path), 0777, true);

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.$bodyText.'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
    }
}
