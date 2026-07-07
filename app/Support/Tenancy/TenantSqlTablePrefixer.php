<?php

namespace App\Support\Tenancy;

class TenantSqlTablePrefixer
{
    /** @var array<int, string> */
    private const RESERVED_ALIAS_WORDS = [
        'where',
        'join',
        'left',
        'right',
        'inner',
        'outer',
        'cross',
        'full',
        'on',
        'group',
        'order',
        'having',
        'limit',
        'union',
    ];

    public function __construct(private readonly TenantTableNames $tableNames) {}

    public function applyToSelectSql(string $sql): string
    {
        if (! $this->tableNames->usesPrefix()) {
            return $sql;
        }

        $cteNames = $this->extractCommonTableExpressionNames($sql);

        return (string) preg_replace_callback(
            '/\b(from|join)\s+`?([A-Za-z_][A-Za-z0-9_]*)`?(?:\s+(?:as\s+)?`?(?!(?:where|join|left|right|inner|outer|cross|full|on|group|order|having|limit|union)\b)([A-Za-z_][A-Za-z0-9_]*)`?)?(?=\s|$|[),])/i',
            function (array $matches) use ($cteNames): string {
                $keyword = (string) ($matches[1] ?? '');
                $tableName = (string) ($matches[2] ?? '');
                $alias = (string) ($matches[3] ?? '');

                if ($tableName === '' || in_array(strtolower($tableName), $cteNames, true)) {
                    return (string) ($matches[0] ?? '');
                }

                if ($alias !== '' && in_array(strtolower($alias), self::RESERVED_ALIAS_WORDS, true)) {
                    $alias = '';
                }

                $logicalTableName = $this->tableNames->toLogical($tableName);
                $physicalTableName = $this->tableNames->quote($this->tableNames->toPhysical($tableName));
                $safeAlias = $alias !== '' ? $alias : $logicalTableName;

                return $keyword.' '.$physicalTableName.' AS '.$this->tableNames->quote($safeAlias);
            },
            $sql,
        );
    }

    public function hasDatabaseQualifiedTableReference(string $sql): bool
    {
        return preg_match('/\b(from|join)\s+`?[A-Za-z_][A-Za-z0-9_]*`?\s*\.\s*`?[A-Za-z_][A-Za-z0-9_]*`?/i', $sql) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function extractCommonTableExpressionNames(string $sql): array
    {
        if (preg_match('/^\s*with\b/i', $sql) !== 1) {
            return [];
        }

        preg_match_all('/(?:with|,)\s*`?([A-Za-z_][A-Za-z0-9_]*)`?\s+as\s*\(/i', $sql, $matches);

        return collect((array) ($matches[1] ?? []))
            ->map(static fn (mixed $name): string => strtolower((string) $name))
            ->filter(static fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();
    }
}
