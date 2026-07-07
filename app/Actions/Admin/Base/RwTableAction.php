<?php

namespace App\Actions\Admin\Base;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Rwsoft\RwTableLaravel\Actions\RwTableAction as PackageRwTableAction;

class RwTableAction extends PackageRwTableAction
{
    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, mixed>
     */
    public static function runtimeData(
        Request $request,
        string $modelClass,
        int $perPageDefault = 25,
        ?callable $queryCallback = null
    ): array {
        $columns = self::parseColumns(self::getRequestValue($request, 'columns', []));

        $globalFields = ['id'];

        if ($columns !== []) {
            $first = $columns[0] ?? null;

            if (is_string($first)) {
                $globalFields = array_values(
                    array_filter(
                        $columns,
                        static fn (mixed $column): bool => is_string($column)
                    )
                );
            } else {
                $selectedColumns = array_filter(
                    $columns,
                    static fn (mixed $column): bool => is_array($column) && ! empty($column['selected'])
                );

                $globalFields = array_values(
                    array_filter(
                        array_map(
                            static fn (mixed $column): ?string => is_array($column)
                                ? (string) ($column['key'] ?? '')
                                : null,
                            $selectedColumns
                        )
                    )
                );
            }
        }

        $query = $modelClass::query();

        if ($queryCallback !== null) {
            $queryCallback($query);
        }

        if ($globalFields === ['id']) {
            $globalFields = array_values(
                array_filter(
                    Schema::getColumnListing($query->getModel()->getTable()),
                    static fn (mixed $column): bool => is_string($column) && trim($column) !== ''
                )
            );
        }

        $idKey = self::sanitizeField((string) self::getRequestValue($request, 'idKey', 'id'), 'id');

        $table = $query->getModel()->getTable();
        $global = (string) self::getRequestValue($request, 'global', '');
        $page = max(1, (int) self::getRequestValue($request, 'page', 1));

        $resolvedGlobalColumns = array_values(
            array_filter(
                array_map(
                    static fn (string $field): ?string => self::resolveRuntimeColumn($table, $field),
                    $globalFields,
                ),
            ),
        );

        if ($global !== '' && $resolvedGlobalColumns !== []) {
            $isNumericGlobal = ctype_digit($global);

            $query->where(function (Builder $nestedQuery) use ($global, $isNumericGlobal, $resolvedGlobalColumns, $idKey, $table): void {
                foreach ($resolvedGlobalColumns as $column) {
                    $shortField = Str::afterLast($column, '.');

                    if ($shortField === $idKey || $column === "{$table}.{$idKey}") {
                        if ($isNumericGlobal) {
                            $nestedQuery->orWhere($column, (int) $global);
                        }

                        continue;
                    }

                    $nestedQuery->orWhereRaw("CAST({$column} AS CHAR) LIKE ?", ["%{$global}%"]);
                }
            });
        }

        /** @var array<string, mixed> $filters */
        $filters = (array) self::getRequestValue($request, 'filters', []);
        /** @var array<string, mixed> $filterModes */
        $filterModes = (array) self::getRequestValue($request, 'filterModes', []);
        /** @var array<string, mixed> $filterTypes */
        $filterTypes = (array) self::getRequestValue($request, 'filterTypes', []);

        foreach ($filters as $field => $value) {
            if (! is_string($field)) {
                continue;
            }

            $column = self::resolveRuntimeColumn($table, $field);

            if ($column === null) {
                continue;
            }

            $mode = (string) ($filterModes[$field] ?? '=');
            $filterType = (string) ($filterTypes[$field] ?? 'text');

            if (is_array($value) && isset($value['from'], $value['to'])) {
                $from = self::safeParseDate((string) $value['from'])?->startOfDay();
                $to = self::safeParseDate((string) $value['to'])?->endOfDay();

                if ($from && $to) {
                    $query->whereBetween($column, [$from, $to]);
                }

                continue;
            }

            self::applyFilter($query, $column, $filterType, $mode, $value);
        }

        $selectionFilter = (string) self::getRequestValue($request, 'selectionFilter', 'none');
        /** @var array<int|string> $selectedIds */
        $selectedIds = (array) self::getRequestValue($request, 'selectedRowIds', []);

        if ($selectedIds !== [] && in_array($selectionFilter, ['exclude', 'only'], true)) {
            $pkColumn = "{$table}.{$idKey}";

            if ($selectionFilter === 'exclude') {
                $query->whereNotIn($pkColumn, $selectedIds);
            } else {
                $query->whereIn($pkColumn, $selectedIds);
            }
        }

        $manualOrdering = (bool) self::getRequestValue($request, 'manualOrdering', false);
        $manualOrderField = self::sanitizeField((string) self::getRequestValue($request, 'manualOrderField', 'index'), 'index');
        $sortField = self::sanitizeField((string) self::getRequestValue($request, 'sortField', $idKey), $idKey);
        $sortOrder = strtolower((string) self::getRequestValue($request, 'sortOrder', 'asc'));
        $sortOrder = in_array($sortOrder, ['asc', 'desc'], true) ? $sortOrder : 'asc';

        if ($manualOrdering) {
            $sortField = $manualOrderField;
            $sortOrder = 'asc';
        }

        $sortColumn = self::resolveRuntimeColumn($table, $sortField);

        if ($sortColumn === null) {
            $sortColumn = self::resolveRuntimeColumn($table, $idKey) ?? "{$table}.id";
        }

        $query->orderBy($sortColumn, $sortOrder);

        $perPage = max(1, min(500, (int) self::getRequestValue($request, 'rowsPerPage', $perPageDefault)));
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginated->items())
                ->map(static function (mixed $row): array {
                    if ($row instanceof Model) {
                        return $row->attributesToArray();
                    }

                    return (array) $row;
                })
                ->values()
                ->all(),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
        ];
    }

    private static function getRequestValue(Request $request, string $key, mixed $default = null): mixed
    {
        if ($request->has($key)) {
            return $request->input($key, $default);
        }

        return $request->query($key, $default);
    }

    /** @return array<int, mixed> */
    private static function parseColumns(mixed $columns): array
    {
        if (is_array($columns)) {
            return array_values($columns);
        }

        if (is_string($columns) && $columns !== '') {
            $decoded = json_decode($columns, true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        return [];
    }

    private static function sanitizeField(string $field, string $default): string
    {
        $pattern = (string) config('rwtable.security.allowed_field_pattern', '/^[A-Za-z0-9_\.]+$/');

        if ($field !== '' && preg_match($pattern, $field) === 1) {
            return $field;
        }

        return $default;
    }

    private static function resolveColumn(string $table, string $field): ?string
    {
        $field = self::sanitizeField($field, '');

        if ($field === '') {
            return null;
        }

        /** @var array<string, string> $aliasToColumn */
        $aliasToColumn = (array) config('rwtable.field_aliases', []);

        if (array_key_exists($field, $aliasToColumn)) {
            return self::sanitizeField($aliasToColumn[$field], "{$table}.id");
        }

        if (str_contains($field, '.')) {
            $shortField = Str::afterLast($field, '.');

            if (str_starts_with($field, "{$table}.") && array_key_exists($shortField, $aliasToColumn)) {
                return self::sanitizeField($aliasToColumn[$shortField], "{$table}.id");
            }

            return self::sanitizeField($field, "{$table}.id");
        }

        return "{$table}.{$field}";
    }

    private static function resolveRuntimeColumn(string $table, string $field): ?string
    {
        $field = self::sanitizeField($field, '');

        if ($field === '') {
            return null;
        }

        $shortField = Str::afterLast($field, '.');

        if (! Schema::hasColumn($table, $shortField)) {
            return null;
        }

        return "{$table}.{$shortField}";
    }

    private static function safeParseDate(string $date): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function applyFilter(Builder $query, string $column, string $filterType, string $mode, mixed $value): void
    {
        if ($mode === 'option_contains' || $mode === 'option_equals') {
            $values = self::normalizeOptionFilterValues($value);

            if ($values === []) {
                return;
            }

            if ($mode === 'option_equals') {
                if (count($values) === 1) {
                    $query->where($column, '=', $values[0]);
                }

                return;
            }

            foreach ($values as $token) {
                $query->whereRaw("CAST({$column} AS CHAR) LIKE ?", ["%{$token}%"]);
            }

            return;
        }

        $method = $filterType === 'date' ? 'whereDate' : 'where';

        switch ($mode) {
            case '!=':
                $query->{$method}($column, '!=', $value);
                break;
            case 'bevat':
                $query->where($column, 'like', "%{$value}%");
                break;
            case 'bevat niet':
                $query->where($column, 'not like', "%{$value}%");
                break;
            case '>':
                $query->{$method}($column, '>', $value);
                break;
            case '<':
                $query->{$method}($column, '<', $value);
                break;
            case '=':
            default:
                $query->{$method}($column, '=', $value);
                break;
        }
    }

    /** @return array<int, string> */
    private static function normalizeOptionFilterValues(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return collect($values)
            ->map(static fn (mixed $item): string => trim((string) $item))
            ->filter(static fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }
}
