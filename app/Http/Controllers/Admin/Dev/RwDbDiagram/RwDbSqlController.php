<?php

namespace App\Http\Controllers\Admin\Dev\RwDbDiagram;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dev\RunDbDiagramSqlDestructiveRequest;
use App\Http\Requests\Admin\Dev\RunDbDiagramSqlReadonlyRequest;
use App\Models\DatabaseEditorLog;
use App\Support\Audit\AuditLogger;
use App\Support\Database\DatabaseAccessGate;
use App\Support\Database\DatabaseSchemaInspector;
use App\Support\Database\SqlExecutionGuard;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RwDbSqlController extends Controller
{
    public function __construct(
        private readonly SqlExecutionGuard $sqlExecutionGuard,
        private readonly DatabaseSchemaInspector $schemaInspector,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! $this->canUseSqlEditor($request->user())) {
            return redirect()
                ->route('admin.db-diagram')
                ->with('error', __('db_diagram_ui.sql_editor.errors.no_access'));
        }

        DatabaseEditorLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => 'sql_editor_opened',
            'table_name' => '__sql__',
            'record_key' => null,
            'context' => [
                'can_destructive' => $this->canRunDestructiveSql($request->user()),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->auditLogger->success(
            action: 'db.sql.editor.open',
            module: 'db_diagram',
            subjectType: 'sql',
            subjectKey: '__sql__',
            message: __('db_diagram_ui.audit.sql_editor_opened'),
            meta: [
                'can_destructive' => $this->canRunDestructiveSql($request->user()),
            ],
            request: $request,
        );

        return Inertia::render('Admin/RwDbDiagram/RwDbSqlEditor', [
            'canRunDestructiveSql' => $this->canRunDestructiveSql($request->user()),
            'warningMessage' => __('db_diagram_ui.sql_editor.warning_message'),
            'sqlMetadata' => $this->buildSqlMetadata(),
        ]);
    }

    public function executeReadonly(RunDbDiagramSqlReadonlyRequest $request): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $sql = trim((string) $request->validated('query'));

        $guardResult = $this->sqlExecutionGuard->validateReadonly($sql);
        if (($guardResult['error'] ?? false) === true) {
            $message = (string) ($guardResult['message'] ?? __('db_diagram_ui.sql_editor.errors.sql_blocked'));
            $this->auditBlockedSql($request, $sql, 'readonly', $message);

            throw ValidationException::withMessages([
                'query' => [$message],
            ]);
        }

        $safeSql = (string) ($guardResult['sql'] ?? $sql);
        try {
            $rows = DB::select($safeSql);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'query' => [__('db_diagram_ui.sql_editor.errors.readonly_execute_failed')],
            ]);
        }

        $normalizedRows = array_map(static fn (object $row): array => get_object_vars($row), $rows);
        $columns = $normalizedRows !== []
            ? array_values(array_keys($normalizedRows[0]))
            : [];

        DatabaseEditorLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => 'sql_readonly_run',
            'table_name' => '__sql__',
            'record_key' => null,
            'context' => [
                'statement' => $guardResult['statement'] ?? 'readonly',
                'query' => mb_substr($sql, 0, 4000),
                'returned_rows' => count($normalizedRows),
                'returned_columns' => $columns,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->auditLogger->success(
            action: 'db.sql.readonly.run',
            module: 'db_diagram',
            subjectType: 'sql',
            subjectKey: '__sql__',
            message: __('db_diagram_ui.audit.readonly_sql_executed'),
            meta: [
                'statement' => $guardResult['statement'] ?? 'readonly',
                'returned_rows' => count($normalizedRows),
                'returned_columns' => $columns,
            ],
            request: $request,
        );

        return response()->json([
            'mode' => 'readonly',
            'rows' => $normalizedRows,
            'columns' => $columns,
            'rowCount' => count($normalizedRows),
        ]);
    }

    public function executeDestructive(RunDbDiagramSqlDestructiveRequest $request): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $sql = trim((string) $request->validated('query'));

        $guardResult = $this->sqlExecutionGuard->validateDestructiveDml($sql);
        if (($guardResult['error'] ?? false) === true) {
            $message = (string) ($guardResult['message'] ?? __('db_diagram_ui.sql_editor.errors.sql_blocked'));
            $this->auditBlockedSql($request, $sql, 'destructive', $message);

            throw ValidationException::withMessages([
                'query' => [$message],
            ]);
        }

        $safeSql = (string) ($guardResult['sql'] ?? $sql);
        try {
            $affectedRows = DB::transaction(static fn (): int => DB::affectingStatement($safeSql));
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'query' => [__('db_diagram_ui.sql_editor.errors.destructive_execute_failed')],
            ]);
        }

        DatabaseEditorLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => 'sql_dml_run',
            'table_name' => '__sql__',
            'record_key' => null,
            'context' => [
                'statement' => $guardResult['statement'] ?? 'dml',
                'query' => mb_substr($sql, 0, 4000),
                'affected_rows' => $affectedRows,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->auditLogger->success(
            action: 'db.sql.destructive.run',
            module: 'db_diagram',
            subjectType: 'sql',
            subjectKey: '__sql__',
            message: __('db_diagram_ui.audit.destructive_sql_executed'),
            meta: [
                'statement' => $guardResult['statement'] ?? 'dml',
                'affected_rows' => $affectedRows,
            ],
            request: $request,
        );

        return response()->json([
            'mode' => 'destructive',
            'affectedRows' => $affectedRows,
            'statement' => $guardResult['statement'] ?? 'dml',
        ]);
    }

    private function auditBlockedSql(Request $request, string $sql, string $mode, string $message): void
    {
        DatabaseEditorLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => 'sql_blocked',
            'table_name' => '__sql__',
            'record_key' => null,
            'context' => [
                'mode' => $mode,
                'query' => mb_substr($sql, 0, 4000),
                'message' => $message,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->auditLogger->denied(
            action: 'db.sql.blocked',
            module: 'db_diagram',
            subjectType: 'sql',
            subjectKey: '__sql__',
            message: $message,
            meta: [
                'mode' => $mode,
            ],
            request: $request,
        );
    }

    private function canUseSqlEditor(mixed $user): bool
    {
        return DatabaseAccessGate::canAccess($user, 'admin.db-diagram.sql-editor', ['view', 'sql']);
    }

    private function canRunDestructiveSql(mixed $user): bool
    {
        return DatabaseAccessGate::canAccess($user, 'admin.db-diagram.sql-execute-destructive', ['view', 'sql', 'sql-destructive']);
    }

    /**
     * @return array{tables: array<int, array{name: string, columns: array<int, array{name: string, type: string}>}>}
     */
    private function buildSqlMetadata(): array
    {
        $tables = [];

        foreach ($this->schemaInspector->getViewableTables() as $tableName) {
            $columns = [];
            foreach ($this->schemaInspector->getTableColumns($tableName) as $column) {
                $name = (string) ($column['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $columns[] = [
                    'name' => $name,
                    'type' => (string) ($column['data_type'] ?? ''),
                ];
            }

            $tables[] = [
                'name' => $tableName,
                'columns' => $columns,
            ];
        }

        return ['tables' => $tables];
    }
}
