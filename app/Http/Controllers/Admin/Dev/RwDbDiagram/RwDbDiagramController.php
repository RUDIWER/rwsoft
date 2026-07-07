<?php

namespace App\Http\Controllers\Admin\Dev\RwDbDiagram;

use App\Http\Controllers\Controller;
use App\Models\DatabaseColumnMetadata;
use App\Models\User;
use App\Support\Database\DatabaseAccessGate;
use App\Support\Database\DatabaseSchemaInspector;
use App\Support\ModelDiscovery\ModelClassLocator;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use ReflectionClass;
use Throwable;

class RwDbDiagramController extends Controller
{
    public function __construct(
        private readonly DatabaseSchemaInspector $schemaInspector,
        private readonly ModelClassLocator $modelClassLocator,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        /** @var User|null $user */
        $user = $request->user();

        if (! DatabaseAccessGate::canAccess($user, 'admin.db-diagram', ['view'])) {
            return redirect()->route('admin')->with('error', __('db_diagram_ui.backend.no_access_diagram'));
        }

        $scopedTables = $this->schemaInspector->getViewableTables();
        $tableSchema = $this->buildTableSchema($scopedTables);

        return Inertia::render('Admin/RwDbDiagram/RwDbDiagram', [
            'tableSchema' => $tableSchema,
            'modelSchema' => $this->buildModelSchema($scopedTables),
            'fieldUsage' => [],
            'canViewDatabaseContents' => DatabaseAccessGate::canAccess($user, 'admin.db-diagram.table-data', ['view']),
            'canEditDatabaseContents' => DatabaseAccessGate::canAccess($user, 'admin.db-diagram.table-edit', ['view', 'edit']),
            'canAddDatabaseContents' => DatabaseAccessGate::canAccess($user, 'admin.db-diagram.table-create', ['view', 'edit', 'add']),
            'canDeleteDatabaseContents' => DatabaseAccessGate::canAccess($user, 'admin.db-diagram.table-delete', ['view', 'edit', 'delete']),
            'canExportDatabaseContents' => DatabaseAccessGate::canAccess($user, 'admin.db-diagram.table-export-sql', ['view', 'export']),
            'canExportFullDatabase' => DatabaseAccessGate::canAccess($user, 'admin.db-diagram.backup-full.start', ['view', 'full_backup']),
            'canViewDatabaseLogs' => DatabaseAccessGate::canAccess($user, 'admin.database-logs', ['view', 'full_backup']),
            'canUseSqlEditor' => DatabaseAccessGate::canAccess($user, 'admin.db-diagram.sql-editor', ['view', 'sql']),
            'canCreateTableDefinition' => false,
            'canEditTableDefinition' => false,
            'canManageModelCode' => false,
            'activeTablePrefix' => null,
            'tableScope' => 'all',
            'canSwitchTableScope' => false,
            'canManageSharedTableAccess' => false,
            'applicationPrefixes' => [],
            'applicationAccessTargets' => [],
            'sharedTableAccessByTable' => (object) [],
            'editBlockedTables' => $this->resolveEditBlockedTables($tableSchema),
            'nonEditableColumnsByTable' => $this->resolveNonEditableColumnsByTable($tableSchema),
            'sidebarMenus' => [],
        ]);
    }

    /**
     * @param  array<int, string>  $tableNames
     * @return array{tables: array<int, array<string, mixed>>, edges: array<int, array<string, string>>}
     */
    private function buildTableSchema(array $tableNames): array
    {
        $tables = [];
        $edges = [];

        foreach ($tableNames as $tableName) {
            $foreignKeys = $this->schemaInspector->getOutgoingForeignKeys($tableName);

            $tables[] = [
                'name' => $tableName,
                'columns' => $this->enrichTableColumns($tableName, $this->schemaInspector->getTableColumns($tableName)),
                'foreign_keys' => $foreignKeys,
                'indexes' => $this->schemaInspector->getTableIndexes($tableName),
            ];

            foreach ($foreignKeys as $foreignKey) {
                $edges[] = [
                    'from' => (string) ($foreignKey['table'] ?? $tableName),
                    'to' => (string) ($foreignKey['referenced_table'] ?? ''),
                    'label' => sprintf(
                        '%s -> %s',
                        (string) ($foreignKey['column'] ?? ''),
                        (string) ($foreignKey['referenced_column'] ?? '')
                    ),
                    'type' => 'foreign_key',
                ];
            }
        }

        return [
            'tables' => $tables,
            'edges' => $edges,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function enrichTableColumns(string $tableName, array $columns): array
    {
        $metadataByColumn = $this->columnMetadataByColumn($tableName);

        return collect($columns)
            ->map(static function (array $column) use ($metadataByColumn): array {
                $columnName = trim((string) ($column['name'] ?? ''));
                $metadata = is_array($metadataByColumn[$columnName] ?? null) ? $metadataByColumn[$columnName] : [];

                return [
                    ...$column,
                    'render_as_file_upload' => (bool) ($metadata['render_as_file_upload'] ?? false),
                    'upload_config' => is_array($metadata['upload_config'] ?? null) ? $metadata['upload_config'] : [],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $tableNames
     * @return array{tables: array<int, array<string, mixed>>, edges: array<int, array<string, string>>}
     */
    private function buildModelSchema(array $tableNames): array
    {
        $allowedTables = array_flip($tableNames);
        $tables = [];
        $edges = [];

        foreach ($this->modelClassLocator->all() as $className) {
            try {
                /** @var Model $model */
                $model = app($className);
            } catch (Throwable) {
                continue;
            }

            $tableName = $model->getTable();
            if (! isset($allowedTables[$tableName])) {
                continue;
            }

            $tables[$tableName] ??= [
                'name' => $tableName,
                'models' => [],
            ];

            $relations = $this->extractModelRelationships($model);
            $tables[$tableName]['models'][] = [
                'class' => $className,
                'name' => class_basename($className),
                'relationships' => $relations,
            ];

            foreach ($relations as $relation) {
                $relatedTable = (string) ($relation['related_table'] ?? '');
                if ($relatedTable === '') {
                    continue;
                }

                $edges[] = [
                    'from' => $tableName,
                    'to' => $relatedTable,
                    'label' => (string) ($relation['name'] ?? 'relation'),
                    'type' => (string) ($relation['type'] ?? 'relation'),
                ];
            }
        }

        return [
            'tables' => array_values($tables),
            'edges' => $edges,
        ];
    }

    /** @return array<int, array{name: string, type: string, related_table: string|null}> */
    private function extractModelRelationships(Model $model): array
    {
        $relationships = [];
        $methods = (new ReflectionClass($model))->getMethods();

        foreach ($methods as $method) {
            if ($method->class !== get_class($model)) {
                continue;
            }

            if (! $method->isPublic() || $method->getNumberOfParameters() > 0) {
                continue;
            }

            try {
                $result = $model->{$method->name}();
                if (! $result instanceof Relation) {
                    continue;
                }

                $relationships[] = [
                    'name' => $method->name,
                    'type' => class_basename($result),
                    'related_table' => $result->getRelated()->getTable(),
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return $relationships;
    }

    /**
     * @param  array{tables: array<int, array{name: string, columns?: array<int, array<string, mixed>>}>}  $tableSchema
     * @return array<int, string>
     */
    private function resolveEditBlockedTables(array $tableSchema): array
    {
        $blocked = [];

        foreach (($tableSchema['tables'] ?? []) as $table) {
            $tableName = (string) ($table['name'] ?? '');
            if ($tableName !== '' && $this->schemaInspector->isEditBlocked($tableName)) {
                $blocked[] = $tableName;
            }
        }

        return array_values(array_unique($blocked));
    }

    /**
     * @param  array{tables: array<int, array{name: string, columns?: array<int, array<string, mixed>>}>}  $tableSchema
     * @return array<string, array<int, string>>
     */
    private function resolveNonEditableColumnsByTable(array $tableSchema): array
    {
        $map = [];

        foreach (($tableSchema['tables'] ?? []) as $table) {
            $tableName = (string) ($table['name'] ?? '');
            $columns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
            if ($tableName === '' || $columns === []) {
                continue;
            }

            $primaryKey = $this->schemaInspector->getPrimaryKey($tableName);
            $map[$tableName] = $this->schemaInspector->getNonEditableColumns($tableName, $columns, $primaryKey);
        }

        return $map;
    }

    /** @return array<string, array<string, mixed>> */
    private function columnMetadataByColumn(string $tableName): array
    {
        if (! Schema::hasTable('rw_db_column_metadata')) {
            return [];
        }

        return DatabaseColumnMetadata::query()
            ->where('table_name', $tableName)
            ->get()
            ->keyBy('column_name')
            ->map(static fn (DatabaseColumnMetadata $metadata): array => [
                'render_as_file_upload' => (bool) $metadata->render_as_file_upload,
                'upload_config' => is_array($metadata->upload_config) ? $metadata->upload_config : [],
            ])
            ->all();
    }
}
