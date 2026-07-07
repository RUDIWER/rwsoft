<?php

namespace App\Support\Tenancy;

class TenantTableNames
{
    public function prefix(): string
    {
        return (string) config('database.connections.tenant.prefix', '');
    }

    public function usesPrefix(): bool
    {
        return $this->prefix() !== '';
    }

    public function toPhysical(string $tableName): string
    {
        $tableName = trim($tableName);
        $prefix = $this->prefix();

        if ($prefix === '' || str_starts_with($tableName, $prefix)) {
            return $tableName;
        }

        return $prefix.$tableName;
    }

    public function toLogical(string $tableName): string
    {
        $tableName = trim($tableName);
        $prefix = $this->prefix();

        if ($prefix === '' || ! str_starts_with($tableName, $prefix)) {
            return $tableName;
        }

        return substr($tableName, strlen($prefix));
    }

    public function belongsToTenant(string $physicalTableName): bool
    {
        $prefix = $this->prefix();

        return $prefix === '' || str_starts_with($physicalTableName, $prefix);
    }

    public function quote(string $tableName): string
    {
        return '`'.str_replace('`', '``', $tableName).'`';
    }
}
