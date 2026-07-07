<?php

namespace App\Http\Controllers\Admin\Dev\RwDbDiagram;

use App\Http\Controllers\Controller;
use App\Jobs\Admin\Database\GenerateDatabaseBackupJob;
use App\Models\DatabaseEditorLog;
use App\Models\DatabaseLog;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Database\DatabaseAccessGate;
use App\Support\Database\DatabaseSchemaInspector;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RwDbTableViewController extends Controller
{
    public function __construct(
        private readonly DatabaseSchemaInspector $schemaInspector,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function apiData(Request $request, string $table): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canViewDatabaseContents($request->user())) {
            return response()->json(['error' => __('db_diagram_ui.backend.no_access')], 403);
        }

        $context = $this->resolveTableContext($request, $table);
        if (! is_array($context)) {
            return response()->json(['error' => __('db_diagram_ui.backend.table_not_found')], 404);
        }

        return response()->json($this->buildPaginatedRows(
            request: $request,
            tableName: $context['tableName'],
            columns: $context['columns'],
            columnNames: $context['columnNames'],
            primaryKey: $context['primaryKey'],
        ));
    }

    public function exportSql(Request $request, string $table)
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canExportDatabaseContents($request->user())) {
            return redirect()->route('admin.db-diagram')->with('error', __('db_diagram_ui.backend.no_access_export'));
        }

        $context = $this->resolveTableContext($request, $table);
        if (! is_array($context)) {
            return redirect()->route('admin.db-diagram')->with('error', __('db_diagram_ui.backend.table_not_found'));
        }

        $tableName = (string) $context['tableName'];

        try {
            $createTable = DB::selectOne("SHOW CREATE TABLE `{$tableName}`");
            $sql = "-- SQL Dump for table '{$tableName}'\n";
            $sql .= '-- Generated at: '.now()->toDateTimeString()."\n\n";
            $sql .= ($createTable->{'Create Table'} ?? '').";\n\n";

            $rows = DB::table($tableName)->get();

            if ($rows->isNotEmpty()) {
                $columnNames = array_keys((array) $rows->first());
                $sql .= "INSERT INTO `{$tableName}` (`".implode('`, `', $columnNames)."`) VALUES\n";

                foreach ($rows as $index => $row) {
                    $values = [];
                    foreach ((array) $row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } elseif (is_numeric($value)) {
                            $values[] = $value;
                        } else {
                            $values[] = "'".str_replace(['\\', "'"], ['\\\\', "''"], (string) $value)."'";
                        }
                    }

                    $sql .= '('.implode(', ', $values).')';
                    $sql .= ($index === ($rows->count() - 1)) ? ";\n" : ",\n";
                }
            }

            $this->logEditorAction($request, 'export_table_sql', $tableName, null, [
                'row_count' => $rows->count(),
            ]);

            $filename = "{$tableName}.sql";

            return response($sql, 200, [
                'Content-Type' => 'application/sql',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.db-diagram')
                ->with('error', __('db_diagram_ui.backend.export_failed_with_error', ['error' => $exception->getMessage()]));
        }
    }

    public function startFullBackup(Request $request): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canExportFullDatabase($request->user())) {
            return response()->json(['message' => __('db_diagram_ui.backend.no_access_full_backup')], 403);
        }

        $validated = $request->validate([
            'tables' => ['required', 'array', 'min:1'],
            'tables.*' => ['required', 'string', 'max:128'],
            'project_name' => ['nullable', 'string', 'max:64'],
        ]);

        $allowedTables = $this->getScopedViewableTables($request);
        $selectedTables = array_values(array_filter($validated['tables'], static function (mixed $table) use ($allowedTables): bool {
            return in_array((string) $table, $allowedTables, true);
        }));

        if ($selectedTables === []) {
            return response()->json(['message' => __('db_diagram_ui.backend.no_valid_tables_selected')], 422);
        }

        $backup = DatabaseLog::query()->create([
            'user_id' => $request->user()?->id,
            'project_name' => (string) ($validated['project_name'] ?? 'rwsoft'),
            'status' => 'pending',
            'selected_tables' => $selectedTables,
            'log_details' => [],
        ]);

        GenerateDatabaseBackupJob::dispatch((int) $backup->id, (int) TenantContext::siteId());

        $this->logEditorAction($request, 'start_full_backup', '__backup__', (string) $backup->id, [
            'tables' => $selectedTables,
        ]);

        return response()->json([
            'backup_id' => $backup->id,
            'message' => __('db_diagram_ui.backend.backup_started'),
        ]);
    }

    public function getBackupStatus(Request $request, int $id): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canExportFullDatabase($request->user())) {
            return response()->json(['message' => __('db_diagram_ui.backend.no_access')], 403);
        }

        $backup = DatabaseLog::query()->findOrFail($id);

        if ($backup->user_id && $backup->user_id !== $request->user()?->id) {
            return response()->json(['message' => __('db_diagram_ui.backend.unauthorized')], 403);
        }

        return response()->json([
            'status' => $backup->status,
            'filename' => $backup->filename,
            'error_message' => $backup->error_message,
            'file_size_kb' => $backup->file_size_kb,
            'log_details' => $backup->log_details,
        ]);
    }

    public function downloadBackup(Request $request, int $id)
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canExportFullDatabase($request->user())) {
            abort(403);
        }

        $backup = DatabaseLog::query()->findOrFail($id);

        if ($backup->user_id && $backup->user_id !== $request->user()?->id) {
            abort(403);
        }

        if ($backup->status !== 'completed') {
            abort(403, __('db_diagram_ui.backend.backup_not_completed'));
        }

        $disk = 'private';
        $path = (string) $backup->filename;

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            abort(404, __('db_diagram_ui.backend.storage_file_not_found'));
        }

        if (config("filesystems.disks.{$disk}.driver") === 's3') {
            return redirect()->away(Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(10)));
        }

        return Storage::disk($disk)->download($path);
    }

    public function createForm(Request $request, string $table): Response|RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canAddDatabaseContents($request->user())) {
            return redirect()->route('admin.db-diagram')->with('error', __('db_diagram_ui.backend.no_access_add'));
        }

        $context = $this->resolveTableContext($request, $table);
        if (! is_array($context)) {
            return redirect()->route('admin.db-diagram')->with('error', __('db_diagram_ui.backend.table_not_found'));
        }

        if ($this->schemaInspector->isEditBlocked($context['tableName'])) {
            return redirect()->route('admin.db-diagram')->with('error', __('db_diagram_ui.backend.table_not_editable'));
        }

        $formFields = $this->schemaInspector->getFormColumns($context['tableName'], $context['columns'], $context['primaryKey']);
        $formValues = [];
        foreach ($formFields as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $formValues[$key] = $field['default'] ?? null;
        }

        return Inertia::render('Admin/RwDbDiagram/RwDbTableForm', [
            'tableName' => $context['tableName'],
            'primaryKey' => $context['primaryKey'],
            'mode' => 'create',
            'recordKey' => null,
            'warningMessage' => __('db_diagram_ui.backend.direct_table_edit_warning'),
            'formFields' => $formFields,
            'formValues' => $formValues,
            'relationshipOptions' => $this->schemaInspector->getRelationshipOptions($context['tableName'], $context['primaryKey']),
            'backRoute' => route('admin.db-diagram'),
            'submitRoute' => route('admin.db-diagram.table-store', ['table' => $context['tableName']]),
        ]);
    }

    public function editForm(Request $request, string $table, mixed $id): Response|RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canEditDatabaseContents($request->user())) {
            return redirect()->route('admin.db-diagram')->with('error', __('db_diagram_ui.backend.no_access_edit'));
        }

        $context = $this->resolveTableContext($request, $table);
        if (! is_array($context)) {
            return redirect()->route('admin.db-diagram')->with('error', __('db_diagram_ui.backend.table_not_found'));
        }

        if ($this->schemaInspector->isEditBlocked($context['tableName'])) {
            return redirect()->route('admin.db-diagram')->with('error', __('db_diagram_ui.backend.table_not_editable'));
        }

        $record = DB::table($context['tableName'])->where($context['primaryKey'], $id)->first();
        if (! $record) {
            return redirect()->route('admin.db-diagram')->with('error', __('db_diagram_ui.backend.record_not_found'));
        }

        $formFields = $this->schemaInspector->getFormColumns($context['tableName'], $context['columns'], $context['primaryKey']);
        $formValues = [];

        foreach ($formFields as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $formValues[$key] = $record->{$key} ?? null;
        }

        return Inertia::render('Admin/RwDbDiagram/RwDbTableForm', [
            'tableName' => $context['tableName'],
            'primaryKey' => $context['primaryKey'],
            'mode' => 'edit',
            'recordKey' => $id,
            'warningMessage' => __('db_diagram_ui.backend.direct_table_edit_warning'),
            'formFields' => $formFields,
            'formValues' => $formValues,
            'relationshipOptions' => $this->schemaInspector->getRelationshipOptions($context['tableName'], $context['primaryKey']),
            'backRoute' => route('admin.db-diagram'),
            'submitRoute' => route('admin.db-diagram.table-update-form', ['table' => $context['tableName'], 'id' => $id]),
        ]);
    }

    public function store(Request $request, string $table): RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canAddDatabaseContents($request->user())) {
            return redirect()->back()->with('error', __('db_diagram_ui.backend.no_access_add'));
        }

        return $this->storeOrUpdateFromForm($request, $table);
    }

    public function updateForm(Request $request, string $table, mixed $id): RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canEditDatabaseContents($request->user())) {
            return redirect()->back()->with('error', __('db_diagram_ui.backend.no_access_edit'));
        }

        return $this->storeOrUpdateFromForm($request, $table, $id);
    }

    public function update(Request $request, string $table, mixed $id): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canEditDatabaseContents($request->user())) {
            return response()->json(['message' => __('db_diagram_ui.backend.no_access')], 403);
        }

        $context = $this->resolveTableContext($request, $table);
        if (! is_array($context)) {
            return response()->json(['message' => __('db_diagram_ui.backend.table_not_found')], 404);
        }

        if ($this->schemaInspector->isEditBlocked($context['tableName'])) {
            return response()->json(['message' => __('db_diagram_ui.backend.table_not_editable')], 422);
        }

        $field = (string) $request->input('field');
        $value = $request->input('value');

        if (! in_array($field, $context['columnNames'], true)) {
            return response()->json(['message' => __('db_diagram_ui.backend.unknown_field')], 422);
        }

        $nonEditable = $this->schemaInspector->getNonEditableColumns($context['tableName'], $context['columns'], $context['primaryKey']);
        if (in_array($field, $nonEditable, true)) {
            return response()->json(['message' => __('db_diagram_ui.backend.field_not_editable')], 422);
        }

        $oldRecord = DB::table($context['tableName'])->where($context['primaryKey'], $id)->first();
        if (! $oldRecord) {
            return response()->json(['message' => __('db_diagram_ui.backend.record_not_found')], 404);
        }

        DB::table($context['tableName'])->where($context['primaryKey'], $id)->update([$field => $value]);

        $this->logEditorAction($request, 'inline_update', $context['tableName'], (string) $id, [
            'field' => $field,
            'old_value' => $oldRecord->{$field} ?? null,
            'new_value' => $value,
        ]);

        return response()->json(['message' => __('db_diagram_ui.backend.field_updated')]);
    }

    public function analyzeUpdate(Request $request, string $table): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canEditDatabaseContents($request->user())) {
            return response()->json(['message' => __('db_diagram_ui.backend.no_access')], 403);
        }

        $context = $this->resolveTableContext($request, $table);
        if (! is_array($context)) {
            return response()->json(['message' => __('db_diagram_ui.backend.table_not_found')], 404);
        }

        return response()->json([
            'requiresConfirmation' => false,
            'databaseRelations' => ['incoming' => [], 'outgoing' => []],
            'modelRelations' => [],
        ]);
    }

    public function analyzeDelete(Request $request, string $table, mixed $id): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canDeleteDatabaseContents($request->user())) {
            return response()->json(['message' => __('db_diagram_ui.backend.no_access')], 403);
        }

        $context = $this->resolveTableContext($request, $table);
        if (! is_array($context)) {
            return response()->json(['message' => __('db_diagram_ui.backend.table_not_found')], 404);
        }

        $relations = $this->schemaInspector->getRecordRelations($context['tableName'], $context['primaryKey'], $id);
        $incoming = is_array($relations['database']['incoming'] ?? null) ? $relations['database']['incoming'] : [];
        $requiresConfirmation = collect($incoming)->contains(static function (array $relation): bool {
            return (int) ($relation['affected_rows'] ?? 0) > 0;
        });

        return response()->json([
            'requiresConfirmation' => $requiresConfirmation,
            'recordId' => $id,
            'databaseRelations' => $relations['database'],
            'modelRelations' => $relations['models'],
        ]);
    }

    public function destroy(Request $request, string $table, mixed $id): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canDeleteDatabaseContents($request->user())) {
            return response()->json(['message' => __('db_diagram_ui.backend.no_access')], 403);
        }

        $context = $this->resolveTableContext($request, $table);
        if (! is_array($context)) {
            return response()->json(['message' => __('db_diagram_ui.backend.table_not_found')], 404);
        }

        if ($this->schemaInspector->isEditBlocked($context['tableName'])) {
            return response()->json(['message' => __('db_diagram_ui.backend.table_not_editable')], 422);
        }

        $record = DB::table($context['tableName'])->where($context['primaryKey'], $id)->first();
        if (! $record) {
            return response()->json(['message' => __('db_diagram_ui.backend.record_not_found')], 404);
        }

        $relations = $this->schemaInspector->getRecordRelations($context['tableName'], $context['primaryKey'], $id);
        $requiresConfirmation = collect($relations['database']['incoming'] ?? [])->contains(static function (array $relation): bool {
            return (int) ($relation['affected_rows'] ?? 0) > 0;
        });

        if ($requiresConfirmation && ! (bool) $request->input('relation_confirmed', false)) {
            return response()->json(['message' => __('db_diagram_ui.backend.relation_confirmation_required')], 422);
        }

        DB::table($context['tableName'])->where($context['primaryKey'], $id)->delete();

        $this->logEditorAction($request, 'delete_record', $context['tableName'], (string) $id, [
            'data' => (array) $record,
            'relations_confirmed' => (bool) $request->input('relation_confirmed', false),
        ]);

        return response()->json(['message' => __('db_diagram_ui.backend.record_deleted')]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, string>  $columnNames
     * @return array{data: array<int, array<string, mixed>>, total: int, current_page: int, last_page: int, per_page: int}
     */
    private function buildPaginatedRows(
        Request $request,
        string $tableName,
        array $columns,
        array $columnNames,
        string $primaryKey,
    ): array {
        $rowsPerPage = $this->resolveRowsPerPage($request);
        $sortField = (string) $request->query('sortField', $primaryKey);
        $sortOrder = strtolower((string) $request->query('sortOrder', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (! in_array($sortField, $columnNames, true)) {
            $sortField = in_array($primaryKey, $columnNames, true) ? $primaryKey : ($columnNames[0] ?? $primaryKey);
        }

        $query = DB::table($tableName);
        $this->applyGlobalSearch($query, $columns, trim((string) $request->query('global', '')));
        $this->applyColumnFilters($query, $request, $columnNames);

        $query->orderBy($sortField, $sortOrder);

        $page = max((int) $request->query('page', 1), 1);
        $rows = $query->paginate($rowsPerPage, ['*'], 'page', $page)->withQueryString();

        return [
            'data' => array_map(static fn (mixed $row): array => (array) $row, $rows->items()),
            'total' => $rows->total(),
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'per_page' => $rows->perPage(),
        ];
    }

    private function resolveRowsPerPage(Request $request): int
    {
        $perPage = (int) $request->query('rowsPerPage', 25);

        return in_array($perPage, [10, 25, 50, 75, 100], true) ? $perPage : 25;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    private function applyGlobalSearch(Builder $query, array $columns, string $global): void
    {
        if ($global === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($columns, $global): void {
            foreach ($columns as $column) {
                $name = (string) ($column['name'] ?? '');
                $type = strtolower((string) ($column['data_type'] ?? ''));
                if ($name === '') {
                    continue;
                }

                if (in_array($type, ['text', 'varchar', 'char', 'tinytext', 'mediumtext', 'longtext'], true)) {
                    $builder->orWhere($name, 'LIKE', '%'.$global.'%');
                }
            }
        });
    }

    /**
     * @param  array<int, string>  $columnNames
     */
    private function applyColumnFilters(Builder $query, Request $request, array $columnNames): void
    {
        $filters = $request->query('filters', []);
        if (is_string($filters)) {
            $decoded = json_decode($filters, true);
            $filters = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($filters)) {
            return;
        }

        foreach ($filters as $field => $filter) {
            if (! in_array((string) $field, $columnNames, true)) {
                continue;
            }

            if (! is_array($filter)) {
                continue;
            }

            $value = $filter['value'] ?? null;
            $mode = (string) ($filter['mode'] ?? '=');

            if ($value === null || $value === '') {
                continue;
            }

            switch ($mode) {
                case 'contains':
                    $query->where((string) $field, 'LIKE', '%'.$value.'%');
                    break;
                case 'not_contains':
                    $query->where((string) $field, 'NOT LIKE', '%'.$value.'%');
                    break;
                case 'starts_with':
                    $query->where((string) $field, 'LIKE', $value.'%');
                    break;
                case 'ends_with':
                    $query->where((string) $field, 'LIKE', '%'.$value);
                    break;
                case 'greater_than':
                    $query->where((string) $field, '>', $value);
                    break;
                case 'less_than':
                    $query->where((string) $field, '<', $value);
                    break;
                case 'between':
                    if (is_array($value) && isset($value['from'], $value['to'])) {
                        $query->whereBetween((string) $field, [$value['from'], $value['to']]);
                    }
                    break;
                default:
                    $query->where((string) $field, $mode, $value);
            }
        }
    }

    /**
     * @return array{tableName: string, columns: array<int, array<string, mixed>>, columnNames: array<int, string>, primaryKey: string}|null
     */
    private function resolveTableContext(Request $request, string $table): ?array
    {
        $normalizedTable = $this->schemaInspector->normalizeTableName($table);
        if (! $normalizedTable) {
            return null;
        }

        $viewableTables = $this->getScopedViewableTables($request);
        if (! in_array($normalizedTable, $viewableTables, true)) {
            return null;
        }

        $columns = $this->schemaInspector->getTableColumns($normalizedTable);
        $columnNames = array_values(array_map(static fn (array $column): string => (string) ($column['name'] ?? ''), $columns));
        $primaryKey = $this->schemaInspector->getPrimaryKey($normalizedTable) ?? 'id';

        return [
            'tableName' => $normalizedTable,
            'columns' => $columns,
            'columnNames' => $columnNames,
            'primaryKey' => $primaryKey,
        ];
    }

    private function storeOrUpdateFromForm(Request $request, string $table, mixed $id = null): RedirectResponse
    {
        $context = $this->resolveTableContext($request, $table);
        if (! is_array($context)) {
            return redirect()->back()->with('error', __('db_diagram_ui.backend.table_not_found'));
        }

        if ($this->schemaInspector->isEditBlocked($context['tableName'])) {
            return redirect()->back()->with('error', __('db_diagram_ui.backend.table_not_editable'));
        }

        $isCreate = $id === null;
        $formFields = $this->schemaInspector->getFormColumns($context['tableName'], $context['columns'], $context['primaryKey']);
        $allowedKeys = array_values(array_map(static fn (array $field): string => (string) $field['key'], $formFields));
        $values = $request->input('values', []);

        if (! is_array($values)) {
            $values = [];
        }

        $payload = [];
        $rules = [];

        foreach ($formFields as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '' || ! in_array($key, $allowedKeys, true)) {
                continue;
            }

            $rawValue = $values[$key] ?? null;

            if (($field['type'] ?? '') === 'boolean') {
                $rawValue = filter_var($rawValue, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                $rawValue = $rawValue ?? false;
            }

            if ($rawValue === '' && (bool) ($field['nullable'] ?? false)) {
                $rawValue = null;
            }

            $payload[$key] = $rawValue;

            $validationRule = trim((string) ($field['validation_rule'] ?? ''));
            $fieldRules = collect(explode('|', $validationRule))
                ->map(static fn (string $entry): string => trim($entry))
                ->filter(static fn (string $entry): bool => $entry !== '')
                ->values()
                ->all();

            if ($fieldRules === []) {
                $fieldRules[] = (bool) ($field['required'] ?? false) ? 'required' : 'nullable';

                $type = (string) ($field['type'] ?? 'text');
                if ($type === 'number') {
                    $fieldRules[] = 'numeric';
                } elseif ($type === 'boolean') {
                    $fieldRules[] = 'boolean';
                } elseif ($type === 'date') {
                    $fieldRules[] = 'date';
                } elseif ($type === 'datetime') {
                    $fieldRules[] = 'date';
                } else {
                    $fieldRules[] = 'string';
                    if (is_int($field['max_length'] ?? null) && (int) $field['max_length'] > 0) {
                        $fieldRules[] = 'max:'.(int) $field['max_length'];
                    }
                }
            }

            $rules[$key] = $fieldRules;
        }

        $validator = Validator::make($payload, $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            if ($isCreate) {
                $newId = DB::table($context['tableName'])->insertGetId($payload);

                $this->logEditorAction($request, 'create_record', $context['tableName'], (string) $newId, [
                    'data' => $payload,
                ]);

                return redirect()
                    ->route('admin.db-diagram')
                    ->with('status', __('db_diagram_ui.backend.record_created'));
            }

            $oldRecord = DB::table($context['tableName'])->where($context['primaryKey'], $id)->first();
            if (! $oldRecord) {
                return redirect()
                    ->route('admin.db-diagram')
                    ->with('error', __('db_diagram_ui.backend.record_not_found'));
            }

            DB::table($context['tableName'])->where($context['primaryKey'], $id)->update($payload);

            $this->logEditorAction($request, 'update_record', $context['tableName'], (string) $id, [
                'old' => (array) $oldRecord,
                'new' => $payload,
            ]);

            return redirect()
                ->route('admin.db-diagram')
                ->with('status', __('db_diagram_ui.backend.record_updated'));
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', __('db_diagram_ui.backend.save_failed_with_error', ['error' => $exception->getMessage()]));
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logEditorAction(Request $request, string $action, string $tableName, ?string $recordKey, array $context): void
    {
        DatabaseEditorLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'table_name' => $tableName,
            'record_key' => $recordKey,
            'context' => $context,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->auditLogger->success(
            action: $this->mapAuditAction($action),
            module: 'db_diagram',
            subjectType: $this->mapAuditSubjectType($action),
            subjectKey: $recordKey ? $tableName.':'.$recordKey : $tableName,
            message: __('db_diagram_ui.audit.editor_action_executed', ['action' => $action]),
            meta: [
                'legacy_action' => $action,
                'table_name' => $tableName,
                'record_key' => $recordKey,
                'context' => $context,
            ],
            request: $request,
        );
    }

    private function mapAuditAction(string $legacyAction): string
    {
        return match ($legacyAction) {
            'create_record' => 'db.record.create',
            'update_record', 'inline_update' => 'db.record.update',
            'delete_record' => 'db.record.delete',
            'export_table_sql' => 'db.table.export_sql',
            'start_full_backup' => 'db.backup.start',
            default => 'db.'.str_replace('_', '.', trim($legacyAction)),
        };
    }

    private function mapAuditSubjectType(string $legacyAction): string
    {
        return match ($legacyAction) {
            'start_full_backup' => 'backup',
            'export_table_sql' => 'table',
            default => str_contains($legacyAction, 'record') || $legacyAction === 'inline_update'
                ? 'record'
                : 'table',
        };
    }

    /**
     * @return array<int, string>
     */
    private function getScopedViewableTables(Request $request): array
    {
        return $this->schemaInspector->getViewableTables();
    }

    private function isSuperAdmin(mixed $user): bool
    {
        return $user instanceof User && $user->hasRoleKey('super_admin');
    }

    private function canViewDatabaseContents(mixed $user): bool
    {
        return DatabaseAccessGate::canAccess($user, 'admin.db-diagram.table-data', ['view']);
    }

    private function canEditDatabaseContents(mixed $user): bool
    {
        return DatabaseAccessGate::canAccess($user, 'admin.db-diagram.table-edit', ['view', 'edit']);
    }

    private function canAddDatabaseContents(mixed $user): bool
    {
        return DatabaseAccessGate::canAccess($user, 'admin.db-diagram.table-create', ['view', 'edit', 'add']);
    }

    private function canDeleteDatabaseContents(mixed $user): bool
    {
        return DatabaseAccessGate::canAccess($user, 'admin.db-diagram.table-delete', ['view', 'edit', 'delete']);
    }

    private function canExportDatabaseContents(mixed $user): bool
    {
        return DatabaseAccessGate::canAccess($user, 'admin.db-diagram.table-export-sql', ['view', 'export']);
    }

    private function canExportFullDatabase(mixed $user): bool
    {
        return DatabaseAccessGate::canAccess($user, 'admin.db-diagram.backup-full.start', ['view', 'full_backup']);
    }
}
