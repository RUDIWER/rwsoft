<?php

namespace App\Actions\Admin\Base\Query;

use App\Support\Tenancy\TenantDatabaseGuard;
use App\Support\Tenancy\TenantSqlTablePrefixer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RunSqlQueryAction
{
    private const MAX_RESULT_ROWS = 5000;

    /**
     * @param  array<string, mixed>  $bindings
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $filterModes
     * @param  array<string, mixed>  $filterTypes
     * @return array{data:array<int, array<string, mixed>>,total:int,current_page:int,last_page:int,truncated:bool,columns:array<int, string>}
     */
    public static function handle(
        string $sql,
        array $bindings,
        int $page,
        int $rowsPerPage,
        ?string $sortField = null,
        string $sortOrder = 'asc',
        ?string $globalSearch = null,
        array $filters = [],
        array $filterModes = [],
        array $filterTypes = [],
    ): array {
        TenantDatabaseGuard::ensureTenantConnection();

        $safeRowsPerPage = max(1, min(200, $rowsPerPage));
        $safePage = max(1, $page);

        $rows = DB::select(app(TenantSqlTablePrefixer::class)->applyToSelectSql($sql), self::normalizeBindings($bindings));
        $asArray = collect($rows)
            ->map(static fn (object $row): array => (array) $row)
            ->values();

        $truncated = false;

        if ($asArray->count() > self::MAX_RESULT_ROWS) {
            $asArray = $asArray->slice(0, self::MAX_RESULT_ROWS)->values();
            $truncated = true;
        }

        $searchTerm = trim((string) ($globalSearch ?? ''));

        if ($searchTerm !== '') {
            $needle = mb_strtolower($searchTerm);

            $asArray = $asArray
                ->filter(static function (array $row) use ($needle): bool {
                    foreach ($row as $value) {
                        if (mb_stripos((string) ($value ?? ''), $needle) !== false) {
                            return true;
                        }
                    }

                    return false;
                })
                ->values();
        }

        $asArray = self::applyColumnFilters($asArray, $filters, $filterModes, $filterTypes);

        $firstRow = $asArray->first();
        $columns = is_array($firstRow) ? array_keys($firstRow) : [];
        $normalizedSortField = trim((string) ($sortField ?? ''));

        if ($normalizedSortField !== '' && in_array($normalizedSortField, $columns, true)) {
            $descending = strtolower($sortOrder) === 'desc';

            $asArray = $asArray
                ->sortBy(
                    static function (array $row) use ($normalizedSortField): mixed {
                        return $row[$normalizedSortField] ?? null;
                    },
                    SORT_NATURAL | SORT_FLAG_CASE,
                    $descending,
                )
                ->values();
        }

        $total = $asArray->count();
        $lastPage = $total > 0 ? (int) ceil($total / $safeRowsPerPage) : 1;
        $offset = ($safePage - 1) * $safeRowsPerPage;

        return [
            'data' => $asArray->slice($offset, $safeRowsPerPage)->values()->all(),
            'total' => $total,
            'current_page' => $safePage,
            'last_page' => $lastPage,
            'truncated' => $truncated,
            'columns' => $columns,
        ];
    }

    /**
     * @param  array<string, mixed>  $bindings
     * @return array{data:array<int, array<string, mixed>>,total:int,truncated:bool,columns:array<int, string>}
     */
    public static function handleAll(
        string $sql,
        array $bindings,
        ?string $globalSearch = null,
        ?string $sortField = null,
        string $sortOrder = 'asc',
    ): array {
        $result = self::handle(
            $sql,
            $bindings,
            1,
            self::MAX_RESULT_ROWS,
            $sortField,
            $sortOrder,
            $globalSearch,
            [],
            [],
            [],
        );

        $data = $result['data'] ?? [];

        if ((int) ($result['last_page'] ?? 1) <= 1) {
            return [
                'data' => (array) $data,
                'total' => (int) ($result['total'] ?? count((array) $data)),
                'truncated' => (bool) ($result['truncated'] ?? false),
                'columns' => (array) ($result['columns'] ?? []),
            ];
        }

        $allRows = (array) $data;
        $lastPage = (int) ($result['last_page'] ?? 1);

        for ($page = 2; $page <= $lastPage; $page++) {
            $pageResult = self::handle(
                $sql,
                $bindings,
                $page,
                self::MAX_RESULT_ROWS,
                $sortField,
                $sortOrder,
                $globalSearch,
                [],
                [],
                [],
            );

            $allRows = array_merge($allRows, (array) ($pageResult['data'] ?? []));
        }

        return [
            'data' => $allRows,
            'total' => (int) ($result['total'] ?? count($allRows)),
            'truncated' => (bool) ($result['truncated'] ?? false),
            'columns' => (array) ($result['columns'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $bindings
     * @return array<string, mixed>
     */
    private static function normalizeBindings(array $bindings): array
    {
        return collect($bindings)
            ->mapWithKeys(static function (mixed $value, mixed $key): array {
                $bindingKey = trim((string) $key);

                if ($bindingKey === '') {
                    return [];
                }

                if (is_bool($value) || is_int($value) || is_float($value)) {
                    return [$bindingKey => $value];
                }

                if ($value === null) {
                    return [$bindingKey => null];
                }

                return [$bindingKey => (string) $value];
            })
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $filterModes
     * @param  array<string, mixed>  $filterTypes
     * @return Collection<int, array<string, mixed>>
     */
    private static function applyColumnFilters($rows, array $filters, array $filterModes, array $filterTypes)
    {
        $normalizedFilters = self::normalizeFilterPayload($filters);
        $normalizedModes = self::normalizeFilterPayload($filterModes);
        $normalizedTypes = self::normalizeFilterPayload($filterTypes);

        foreach ($normalizedFilters as $field => $value) {
            $column = trim((string) $field);

            if ($column === '') {
                continue;
            }

            $mode = strtolower(trim((string) ($normalizedModes[$column] ?? '=')));
            $type = strtolower(trim((string) ($normalizedTypes[$column] ?? 'text')));

            $rows = $rows->filter(static function (array $row) use ($column, $value, $mode, $type): bool {
                $candidate = $row[$column] ?? null;

                if (is_array($value)) {
                    $from = trim((string) ($value['from'] ?? ''));
                    $to = trim((string) ($value['to'] ?? ''));

                    if ($from === '' && $to === '') {
                        return true;
                    }

                    $candidateString = trim((string) ($candidate ?? ''));

                    if ($candidateString === '') {
                        return false;
                    }

                    if ($from !== '' && strnatcasecmp($candidateString, $from) < 0) {
                        return false;
                    }

                    if ($to !== '' && strnatcasecmp($candidateString, $to) > 0) {
                        return false;
                    }

                    return true;
                }

                $needle = trim((string) ($value ?? ''));

                if ($needle === '' && ! in_array($mode, ['is empty', 'is not empty'], true)) {
                    return true;
                }

                $candidateString = trim((string) ($candidate ?? ''));

                if ($type === 'number') {
                    $candidateNumber = is_numeric($candidate) ? (float) $candidate : null;
                    $needleNumber = is_numeric($needle) ? (float) $needle : null;

                    if ($needleNumber === null && in_array($mode, ['=', '!=', '>', '<', '>=', '<='], true)) {
                        return true;
                    }

                    return match ($mode) {
                        '=', 'is' => $candidateNumber === $needleNumber,
                        '!=', 'is not' => $candidateNumber !== $needleNumber,
                        '>', 'gt' => $candidateNumber !== null && $needleNumber !== null && $candidateNumber > $needleNumber,
                        '<', 'lt' => $candidateNumber !== null && $needleNumber !== null && $candidateNumber < $needleNumber,
                        '>=', 'gte' => $candidateNumber !== null && $needleNumber !== null && $candidateNumber >= $needleNumber,
                        '<=', 'lte' => $candidateNumber !== null && $needleNumber !== null && $candidateNumber <= $needleNumber,
                        default => $candidateNumber === $needleNumber,
                    };
                }

                $candidateLower = mb_strtolower($candidateString);
                $needleLower = mb_strtolower($needle);

                return match ($mode) {
                    'contains', 'bevat', 'like' => mb_stripos($candidateLower, $needleLower) !== false,
                    'does not contain', 'bevat niet', 'not like' => mb_stripos($candidateLower, $needleLower) === false,
                    'starts with' => str_starts_with($candidateLower, $needleLower),
                    'ends with' => str_ends_with($candidateLower, $needleLower),
                    'is empty' => $candidateString === '',
                    'is not empty' => $candidateString !== '',
                    '=', 'is' => $candidateLower === $needleLower,
                    '!=', 'is not' => $candidateLower !== $needleLower,
                    '>', 'gt' => strnatcasecmp($candidateString, $needle) > 0,
                    '<', 'lt' => strnatcasecmp($candidateString, $needle) < 0,
                    '>=', 'gte' => strnatcasecmp($candidateString, $needle) >= 0,
                    '<=', 'lte' => strnatcasecmp($candidateString, $needle) <= 0,
                    default => mb_stripos($candidateLower, $needleLower) !== false,
                };
            })->values();
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>|string  $payload
     * @return array<string, mixed>
     */
    private static function normalizeFilterPayload(array|string $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }
}
