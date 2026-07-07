<?php

namespace App\Http\Controllers\Admin\Run\Query;

use App\Actions\Admin\Base\Query\ConvertOfficeToPdfAction;
use App\Actions\Admin\Base\Query\RenderQueryDocumentTemplateAction;
use App\Actions\Admin\Base\Query\RenderQuerySpreadsheetTemplateAction;
use App\Actions\Admin\Base\Query\RunSqlQueryAction;
use App\Actions\Admin\Base\Query\ValidateSqlQueryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Run\Query\QueryBindingSourceOptionsRequest;
use App\Http\Requests\Admin\Run\Query\RunQueryDataRequest;
use App\Models\Query\Query;
use App\Models\Query\QueryBuilderSelectTable;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueryRunController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function show(int $query, Request $request): Response
    {
        TenantDatabaseGuard::ensureTenantConnection();
        $query = $this->queryForActiveApplication($query);

        $this->auditLogger->success(
            action: 'run.query.open',
            module: 'query_runner',
            subjectType: 'query',
            subjectKey: (string) $query->id,
            message: __('query_builder_ui.audit.query_run_opened'),
            meta: [
                'query_id' => (int) $query->id,
                'output_mode' => (string) ($query->output_mode ?? 'table'),
            ],
            request: $request,
        );

        return Inertia::render('Admin/Query/QueryRun', [
            'query' => [
                'id' => (int) $query->id,
                'slug' => (string) $query->slug,
                'description' => (string) $query->description,
                'memo' => (string) ($query->memo ?? ''),
                'query_mode' => (string) $query->query_mode,
                'output_mode' => (string) $query->output_mode,
                'report_data_source' => (string) ($query->report_data_source ?? 'query'),
                'report_output_format' => (string) ($query->report_output_format ?? 'same_format'),
                'report_template_filename' => (string) ($query->report_template_filename ?? ''),
                'query' => (string) ($query->query ?? ''),
                'binding_rows' => (array) ($query->binding_rows ?? []),
                'is_active' => (bool) $query->is_active,
                'force_table' => $request->boolean('__force_table'),
            ],
        ]);
    }

    public function data(int $query, RunQueryDataRequest $request): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();
        $query = $this->queryForActiveApplication($query);

        if (! $query->is_active) {
            $this->auditLogger->failure(
                action: 'run.query.data.failed',
                module: 'query_runner',
                subjectType: 'query',
                subjectKey: (string) $query->id,
                message: __('query_builder_ui.audit.query_data_inactive'),
                meta: [
                    'reason' => 'query_inactive',
                ],
                request: $request,
            );

            return response()->json([
                'message' => __('query_builder_ui.runtime.query_inactive'),
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'last_page' => 1,
            ], 422);
        }

        $sql = (string) ($query->query ?? '');
        $validation = ValidateSqlQueryAction::handle($sql);

        if (! (bool) $validation['is_valid']) {
            $this->auditLogger->failure(
                action: 'run.query.data.failed',
                module: 'query_runner',
                subjectType: 'query',
                subjectKey: (string) $query->id,
                message: __('query_builder_ui.audit.query_data_sql_invalid'),
                meta: [
                    'reason' => 'sql_invalid',
                    'validation_message' => (string) ($validation['message'] ?? ''),
                ],
                request: $request,
            );

            return response()->json([
                'message' => (string) ($validation['message'] ?? __('query_builder_ui.runtime.query_invalid')),
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'last_page' => 1,
            ], 422);
        }

        $validated = $request->validated();
        $requiredBindings = (array) ($validation['bindings'] ?? []);
        $payloadBindings = (array) ($validated['bindings'] ?? []);
        $missingBindings = $this->missingBindings($requiredBindings, $payloadBindings, $request);

        if ($missingBindings !== []) {
            $this->auditLogger->failure(
                action: 'run.query.data.failed',
                module: 'query_runner',
                subjectType: 'query',
                subjectKey: (string) $query->id,
                message: __('query_builder_ui.audit.query_data_missing_bindings'),
                meta: [
                    'reason' => 'missing_bindings',
                    'missing_bindings' => $missingBindings,
                ],
                request: $request,
            );

            return response()->json([
                'message' => __('query_builder_ui.runtime.missing_bindings_with_list', [
                    'bindings' => implode(', ', $missingBindings),
                ]),
                'missing_bindings' => $missingBindings,
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'last_page' => 1,
            ], 422);
        }

        $bindings = $this->resolveBindings(
            $requiredBindings,
            $payloadBindings,
            $request,
        );

        try {
            $result = RunSqlQueryAction::handle(
                (string) ($validation['sql'] ?? ''),
                $bindings,
                (int) ($validated['page'] ?? 1),
                (int) ($validated['rowsPerPage'] ?? 25),
                isset($validated['sortField']) ? (string) $validated['sortField'] : null,
                (string) ($validated['sortOrder'] ?? 'asc'),
                isset($validated['global']) ? (string) $validated['global'] : null,
                (array) ($validated['filters'] ?? []),
                (array) ($validated['filterModes'] ?? []),
                (array) ($validated['filterTypes'] ?? []),
            );
        } catch (QueryException $exception) {
            $this->auditLogger->failure(
                action: 'run.query.data.failed',
                module: 'query_runner',
                subjectType: 'query',
                subjectKey: (string) $query->id,
                message: __('query_builder_ui.audit.query_data_exception'),
                meta: [
                    'reason' => 'query_exception',
                    'error' => trim($exception->getMessage()),
                ],
                request: $request,
            );

            return response()->json([
                'message' => __('query_builder_ui.runtime.query_failed_with_error', [
                    'error' => $exception->getMessage(),
                ]),
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'last_page' => 1,
            ], 422);
        }

        $this->auditLogger->success(
            action: 'run.query.data',
            module: 'query_runner',
            subjectType: 'query',
            subjectKey: (string) $query->id,
            message: __('query_builder_ui.audit.query_data_loaded'),
            meta: [
                'rows' => count((array) ($result['data'] ?? [])),
                'total' => (int) ($result['total'] ?? 0),
                'columns' => count((array) ($result['columns'] ?? [])),
            ],
            request: $request,
        );

        return response()->json([
            'data' => (array) ($result['data'] ?? []),
            'total' => (int) ($result['total'] ?? 0),
            'current_page' => (int) ($result['current_page'] ?? 1),
            'last_page' => (int) ($result['last_page'] ?? 1),
            'columns' => (array) ($result['columns'] ?? []),
            'truncated' => (bool) ($result['truncated'] ?? false),
        ]);
    }

    public function bindingSourceOptions(QueryBindingSourceOptionsRequest $request): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $validated = $request->validated();
        $source = QueryBuilderSelectTable::query()
            ->where('is_active', true)
            ->find((int) $validated['source_table_id']);

        if (! $source) {
            $this->auditLogger->failure(
                action: 'run.query.binding_source.options.failed',
                module: 'query_runner',
                subjectType: 'query_binding_source',
                subjectKey: (string) ((int) $validated['source_table_id']),
                message: __('query_builder_ui.audit.binding_source_not_found'),
                meta: [
                    'reason' => 'source_not_found',
                ],
                request: $request,
            );

            return response()->json([
                'message' => __('query_builder_ui.runtime.binding_source_not_found'),
                'options' => [],
            ], 404);
        }

        $tableName = trim((string) $source->table_name);
        $selectField = trim((string) $source->select_field);
        $labelFields = collect((array) ($source->label_fields ?? []))
            ->map(static fn (mixed $field): string => trim((string) $field))
            ->filter(static fn (string $field): bool => $field !== '')
            ->values()
            ->all();
        $searchFields = collect((array) ($source->search_fields ?? []))
            ->map(static fn (mixed $field): string => trim((string) $field))
            ->filter(static fn (string $field): bool => $field !== '')
            ->values()
            ->all();

        if ($tableName === '' || $selectField === '' || $labelFields === []) {
            $this->auditLogger->failure(
                action: 'run.query.binding_source.options.failed',
                module: 'query_runner',
                subjectType: 'query_binding_source',
                subjectKey: (string) $source->id,
                message: __('query_builder_ui.audit.binding_source_incomplete'),
                meta: [
                    'reason' => 'source_incomplete',
                ],
                request: $request,
            );

            return response()->json([
                'message' => __('query_builder_ui.runtime.binding_source_incomplete'),
                'options' => [],
            ], 422);
        }

        if (! Schema::hasTable($tableName)) {
            $this->auditLogger->failure(
                action: 'run.query.binding_source.options.failed',
                module: 'query_runner',
                subjectType: 'query_binding_source',
                subjectKey: (string) $source->id,
                message: __('query_builder_ui.audit.binding_source_invalid_table'),
                meta: [
                    'reason' => 'table_not_found',
                    'table_name' => $tableName,
                ],
                request: $request,
            );

            return response()->json([
                'message' => __('query_builder_ui.runtime.binding_source_invalid_table'),
                'options' => [],
            ], 422);
        }

        $requiredColumns = collect([$selectField, ...$labelFields, ...$searchFields])
            ->unique()
            ->values()
            ->all();

        foreach ($requiredColumns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                $this->auditLogger->failure(
                    action: 'run.query.binding_source.options.failed',
                    module: 'query_runner',
                    subjectType: 'query_binding_source',
                    subjectKey: (string) $source->id,
                    message: __('query_builder_ui.audit.binding_source_invalid_columns'),
                    meta: [
                        'reason' => 'column_not_found',
                        'table_name' => $tableName,
                    ],
                    request: $request,
                );

                return response()->json([
                    'message' => __('query_builder_ui.runtime.binding_source_invalid_columns'),
                    'options' => [],
                ], 422);
            }
        }

        $safeLimit = max(1, min(100, (int) ($validated['limit'] ?? 50)));
        $search = trim((string) ($validated['q'] ?? ''));

        $query = DB::table($tableName)->select($requiredColumns);

        if ($search !== '') {
            $columnsForSearch = $searchFields !== [] ? $searchFields : $labelFields;

            $query->where(function (Builder $builder) use ($columnsForSearch, $search): void {
                foreach ($columnsForSearch as $index => $column) {
                    if ($index === 0) {
                        $builder->where($column, 'like', "%{$search}%");

                        continue;
                    }

                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $this->applySourceSort($query, $tableName, $source->default_sort, $labelFields, $selectField);

        $rows = $query
            ->limit($safeLimit)
            ->get();

        $options = $rows->map(function (object $row) use ($selectField, $labelFields): array {
            $value = $row->{$selectField} ?? null;
            $label = collect($labelFields)
                ->map(static fn (string $column): string => trim((string) ($row->{$column} ?? '')))
                ->filter(static fn (string $text): bool => $text !== '')
                ->implode(' - ');

            return [
                'value' => $value,
                'label' => $label !== '' ? $label : (string) ($value ?? ''),
            ];
        })
            ->filter(static fn (array $option): bool => $option['value'] !== null)
            ->values()
            ->all();

        $this->auditLogger->success(
            action: 'run.query.binding_source.options',
            module: 'query_runner',
            subjectType: 'query_binding_source',
            subjectKey: (string) $source->id,
            message: __('query_builder_ui.audit.binding_source_options_loaded'),
            meta: [
                'options_count' => count($options),
                'limit' => $safeLimit,
                'table_name' => $tableName,
            ],
            request: $request,
        );

        return response()->json([
            'options' => $options,
        ]);
    }

    public function export(int $query, Request $request): StreamedResponse|RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();
        $query = $this->queryForActiveApplication($query);

        if ((string) $query->output_mode !== 'excel') {
            return $this->redirectToRunWithError(
                query: $query,
                request: $request,
                message: __('query_builder_ui.runtime.not_excel_output'),
                action: 'run.query.export.failed',
                reason: 'invalid_output_mode',
            );
        }

        $execution = $this->prepareDownloadExecution($query, $request, 'run.query.export');

        if ($execution instanceof RedirectResponse) {
            return $execution;
        }

        $this->auditLogger->success(
            action: 'run.query.export',
            module: 'query_runner',
            subjectType: 'query',
            subjectKey: (string) $query->id,
            message: __('query_builder_ui.audit.query_export_download_started'),
            meta: [
                'output_mode' => (string) $query->output_mode,
                'rows' => count((array) ($execution['data'] ?? [])),
                'columns' => count((array) ($execution['columns'] ?? [])),
            ],
            request: $request,
        );

        return $this->streamCsvDownload(
            $execution['data'],
            $execution['columns'],
            $this->downloadFilename($query, 'excel', 'csv'),
        );
    }

    public function report(int $query, Request $request): StreamedResponse|RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();
        $query = $this->queryForActiveApplication($query);

        if ((string) $query->output_mode !== 'report') {
            return $this->redirectToRunWithError(
                query: $query,
                request: $request,
                message: __('query_builder_ui.runtime.not_report_output'),
                action: 'run.query.report.failed',
                reason: 'invalid_output_mode',
            );
        }

        if ((string) ($query->report_data_source ?? 'query') === 'external') {
            return $this->redirectToRunWithError(
                query: $query,
                request: $request,
                message: __('query_builder_ui.runtime.external_report'),
                action: 'run.query.report.failed',
                reason: 'external_data_source',
            );
        }

        $reportOutputFormat = (string) ($query->report_output_format ?? 'same_format');

        if (in_array($reportOutputFormat, ['same_format', 'pdf'], true)) {
            return $this->renderReportSpreadsheetDownload($query, $request, $reportOutputFormat);
        }

        if ($reportOutputFormat !== 'csv') {
            return $this->redirectToRunWithError(
                query: $query,
                request: $request,
                message: __('query_builder_ui.runtime.unknown_report_output_format'),
                action: 'run.query.report.failed',
                reason: 'invalid_report_output_format',
            );
        }

        $execution = $this->prepareDownloadExecution($query, $request, 'run.query.report');

        if ($execution instanceof RedirectResponse) {
            return $execution;
        }

        $this->auditLogger->success(
            action: 'run.query.report',
            module: 'query_runner',
            subjectType: 'query',
            subjectKey: (string) $query->id,
            message: __('query_builder_ui.audit.query_report_download_started'),
            meta: [
                'report_output_format' => 'csv',
                'rows' => count((array) ($execution['data'] ?? [])),
                'columns' => count((array) ($execution['columns'] ?? [])),
            ],
            request: $request,
        );

        return $this->streamCsvDownload(
            $execution['data'],
            $execution['columns'],
            $this->downloadFilename($query, 'report', 'csv'),
        );
    }

    /**
     * @param  array<int, string>  $requiredBindings
     * @param  array<string, mixed>  $payloadBindings
     * @return array<string, mixed>
     */
    private function resolveBindings(array $requiredBindings, array $payloadBindings, Request $request): array
    {
        $resolved = [];

        foreach ($requiredBindings as $bindingKey) {
            $key = (string) $bindingKey;

            if (array_key_exists($key, $payloadBindings)) {
                $resolved[$key] = $payloadBindings[$key];

                continue;
            }

            $queryValue = $request->query($key);

            if (! $this->isMissingValue($queryValue)) {
                $resolved[$key] = $queryValue;

                continue;
            }

            $systemValue = $this->resolveSystemBindingValue($key, $request);

            if (! $this->isMissingValue($systemValue)) {
                $resolved[$key] = $systemValue;

                continue;
            }

            $resolved[$key] = null;
        }

        return $resolved;
    }

    /**
     * @param  array<int, string>  $requiredBindings
     * @param  array<string, mixed>  $payloadBindings
     * @return array<int, string>
     */
    private function missingBindings(array $requiredBindings, array $payloadBindings, Request $request): array
    {
        return collect($requiredBindings)
            ->map(static fn (string $binding): string => trim($binding))
            ->filter(static fn (string $binding): bool => $binding !== '')
            ->unique()
            ->values()
            ->filter(function (string $binding) use ($payloadBindings, $request): bool {
                if (array_key_exists($binding, $payloadBindings) && ! $this->isMissingValue($payloadBindings[$binding])) {
                    return false;
                }

                if (! $this->isMissingValue($request->query($binding))) {
                    return false;
                }

                return $this->isMissingValue($this->resolveSystemBindingValue($binding, $request));
            })
            ->values()
            ->all();
    }

    private function resolveSystemBindingValue(string $binding, Request $request): mixed
    {
        $normalized = strtoupper(trim($binding));

        if ($normalized === 'CURRENTSCHOOLYEAR') {
            return (int) now()->format('Y');
        }

        if (in_array($normalized, ['USERSCHOOLIDS', 'USERWISASCHOOLIDS', 'USERWISAVIRTSCHOOLIDS'], true)) {
            $fromQuery = $request->query($binding);

            if (is_array($fromQuery)) {
                return collect($fromQuery)
                    ->map(static fn (mixed $value): string => trim((string) $value))
                    ->filter(static fn (string $value): bool => $value !== '')
                    ->implode(',');
            }

            if (! $this->isMissingValue($fromQuery)) {
                return trim((string) $fromQuery);
            }

            return null;
        }

        return null;
    }

    private function isMissingValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }

    /**
     * @param  array<int, mixed>|null  $defaultSort
     * @param  array<int, string>  $labelFields
     */
    private function applySourceSort(Builder $query, string $tableName, ?array $defaultSort, array $labelFields, string $selectField): void
    {
        $sortField = trim((string) ($defaultSort['field'] ?? ''));
        $sortDirection = strtolower(trim((string) ($defaultSort['direction'] ?? 'asc')));

        if ($sortField !== '' && Schema::hasColumn($tableName, $sortField)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');

            return;
        }

        if ($labelFields !== []) {
            $query->orderBy($labelFields[0], 'asc');

            return;
        }

        $query->orderBy($selectField, 'asc');
    }

    /**
     * @return array{data:array<int, array<string, mixed>>,columns:array<int, string>}|RedirectResponse
     */
    private function prepareDownloadExecution(Query $query, Request $request, string $action): array|RedirectResponse
    {
        if (! $query->is_active) {
            return $this->redirectToRunWithError(
                query: $query,
                request: $request,
                message: __('query_builder_ui.runtime.query_inactive'),
                action: $action.'.failed',
                reason: 'query_inactive',
            );
        }

        $sql = (string) ($query->query ?? '');
        $validation = ValidateSqlQueryAction::handle($sql);

        if (! (bool) $validation['is_valid']) {
            return $this->redirectToRunWithError(
                query: $query,
                request: $request,
                message: (string) ($validation['message'] ?? __('query_builder_ui.runtime.query_invalid')),
                action: $action.'.failed',
                reason: 'sql_invalid',
            );
        }

        $requiredBindings = (array) ($validation['bindings'] ?? []);
        $missingBindings = $this->missingBindings($requiredBindings, [], $request);

        if ($missingBindings !== []) {
            return $this->redirectToRunWithError(
                query: $query,
                request: $request,
                message: __('query_builder_ui.runtime.missing_bindings_with_list', [
                    'bindings' => implode(', ', $missingBindings),
                ]),
                action: $action.'.failed',
                reason: 'missing_bindings',
                meta: [
                    'missing_bindings' => $missingBindings,
                ],
            );
        }

        $bindings = $this->resolveBindings($requiredBindings, [], $request);

        try {
            $result = RunSqlQueryAction::handleAll(
                (string) ($validation['sql'] ?? ''),
                $bindings,
            );
        } catch (QueryException $exception) {
            return $this->redirectToRunWithError(
                query: $query,
                request: $request,
                message: __('query_builder_ui.runtime.query_failed_with_error', [
                    'error' => $exception->getMessage(),
                ]),
                action: $action.'.failed',
                reason: 'query_exception',
                meta: [
                    'error' => trim($exception->getMessage()),
                ],
            );
        }

        return [
            'data' => (array) ($result['data'] ?? []),
            'columns' => (array) ($result['columns'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $columns
     */
    private function streamCsvDownload(array $rows, array $columns, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $columns): void {
            $output = fopen('php://output', 'wb');

            if (! is_resource($output)) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");

            if ($columns !== []) {
                fputcsv($output, $columns, ';');
            }

            foreach ($rows as $row) {
                $line = [];

                foreach ($columns as $column) {
                    $line[] = $row[$column] ?? null;
                }

                fputcsv($output, $line, ';');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function queryForActiveApplication(int $queryId): Query
    {
        return Query::query()->findOrFail($queryId);
    }

    private function downloadFilename(Query $query, string $suffix, string $extension): string
    {
        $base = Str::slug((string) ($query->slug ?: $query->description ?: 'query'));

        if ($base === '') {
            $base = 'query';
        }

        return sprintf('%s-%s-%s.%s', $base, $suffix, now()->format('Ymd-His'), $extension);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function redirectToRunWithError(
        Query $query,
        Request $request,
        string $message,
        string $action = 'run.query.failed',
        string $reason = 'unknown',
        array $meta = [],
    ): RedirectResponse {
        $this->auditLogger->failure(
            action: $action,
            module: 'query_runner',
            subjectType: 'query',
            subjectKey: (string) $query->id,
            message: $message,
            meta: array_merge([
                'reason' => $reason,
                'query_id' => (int) $query->id,
            ], $meta),
            request: $request,
        );

        return redirect()
            ->route('admin.run.queries.show', ['query' => $query->id])
            ->with('error', $message);
    }

    private function renderReportSpreadsheetDownload(
        Query $query,
        Request $request,
        string $reportOutputFormat,
    ): StreamedResponse|RedirectResponse {
        $templatePath = trim((string) ($query->report_template_path ?? ''));

        if ($templatePath === '' || ! Storage::disk('private')->exists($templatePath)) {
            return $this->redirectToRunWithError(
                query: $query,
                request: $request,
                message: __('query_builder_ui.runtime.template_missing'),
                action: 'run.query.report.failed',
                reason: 'template_missing',
            );
        }

        $extension = strtolower(trim((string) ($query->report_template_extension ?? '')));

        if ($extension === '') {
            $extension = strtolower((string) pathinfo($templatePath, PATHINFO_EXTENSION));
        }

        if (! in_array($extension, ['xlsx', 'ods', 'docx', 'odt'], true)) {
            return $this->redirectToRunWithError(
                query: $query,
                request: $request,
                message: __('query_builder_ui.runtime.unsupported_template_format'),
                action: 'run.query.report.failed',
                reason: 'unsupported_template_extension',
                meta: [
                    'template_extension' => $extension,
                ],
            );
        }

        $execution = $this->prepareDownloadExecution($query, $request, 'run.query.report');

        if ($execution instanceof RedirectResponse) {
            return $execution;
        }

        $temporaryDirectory = storage_path('app/private/query-reports/render/'.(string) Str::uuid());
        File::ensureDirectoryExists($temporaryDirectory);

        try {
            $templateAbsolutePath = Storage::disk('private')->path($templatePath);
            $renderedPath = $temporaryDirectory.'/rendered.'.$extension;
            if (in_array($extension, ['xlsx', 'ods'], true)) {
                RenderQuerySpreadsheetTemplateAction::handle(
                    $templateAbsolutePath,
                    $renderedPath,
                    [
                        'first' => $execution['data'][0] ?? [],
                        'data' => $execution['data'],
                    ],
                );
            } else {
                RenderQueryDocumentTemplateAction::handle(
                    $templateAbsolutePath,
                    $renderedPath,
                    [
                        'first' => $execution['data'][0] ?? [],
                        'data' => $execution['data'],
                    ],
                );
            }

            $downloadPath = $renderedPath;
            $downloadExtension = $extension;

            if ($reportOutputFormat === 'pdf') {
                $downloadPath = ConvertOfficeToPdfAction::handle($renderedPath, $temporaryDirectory);
                $downloadExtension = 'pdf';
            }

            $this->auditLogger->success(
                action: 'run.query.report',
                module: 'query_runner',
                subjectType: 'query',
                subjectKey: (string) $query->id,
                message: __('query_builder_ui.audit.query_report_download_started'),
                meta: [
                    'report_output_format' => $reportOutputFormat,
                    'download_extension' => $downloadExtension,
                    'rows' => count((array) ($execution['data'] ?? [])),
                    'columns' => count((array) ($execution['columns'] ?? [])),
                ],
                request: $request,
            );

            return $this->streamLocalFileDownload(
                $downloadPath,
                $this->downloadFilename($query, 'report', $downloadExtension),
                $temporaryDirectory,
            );
        } catch (\Throwable $throwable) {
            File::deleteDirectory($temporaryDirectory);

            return $this->redirectToRunWithError(
                query: $query,
                request: $request,
                message: __('query_builder_ui.runtime.report_render_failed_with_error', [
                    'error' => trim($throwable->getMessage()),
                ]),
                action: 'run.query.report.failed',
                reason: 'report_render_exception',
                meta: [
                    'error' => trim($throwable->getMessage()),
                ],
            );
        }
    }

    private function streamLocalFileDownload(
        string $absoluteFilePath,
        string $downloadFilename,
        ?string $cleanupDirectory = null,
    ): StreamedResponse {
        return response()->streamDownload(function () use ($absoluteFilePath, $cleanupDirectory): void {
            $stream = @fopen($absoluteFilePath, 'rb');

            if (! is_resource($stream)) {
                if (is_string($cleanupDirectory) && $cleanupDirectory !== '') {
                    File::deleteDirectory($cleanupDirectory);
                }

                return;
            }

            fpassthru($stream);
            fclose($stream);

            if (is_string($cleanupDirectory) && $cleanupDirectory !== '') {
                File::deleteDirectory($cleanupDirectory);
            }
        }, $downloadFilename);
    }
}
