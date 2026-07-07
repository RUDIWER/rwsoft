<?php

namespace App\Support\Database;

use App\Models\User;

class DatabaseAccessGate
{
    /**
     * @param  array<int, string>  $requiredFlags
     */
    public static function canAccess(mixed $user, string $routeName, array $requiredFlags = []): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (! $user->canAccessRoute($routeName)) {
            return false;
        }

        foreach ($requiredFlags as $flag) {
            if (! self::hasFlag($user, $flag)) {
                return false;
            }
        }

        return true;
    }

    private static function hasFlag(User $user, string $flag): bool
    {
        return match ($flag) {
            'view' => (bool) ($user->getAttribute('database_view_access') ?? false),
            'edit' => (bool) ($user->getAttribute('database_edit_access') ?? false),
            'add' => (bool) ($user->getAttribute('database_add_access') ?? false),
            'delete' => (bool) ($user->getAttribute('database_delete_access') ?? false),
            'export' => (bool) ($user->getAttribute('database_export_access') ?? false),
            'sql' => (bool) ($user->getAttribute('database_sql_query_access') ?? false),
            'sql-destructive' => (bool) ($user->getAttribute('database_sql_destructive_access') ?? false),
            'full_backup' => (bool) ($user->getAttribute('database_full_backup_access') ?? false),
            default => false,
        };
    }
}
