<?php

namespace App\Support\Database;

class SqlExecutionGuard
{
    /**
     * @return array{error: bool, message?: string, sql?: string, statement?: string, stripped?: string}
     */
    public function validateReadonly(string $sql, bool $appendLimit = true, bool $rejectLimitOffset = false): array
    {
        $normalized = $this->normalizeSql($sql);
        if ($normalized['error']) {
            return $normalized;
        }

        $statement = $this->detectStatementType($normalized['stripped']);
        if (! in_array($statement, ['select', 'with', 'show', 'describe', 'desc', 'explain'], true)) {
            return $this->errorResult(__('db_diagram_ui.sql_editor.errors.readonly_only'));
        }

        if ($this->containsForbiddenPattern($normalized['stripped'])) {
            return $this->errorResult(__('db_diagram_ui.sql_editor.errors.forbidden_patterns'));
        }

        if ($rejectLimitOffset && preg_match('/\b(limit|offset)\b/i', $normalized['stripped']) === 1) {
            return $this->errorResult(__('db_diagram_ui.sql_editor.errors.limit_offset_forbidden'));
        }

        return [
            'error' => false,
            'sql' => $appendLimit ? $this->appendReadonlyLimit($normalized['sql'], $statement) : $normalized['sql'],
            'statement' => $statement,
            'stripped' => $normalized['stripped'],
        ];
    }

    /**
     * @return array{error: bool, message?: string, sql?: string, statement?: string, stripped?: string}
     */
    public function validateDestructiveDml(string $sql): array
    {
        $normalized = $this->normalizeSql($sql);
        if ($normalized['error']) {
            return $normalized;
        }

        $statement = $this->detectStatementType($normalized['stripped']);
        if (! in_array($statement, ['insert', 'update', 'delete', 'replace'], true)) {
            return $this->errorResult(__('db_diagram_ui.sql_editor.errors.dml_only'));
        }

        if ($this->containsForbiddenPattern($normalized['stripped'])) {
            return $this->errorResult(__('db_diagram_ui.sql_editor.errors.forbidden_patterns'));
        }

        if (preg_match('/\b(DROP|ALTER|TRUNCATE|CREATE|GRANT|REVOKE|MERGE|CALL|USE|LOCK|UNLOCK)\b/i', $normalized['stripped']) === 1) {
            return $this->errorResult(__('db_diagram_ui.sql_editor.errors.ddl_forbidden'));
        }

        return [
            'error' => false,
            'sql' => $normalized['sql'],
            'statement' => $statement,
            'stripped' => $normalized['stripped'],
        ];
    }

    /**
     * @return array{error: bool, message?: string, sql?: string, stripped?: string}
     */
    private function normalizeSql(string $sql): array
    {
        $trimmed = trim($sql);
        if ($trimmed === '') {
            return $this->errorResult(__('db_diagram_ui.sql_editor.validation.query_required'));
        }

        $normalizedSql = $this->stripSingleTrailingSemicolon($trimmed);
        $stripped = $this->stripSqlStringsAndComments($normalizedSql);
        if ($stripped === '') {
            return $this->errorResult(__('db_diagram_ui.sql_editor.errors.query_invalid'));
        }

        if (str_contains($stripped, ';')) {
            return $this->errorResult(__('db_diagram_ui.sql_editor.errors.single_query_only'));
        }

        return [
            'error' => false,
            'sql' => $normalizedSql,
            'stripped' => $stripped,
        ];
    }

    private function stripSingleTrailingSemicolon(string $sql): string
    {
        return (string) preg_replace('/;\s*$/', '', trim($sql));
    }

    private function stripSqlStringsAndComments(string $sql): string
    {
        $stripped = preg_replace('/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"/', '', $sql);
        if (! is_string($stripped)) {
            return '';
        }

        $stripped = preg_replace('/--.*(\r?\n|$)/', '', $stripped);
        $stripped = preg_replace('/\/\*[\s\S]*?\*\//', '', $stripped);

        return is_string($stripped) ? trim($stripped) : '';
    }

    private function containsForbiddenPattern(string $stripped): bool
    {
        return preg_match('/\bINTO\s+OUTFILE\b|\bINTO\s+DUMPFILE\b|\bLOAD_FILE\s*\(|\bSLEEP\s*\(|\bBENCHMARK\s*\(/i', $stripped) === 1;
    }

    private function detectStatementType(string $stripped): string
    {
        if (preg_match('/^\s*([a-z]+)/i', $stripped, $matches) !== 1) {
            return '';
        }

        return strtolower((string) ($matches[1] ?? ''));
    }

    private function appendReadonlyLimit(string $sql, string $statement): string
    {
        if (! in_array($statement, ['select', 'with'], true)) {
            return $sql;
        }

        if (preg_match('/\blimit\s+\d+(\s*,\s*\d+)?\s*$/i', trim($sql)) === 1) {
            return $sql;
        }

        return rtrim($sql).' LIMIT 1000';
    }

    /**
     * @return array{error: true, message: string}
     */
    private function errorResult(string $message): array
    {
        return [
            'error' => true,
            'message' => $message,
        ];
    }
}
