<?php

namespace App\Actions\Admin\Base\Query;

class ParseSqlToBuilderAction
{
    /**
     * @return array{convertible:bool,message:string,builder_payload:array<string,mixed>}
     */
    public static function handle(string $sql): array
    {
        $normalizedSql = trim((string) $sql);

        if ($normalizedSql === '') {
            return self::failure('Lege SQL query kan niet omgezet worden naar Query Builder.');
        }

        if (preg_match('/\b(with|union|case)\b/i', $normalizedSql) === 1) {
            return self::failure('Deze query gebruikt niet-ondersteunde SQL patronen voor automatische omzetting.');
        }

        if (preg_match('/^\s*select\s+(distinct\s+)?(.+?)\s+from\s+`?([a-zA-Z0-9_]+)`?\s*$/i', $normalizedSql, $matches) !== 1) {
            return self::failure('Deze query kan niet betrouwbaar omgezet worden naar Query Builder.');
        }

        $selectPart = trim((string) ($matches[2] ?? ''));
        $tableName = trim((string) ($matches[3] ?? ''));

        if ($tableName === '') {
            return self::failure('Basistabel ontbreekt in de SQL query.');
        }

        $selectSegments = self::splitSelectSegments($selectPart);

        if ($selectSegments === []) {
            return self::failure('Geen geldige SELECT kolommen gevonden voor omzetting.');
        }

        $selectedFields = [];

        foreach ($selectSegments as $segment) {
            if ($segment === '*' || str_contains($segment, '(') || str_contains($segment, ')')) {
                return self::failure('Functies, wildcard of complexe expressies zijn niet ondersteund in auto-omzetting.');
            }

            $normalizedField = self::normalizeFieldExpression($segment, $tableName);

            if ($normalizedField === '') {
                return self::failure('Een of meer SELECT velden konden niet betrouwbaar omgezet worden.');
            }

            $selectedFields[] = $normalizedField;
        }

        $selectedFields = array_values(array_unique($selectedFields));

        if ($selectedFields === []) {
            return self::failure('Geen ondersteunde SELECT velden gevonden voor Query Builder.');
        }

        return [
            'convertible' => true,
            'message' => 'Query werd automatisch omgezet naar Query Builder.',
            'builder_payload' => [
                'table_name' => $tableName,
                'selected_fields' => $selectedFields,
                'join_rows' => [],
                'where_rows' => [],
                'group_by' => false,
                'group_rows' => [],
                'aggregate_rows' => [],
                'having_rows' => [],
            ],
        ];
    }

    /**
     * @return array{convertible:bool,message:string,builder_payload:array<string,mixed>}
     */
    private static function failure(string $message): array
    {
        return [
            'convertible' => false,
            'message' => $message,
            'builder_payload' => [],
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function splitSelectSegments(string $selectPart): array
    {
        $segments = array_map(
            static fn (string $segment): string => trim($segment),
            explode(',', $selectPart),
        );

        return array_values(array_filter($segments, static fn (string $segment): bool => $segment !== ''));
    }

    private static function normalizeFieldExpression(string $expression, string $baseTable): string
    {
        $normalized = trim(preg_replace('/\s+as\s+/i', ' as ', $expression) ?? $expression);
        $fieldPart = explode(' as ', $normalized, 2)[0] ?? $normalized;
        $fieldPart = trim($fieldPart);

        if (preg_match('/^`?([a-zA-Z0-9_]+)`?\.\s*`?([a-zA-Z0-9_]+)`?$/', $fieldPart, $matches) === 1) {
            return sprintf('%s.%s', (string) $matches[1], (string) $matches[2]);
        }

        if (preg_match('/^`?([a-zA-Z0-9_]+)`?$/', $fieldPart, $matches) === 1) {
            return sprintf('%s.%s', $baseTable, (string) $matches[1]);
        }

        return '';
    }
}
