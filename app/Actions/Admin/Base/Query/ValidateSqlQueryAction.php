<?php

namespace App\Actions\Admin\Base\Query;

use App\Support\Tenancy\TenantSqlTablePrefixer;

class ValidateSqlQueryAction
{
    /**
     * @return array{is_valid:bool,message:string,sql:string,bindings:array<int,string>}
     */
    public static function handle(string $sql): array
    {
        $normalizedSql = trim((string) $sql);

        if ($normalizedSql === '') {
            return [
                'is_valid' => false,
                'message' => 'Query is verplicht.',
                'sql' => '',
                'bindings' => [],
            ];
        }

        if (str_contains($normalizedSql, ';')) {
            return [
                'is_valid' => false,
                'message' => "Gebruik geen ';' in SQL queries.",
                'sql' => $normalizedSql,
                'bindings' => [],
            ];
        }

        if (self::containsSqlComments($normalizedSql)) {
            return [
                'is_valid' => false,
                'message' => 'SQL comments zijn niet toegestaan.',
                'sql' => $normalizedSql,
                'bindings' => [],
            ];
        }

        if (! preg_match('/^\s*(select|with)\b/i', $normalizedSql)) {
            return [
                'is_valid' => false,
                'message' => 'Alleen SELECT of CTE queries zijn toegestaan.',
                'sql' => $normalizedSql,
                'bindings' => [],
            ];
        }

        if (self::containsForbiddenKeyword($normalizedSql)) {
            return [
                'is_valid' => false,
                'message' => 'De query bevat een niet-toegelaten SQL operatie.',
                'sql' => $normalizedSql,
                'bindings' => [],
            ];
        }

        if (app(TenantSqlTablePrefixer::class)->hasDatabaseQualifiedTableReference($normalizedSql)) {
            return [
                'is_valid' => false,
                'message' => __('query_builder_ui.runtime.database_qualified_tables_forbidden'),
                'sql' => $normalizedSql,
                'bindings' => [],
            ];
        }

        if (preg_match('/\blimit\b/i', $normalizedSql) === 1) {
            return [
                'is_valid' => false,
                'message' => 'Gebruik geen LIMIT in SQL queries.',
                'sql' => $normalizedSql,
                'bindings' => [],
            ];
        }

        return [
            'is_valid' => true,
            'message' => '',
            'sql' => $normalizedSql,
            'bindings' => self::extractBindings($normalizedSql),
        ];
    }

    private static function containsSqlComments(string $sql): bool
    {
        return preg_match('/(--|#|\/\*)/', $sql) === 1;
    }

    private static function containsForbiddenKeyword(string $sql): bool
    {
        return preg_match(
            '/\b(insert|update|delete|drop|truncate|alter|create|replace|grant|revoke|merge|call|execute|exec|attach|detach|vacuum|pragma)\b/i',
            $sql,
        ) === 1;
    }

    /**
     * @return array<int, string>
     */
    private static function extractBindings(string $sql): array
    {
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);

        return collect((array) ($matches[1] ?? []))
            ->map(static fn (mixed $binding): string => (string) $binding)
            ->filter(static fn (string $binding): bool => $binding !== '')
            ->unique()
            ->values()
            ->all();
    }
}
