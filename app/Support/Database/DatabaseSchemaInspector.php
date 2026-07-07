<?php

namespace App\Support\Database;

use App\Support\ModelDiscovery\ModelClassLocator;
use App\Support\Tenancy\TenantDatabaseGuard;
use App\Support\Tenancy\TenantTableNames;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Throwable;

class DatabaseSchemaInspector
{
    public function __construct(
        private readonly ModelClassLocator $modelClassLocator,
        private readonly TenantTableNames $tenantTableNames,
    ) {}

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $modelRelationsCache = null;

    /**
     * @var array<string, array<string, string>>|null
     */
    private ?array $tableValidationRulesCache = null;

    /**
     * @return array<int, string>
     */
    public function getViewableTables(): array
    {
        TenantDatabaseGuard::ensureTenantConnection();

        try {
            $rows = DB::select($this->tenantTableNames->usesPrefix()
                ? 'select table_name as table_name from information_schema.tables where table_schema = DATABASE() and left(table_name, ?) = ? order by table_name'
                : 'select table_name as table_name from information_schema.tables where table_schema = DATABASE() order by table_name',
                $this->tenantTableNames->usesPrefix() ? [strlen($this->tenantTableNames->prefix()), $this->tenantTableNames->prefix()] : []
            );

            $tables = [];
            foreach ($rows as $row) {
                $physicalName = is_object($row) ? (string) ($row->table_name ?? '') : '';
                $name = $this->tenantTableNames->toLogical($physicalName);
                if ($name === '' || $this->isViewBlocked($name)) {
                    continue;
                }

                $tables[] = $name;
            }

            return $tables;
        } catch (Throwable) {
            try {
                $tables = [];
                foreach (DB::getSchemaBuilder()->getTables() as $table) {
                    $physicalName = is_array($table) ? (string) ($table['name'] ?? '') : '';
                    if (! $this->tenantTableNames->belongsToTenant($physicalName)) {
                        continue;
                    }

                    $name = $this->tenantTableNames->toLogical($physicalName);
                    if ($name === '' || $this->isViewBlocked($name)) {
                        continue;
                    }

                    $tables[] = $name;
                }

                sort($tables);

                return $tables;
            } catch (Throwable) {
                return [];
            }
        }
    }

    public function normalizeTableName(string $tableName): ?string
    {
        $normalized = trim($tableName);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_]+$/', $normalized) !== 1) {
            return null;
        }

        if ($this->tenantTableNames->usesPrefix() && str_starts_with($normalized, $this->tenantTableNames->prefix())) {
            $normalized = $this->tenantTableNames->toLogical($normalized);
        }

        return $normalized;
    }

    public function normalizeColumnName(string $columnName): ?string
    {
        $normalized = trim($columnName);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_]+$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    public function isViewableTable(string $tableName): bool
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if ($this->isViewBlocked($tableName)) {
            return false;
        }

        try {
            $physicalTableName = $this->tenantTableNames->quote($this->tenantTableNames->toPhysical($tableName));
            DB::selectOne("SELECT 1 FROM {$physicalTableName} LIMIT 0");

            return true;
        } catch (Throwable) {
            try {
                return DB::getSchemaBuilder()->hasTable($tableName);
            } catch (Throwable) {
                return false;
            }
        }
    }

    public function getCreateTableSql(string $tableName): ?string
    {
        TenantDatabaseGuard::ensureTenantConnection();

        try {
            $row = DB::selectOne('SHOW CREATE TABLE '.$this->tenantTableNames->quote($this->tenantTableNames->toPhysical($tableName)));
            if (! is_object($row)) {
                return null;
            }

            $data = (array) $row;
            foreach ($data as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }

                if (str_contains(strtolower($key), 'create table')) {
                    $sql = trim((string) $value);

                    return $sql !== '' ? $sql : null;
                }
            }

            $values = array_values($data);
            if (! isset($values[1])) {
                return null;
            }

            $sql = trim((string) $values[1]);

            return $sql !== '' ? $sql : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getTableStatus(string $tableName): array
    {
        TenantDatabaseGuard::ensureTenantConnection();

        try {
            $row = DB::selectOne('SHOW TABLE STATUS LIKE ?', [$this->tenantTableNames->toPhysical($tableName)]);
            if (! is_object($row)) {
                return [];
            }

            return (array) $row;
        } catch (Throwable) {
            return [];
        }
    }

    public function canEditTable(string $tableName): bool
    {
        return $this->isViewableTable($tableName) && ! $this->isEditBlocked($tableName);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTableColumns(string $tableName): array
    {
        TenantDatabaseGuard::ensureTenantConnection();

        try {
            $rows = DB::select(
                'select column_name as column_name,
                        data_type as data_type,
                        column_type as column_type,
                        is_nullable as is_nullable,
                        column_default as column_default,
                        column_key as column_key,
                        extra as extra,
                        column_comment as column_comment
                  from information_schema.columns
                  where table_schema = DATABASE() and table_name = ?
                  order by ordinal_position',
                [$this->tenantTableNames->toPhysical($tableName)]
            );

            if ($rows !== []) {
                return array_values(array_filter(array_map(function (object $row): array {
                    return [
                        'name' => (string) ($row->column_name ?? ''),
                        'data_type' => $this->normalizeSchemaDataType((string) ($row->data_type ?? '')),
                        'type_name' => $this->normalizeSchemaDataType((string) ($row->data_type ?? '')),
                        'column_type' => (string) ($row->column_type ?? ''),
                        'nullable' => ((string) ($row->is_nullable ?? 'NO')) === 'YES',
                        'default' => $row->column_default,
                        'key' => (string) ($row->column_key ?? ''),
                        'extra' => (string) ($row->extra ?? ''),
                        'comment' => (string) ($row->column_comment ?? ''),
                    ];
                }, $rows), static function (array $column): bool {
                    return $column['name'] !== '';
                }));
            }
        } catch (Throwable) {
        }

        try {
            $rows = DB::select('SHOW COLUMNS FROM '.$this->tenantTableNames->quote($this->tenantTableNames->toPhysical($tableName)));
            if (! empty($rows)) {
                $columns = [];
                foreach ($rows as $row) {
                    $rowData = array_change_key_case((array) $row, CASE_LOWER);
                    $name = (string) ($rowData['field'] ?? '');
                    if ($name === '') {
                        continue;
                    }

                    $columnType = (string) ($rowData['type'] ?? '');
                    $dataType = $this->normalizeSchemaDataType($columnType);

                    $columns[] = [
                        'name' => $name,
                        'data_type' => $dataType,
                        'type_name' => $dataType,
                        'column_type' => $columnType,
                        'nullable' => ((string) ($rowData['null'] ?? 'NO')) === 'YES',
                        'default' => $rowData['default'] ?? null,
                        'key' => (string) ($rowData['key'] ?? ''),
                        'extra' => (string) ($rowData['extra'] ?? ''),
                        'comment' => '',
                    ];
                }

                return $columns;
            }
        } catch (Throwable) {
        }

        return $this->getTableColumnsViaSchemaBuilder($tableName);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTableColumnsViaSchemaBuilder(string $tableName): array
    {
        try {
            $indexes = DB::getSchemaBuilder()->getIndexes($tableName);
            $columns = [];

            foreach (DB::getSchemaBuilder()->getColumns($tableName) as $column) {
                $name = (string) ($column['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $key = '';
                foreach ($indexes as $index) {
                    $indexColumns = is_array($index['columns'] ?? null) ? $index['columns'] : [];
                    if (! in_array($name, $indexColumns, true)) {
                        continue;
                    }

                    if ((bool) ($index['primary'] ?? false)) {
                        $key = 'PRI';
                        break;
                    }

                    if ((bool) ($index['unique'] ?? false)) {
                        $key = 'UNI';
                    } elseif ($key === '') {
                        $key = 'MUL';
                    }
                }

                $columns[] = [
                    'name' => $name,
                    'data_type' => $this->normalizeSchemaDataType((string) ($column['type_name'] ?? $column['type'] ?? 'text')),
                    'type_name' => $this->normalizeSchemaDataType((string) ($column['type_name'] ?? $column['type'] ?? 'text')),
                    'column_type' => (string) ($column['type'] ?? ''),
                    'nullable' => (bool) ($column['nullable'] ?? false),
                    'default' => $column['default'] ?? null,
                    'key' => $key,
                    'extra' => (bool) ($column['auto_increment'] ?? false) ? 'auto_increment' : '',
                    'comment' => (string) ($column['comment'] ?? ''),
                ];
            }

            return $columns;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    public function getColumnDefinition(array $columns, string $columnName): ?array
    {
        foreach ($columns as $column) {
            if ((string) ($column['name'] ?? '') === $columnName) {
                return $column;
            }
        }

        return null;
    }

    private function normalizeSchemaDataType(string $rawType): string
    {
        $normalized = strtolower(trim($rawType));
        if ($normalized === '') {
            return '';
        }

        $normalized = (string) preg_replace('/\(.*/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?: '';
        if ($normalized === '') {
            return '';
        }

        $tokens = explode(' ', $normalized);

        return trim((string) ($tokens[0] ?? ''));
    }

    public function getPrimaryKey(string $tableName): ?string
    {
        $columns = $this->getTableColumns($tableName);

        foreach ($columns as $column) {
            if ((string) ($column['key'] ?? '') === 'PRI') {
                return (string) ($column['name'] ?? 'id');
            }
        }

        foreach ($columns as $column) {
            if ((string) ($column['name'] ?? '') === 'id') {
                return 'id';
            }
        }

        return $columns !== [] ? (string) ($columns[0]['name'] ?? null) : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    public function toRwTableColumns(array $columns): array
    {
        $result = [];

        foreach ($columns as $column) {
            $columnName = (string) ($column['name'] ?? '');
            if ($columnName === '') {
                continue;
            }

            $result[] = [
                'key' => $columnName,
                'label' => $columnName,
                'selected' => true,
                'sortable' => true,
                'filterable' => true,
                'type' => $this->mapColumnTypeToRwType($column),
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, string>
     */
    public function getSearchableColumns(array $columns): array
    {
        $searchable = [];

        foreach ($columns as $column) {
            $columnName = (string) ($column['name'] ?? '');
            $dataType = strtolower((string) ($column['data_type'] ?? ''));
            if ($columnName === '') {
                continue;
            }

            if (in_array($dataType, ['blob', 'binary', 'varbinary', 'geometry', 'point', 'linestring', 'polygon'], true)) {
                continue;
            }

            $searchable[] = $columnName;
        }

        return $searchable;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, string>
     */
    public function getNonEditableColumns(string $tableName, array $columns, ?string $primaryKey = null): array
    {
        if ($this->isEditBlocked($tableName)) {
            return array_values(array_unique(array_map(static fn (array $column): string => (string) ($column['name'] ?? ''), $columns)));
        }

        $excluded = [];
        $globalNames = ['created_at', 'updated_at', 'deleted_at'];
        $tableSpecific = $this->nonEditableColumnsByTable()[$tableName] ?? [];

        foreach ($columns as $column) {
            $name = (string) ($column['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $dataType = strtolower((string) ($column['data_type'] ?? ''));
            $columnType = strtolower((string) ($column['column_type'] ?? ''));
            $extra = strtolower((string) ($column['extra'] ?? ''));

            if ($name === $primaryKey || in_array($name, $globalNames, true)) {
                $excluded[] = $name;

                continue;
            }

            if (in_array('*', $tableSpecific, true) || in_array($name, $tableSpecific, true)) {
                $excluded[] = $name;

                continue;
            }

            if (str_contains($extra, 'auto_increment')) {
                $excluded[] = $name;

                continue;
            }

            if (in_array($dataType, ['blob', 'binary', 'varbinary', 'geometry', 'point', 'linestring', 'polygon'], true)) {
                $excluded[] = $name;

                continue;
            }

            if (str_contains($columnType, 'generated')) {
                $excluded[] = $name;
            }
        }

        return array_values(array_unique($excluded));
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    public function getFormColumns(string $tableName, array $columns, ?string $primaryKey = null): array
    {
        $nonEditableColumns = $this->getNonEditableColumns($tableName, $columns, $primaryKey);
        $validationRules = $this->getValidationRulesForTable($tableName);
        $result = [];

        foreach ($columns as $column) {
            $name = (string) ($column['name'] ?? '');
            if ($name === '' || in_array($name, $nonEditableColumns, true)) {
                continue;
            }

            if ($this->isBinaryLikeColumn($column)) {
                continue;
            }

            $result[] = [
                'key' => $name,
                'label' => $name,
                'type' => $this->mapColumnTypeToRwType($column),
                'data_type' => strtolower((string) ($column['data_type'] ?? '')),
                'nullable' => (bool) ($column['nullable'] ?? false),
                'required' => $this->isRequiredFormColumn($column),
                'max_length' => $this->extractColumnMaxLength((string) ($column['column_type'] ?? '')),
                'default' => $column['default'] ?? null,
                'validation_rule' => $validationRules[$name] ?? '',
            ];
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function getValidationRulesForTable(string $tableName): array
    {
        if ($this->tableValidationRulesCache === null) {
            $this->tableValidationRulesCache = $this->buildValidationRulesByTable();
        }

        return $this->tableValidationRulesCache[$tableName] ?? [];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function buildValidationRulesByTable(): array
    {
        $rulesByTable = [];

        foreach ($this->modelClassLocator->all() as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            /** @var Model $model */
            $model = new $class;
            $table = trim((string) $model->getTable());

            if ($table === '' || ! method_exists($class, 'rules')) {
                continue;
            }

            try {
                $rawRules = $class::rules(0);
            } catch (Throwable) {
                continue;
            }

            if (! is_array($rawRules)) {
                continue;
            }

            $normalized = [];

            foreach ($rawRules as $field => $rule) {
                $fieldName = trim((string) $field);

                if ($fieldName === '') {
                    continue;
                }

                $ruleString = $this->normalizeRuleDefinitionToString($rule);

                if ($ruleString === '') {
                    continue;
                }

                $normalized[$fieldName] = $ruleString;
            }

            if ($normalized !== []) {
                $rulesByTable[$table] = $normalized;
            }
        }

        return $rulesByTable;
    }

    private function normalizeRuleDefinitionToString(mixed $rule): string
    {
        if (is_string($rule)) {
            return trim($rule);
        }

        if (! is_array($rule)) {
            return '';
        }

        $tokens = collect($rule)
            ->map(static function (mixed $entry): string {
                if (is_string($entry)) {
                    return trim($entry);
                }

                if (is_object($entry) && method_exists($entry, '__toString')) {
                    return trim((string) $entry);
                }

                return '';
            })
            ->filter(static fn (string $entry): bool => $entry !== '')
            ->values();

        if ($tokens->isEmpty()) {
            return '';
        }

        return $tokens->implode('|');
    }

    public function isEditBlocked(string $tableName): bool
    {
        return in_array($tableName, $this->editBlockedTables(), true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOutgoingForeignKeys(string $tableName): array
    {
        TenantDatabaseGuard::ensureTenantConnection();

        try {
            $rows = DB::select(
                'select kcu.table_name as table_name,
                        kcu.column_name as column_name,
                        kcu.referenced_table_name as referenced_table_name,
                        kcu.referenced_column_name as referenced_column_name,
                        rc.constraint_name as constraint_name,
                        rc.update_rule as update_rule,
                        rc.delete_rule as delete_rule
                 from information_schema.key_column_usage kcu
                 join information_schema.referential_constraints rc
                   on rc.constraint_schema = kcu.table_schema
                  and rc.constraint_name = kcu.constraint_name
                 where kcu.table_schema = DATABASE()
                   and kcu.table_name = ?
                   and kcu.referenced_table_name is not null',
                [$this->tenantTableNames->toPhysical($tableName)]
            );

            return array_values(array_map(function (object $row): array {
                return [
                    'table' => $this->tenantTableNames->toLogical((string) ($row->table_name ?? '')),
                    'column' => (string) ($row->column_name ?? ''),
                    'referenced_table' => $this->tenantTableNames->toLogical((string) ($row->referenced_table_name ?? '')),
                    'referenced_column' => (string) ($row->referenced_column_name ?? ''),
                    'constraint' => (string) ($row->constraint_name ?? ''),
                    'on_update' => (string) ($row->update_rule ?? ''),
                    'on_delete' => (string) ($row->delete_rule ?? ''),
                ];
            }, $rows));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getIncomingForeignKeys(string $tableName): array
    {
        TenantDatabaseGuard::ensureTenantConnection();

        try {
            $rows = DB::select(
                'select kcu.table_name as table_name,
                        kcu.column_name as column_name,
                        kcu.referenced_table_name as referenced_table_name,
                        kcu.referenced_column_name as referenced_column_name,
                        rc.constraint_name as constraint_name,
                        rc.update_rule as update_rule,
                        rc.delete_rule as delete_rule
                 from information_schema.key_column_usage kcu
                 join information_schema.referential_constraints rc
                   on rc.constraint_schema = kcu.table_schema
                  and rc.constraint_name = kcu.constraint_name
                 where kcu.table_schema = DATABASE()
                   and kcu.referenced_table_name = ?
                   and kcu.referenced_table_name is not null',
                [$this->tenantTableNames->toPhysical($tableName)]
            );

            return array_values(array_map(function (object $row): array {
                return [
                    'table' => $this->tenantTableNames->toLogical((string) ($row->table_name ?? '')),
                    'column' => (string) ($row->column_name ?? ''),
                    'referenced_table' => $this->tenantTableNames->toLogical((string) ($row->referenced_table_name ?? '')),
                    'referenced_column' => (string) ($row->referenced_column_name ?? ''),
                    'constraint' => (string) ($row->constraint_name ?? ''),
                    'on_update' => (string) ($row->update_rule ?? ''),
                    'on_delete' => (string) ($row->delete_rule ?? ''),
                ];
            }, $rows));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTableIndexes(string $tableName): array
    {
        TenantDatabaseGuard::ensureTenantConnection();

        try {
            $rows = DB::select(
                'select index_name as index_name,
                        non_unique as non_unique,
                        index_type as index_type,
                        seq_in_index as seq_in_index,
                        column_name as column_name,
                        collation as collation
                 from information_schema.statistics
                  where table_schema = DATABASE() and table_name = ?
                  order by index_name, seq_in_index',
                [$this->tenantTableNames->toPhysical($tableName)]
            );

            $indexes = [];
            foreach ($rows as $row) {
                $indexName = (string) ($row->index_name ?? '');
                if ($indexName === '') {
                    continue;
                }

                if (! isset($indexes[$indexName])) {
                    $indexes[$indexName] = [
                        'name' => $indexName,
                        'unique' => ((int) ($row->non_unique ?? 1)) === 0,
                        'type' => (string) ($row->index_type ?? ''),
                        'columns' => [],
                        'column_orders' => [],
                    ];
                }

                $indexes[$indexName]['columns'][] = (string) ($row->column_name ?? '');
                $indexes[$indexName]['column_orders'][] = strtoupper((string) ($row->collation ?? 'A')) === 'D'
                    ? 'desc'
                    : 'asc';
            }

            return array_values($indexes);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    public function getRelationshipColumns(string $tableName, ?string $primaryKey = null): array
    {
        $columns = [];

        foreach ($this->getOutgoingForeignKeys($tableName) as $foreignKey) {
            $column = (string) ($foreignKey['column'] ?? '');
            if ($column !== '' && $column !== $primaryKey) {
                $columns[] = $column;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @return array<string, array<int, array{value: mixed, label: string}>>
     */
    public function getRelationshipOptions(string $tableName, ?string $primaryKey = null): array
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $options = [];

        foreach ($this->getOutgoingForeignKeys($tableName) as $foreignKey) {
            $column = (string) ($foreignKey['column'] ?? '');
            $referenceTable = (string) ($foreignKey['referenced_table'] ?? '');
            $referenceColumn = (string) ($foreignKey['referenced_column'] ?? 'id');

            if ($column === '' || $referenceTable === '') {
                continue;
            }

            if (! $this->isViewableTable($referenceTable)) {
                continue;
            }

            $referenceColumns = $this->getTableColumns($referenceTable);
            $displayColumn = $this->resolveDisplayColumn($referenceColumns, $referenceColumn);

            try {
                $rows = DB::table($referenceTable)
                    ->select([$referenceColumn, $displayColumn])
                    ->limit(200)
                    ->get();

                $options[$column] = $rows->map(static function (object $row) use ($referenceColumn, $displayColumn): array {
                    $value = $row->{$referenceColumn} ?? null;
                    $label = $row->{$displayColumn} ?? $value;

                    return [
                        'value' => $value,
                        'label' => (string) $label,
                    ];
                })->values()->all();
            } catch (Throwable) {
                $options[$column] = [];
            }
        }

        return $options;
    }

    /**
     * @return array{database: array<string, mixed>, models: array<int, array<string, mixed>>}
     */
    public function getRecordRelations(string $tableName, string $primaryKey, mixed $recordId): array
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $database = [
            'outgoing' => [],
            'incoming' => [],
        ];

        $record = DB::table($tableName)->where($primaryKey, $recordId)->first();

        foreach ($this->getOutgoingForeignKeys($tableName) as $foreignKey) {
            $column = (string) ($foreignKey['column'] ?? '');
            $currentValue = null;

            if ($record && $column !== '' && property_exists($record, $column)) {
                $currentValue = $record->{$column};
            }

            $database['outgoing'][] = array_merge($foreignKey, [
                'current_value' => $currentValue,
            ]);
        }

        foreach ($this->getIncomingForeignKeys($tableName) as $foreignKey) {
            $affectedRows = 0;

            $sourceTable = (string) ($foreignKey['table'] ?? '');
            $sourceColumn = (string) ($foreignKey['column'] ?? '');

            if ($sourceTable !== '' && $sourceColumn !== '') {
                try {
                    $affectedRows = DB::table($sourceTable)
                        ->where($sourceColumn, $recordId)
                        ->count();
                } catch (Throwable) {
                    $affectedRows = 0;
                }
            }

            $database['incoming'][] = array_merge($foreignKey, [
                'affected_rows' => $affectedRows,
            ]);
        }

        $models = array_values(array_filter($this->getAllModelRelations(), static function (array $relation) use ($tableName): bool {
            return (string) ($relation['source_table'] ?? '') === $tableName
                || (string) ($relation['related_table'] ?? '') === $tableName;
        }));

        return [
            'database' => $database,
            'models' => $models,
        ];
    }

    private function resolveDisplayColumn(array $columns, string $fallbackColumn): string
    {
        foreach ($columns as $column) {
            $name = (string) ($column['name'] ?? '');
            $type = strtolower((string) ($column['data_type'] ?? ''));

            if ($name === '' || in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            if (in_array($type, ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'], true)) {
                return $name;
            }
        }

        return $fallbackColumn;
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function isBinaryLikeColumn(array $column): bool
    {
        $dataType = strtolower((string) ($column['data_type'] ?? ''));

        return in_array($dataType, ['blob', 'binary', 'varbinary', 'geometry', 'point', 'linestring', 'polygon'], true);
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function isRequiredFormColumn(array $column): bool
    {
        if ((bool) ($column['nullable'] ?? false)) {
            return false;
        }

        if (array_key_exists('default', $column) && $column['default'] !== null) {
            return false;
        }

        $extra = strtolower((string) ($column['extra'] ?? ''));
        $columnType = strtolower((string) ($column['column_type'] ?? ''));

        return ! str_contains($extra, 'auto_increment') && ! str_contains($columnType, 'generated');
    }

    private function extractColumnMaxLength(string $columnType): ?int
    {
        if (preg_match('/^(varchar|char)\((\d+)\)/i', $columnType, $matches) !== 1) {
            return null;
        }

        return (int) ($matches[2] ?? 0);
    }

    private function isViewBlocked(string $tableName): bool
    {
        return in_array($tableName, $this->viewBlockedTables(), true);
    }

    /**
     * @return array<int, string>
     */
    private function viewBlockedTables(): array
    {
        $blocked = config('database_tools.view_blocked_tables', []);

        return is_array($blocked) ? array_values(array_map(static fn (mixed $name): string => (string) $name, $blocked)) : [];
    }

    /**
     * @return array<int, string>
     */
    private function editBlockedTables(): array
    {
        $blocked = config('database_tools.edit_blocked_tables', []);

        return is_array($blocked) ? array_values(array_map(static fn (mixed $name): string => (string) $name, $blocked)) : [];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function nonEditableColumnsByTable(): array
    {
        $configured = config('database_tools.non_editable_columns_by_table', []);
        if (! is_array($configured)) {
            return [];
        }

        $result = [];
        foreach ($configured as $table => $columns) {
            if (! is_array($columns)) {
                continue;
            }

            $result[(string) $table] = array_values(array_map(static fn (mixed $column): string => (string) $column, $columns));
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function mapColumnTypeToRwType(array $column): string
    {
        $dataType = strtolower((string) ($column['data_type'] ?? ''));
        $columnType = strtolower((string) ($column['column_type'] ?? ''));

        if ($dataType === 'tinyint' && str_contains($columnType, 'tinyint(1)')) {
            return 'boolean';
        }

        if (in_array($dataType, ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'decimal', 'float', 'double'], true)) {
            return 'number';
        }

        if ($dataType === 'date') {
            return 'date';
        }

        if (in_array($dataType, ['datetime', 'timestamp'], true)) {
            return 'datetime';
        }

        return 'text';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getAllModelRelations(): array
    {
        if (is_array($this->modelRelationsCache)) {
            return $this->modelRelationsCache;
        }

        $result = [];
        foreach ($this->modelClassLocator->all() as $class) {
            try {
                /** @var Model $model */
                $model = app($class);
            } catch (Throwable) {
                continue;
            }

            $sourceTable = $model->getTable();
            $sourceModel = class_basename($class);

            foreach ($this->extractModelRelationships($model) as $relation) {
                $result[] = [
                    'source_model' => $sourceModel,
                    'source_table' => $sourceTable,
                    'relation_name' => (string) ($relation['name'] ?? ''),
                    'relation_type' => (string) ($relation['type'] ?? ''),
                    'related_model' => (string) ($relation['related_model'] ?? ''),
                    'related_table' => (string) ($relation['related_table'] ?? ''),
                    'foreign_key' => (string) ($relation['foreign_key'] ?? ''),
                    'owner_key' => (string) ($relation['owner_key'] ?? ''),
                    'local_key' => (string) ($relation['local_key'] ?? ''),
                    'parent_key' => (string) ($relation['parent_key'] ?? ''),
                    'related_key' => (string) ($relation['related_key'] ?? ''),
                ];
            }
        }

        $this->modelRelationsCache = $result;

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
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
                $relationResult = $model->{$method->name}();
                if (! $relationResult instanceof Relation) {
                    continue;
                }

                $relatedModel = null;
                $relatedTable = null;
                try {
                    $relatedModel = get_class($relationResult->getRelated());
                    $relatedTable = $relationResult->getRelated()->getTable();
                } catch (Throwable) {
                    $relatedModel = null;
                    $relatedTable = null;
                }

                $relation = [
                    'name' => $method->name,
                    'type' => class_basename($relationResult),
                    'related_model' => $relatedModel,
                    'related_table' => $relatedTable,
                ];

                if (method_exists($relationResult, 'getForeignKeyName')) {
                    $relation['foreign_key'] = $relationResult->getForeignKeyName();
                }
                if (method_exists($relationResult, 'getOwnerKeyName')) {
                    $relation['owner_key'] = $relationResult->getOwnerKeyName();
                }
                if (method_exists($relationResult, 'getLocalKeyName')) {
                    $relation['local_key'] = $relationResult->getLocalKeyName();
                }
                if (method_exists($relationResult, 'getParentKeyName')) {
                    $relation['parent_key'] = $relationResult->getParentKeyName();
                }
                if (method_exists($relationResult, 'getRelatedKeyName')) {
                    $relation['related_key'] = $relationResult->getRelatedKeyName();
                }

                $relationships[] = $relation;
            } catch (Throwable) {
                continue;
            }
        }

        return $relationships;
    }
}
