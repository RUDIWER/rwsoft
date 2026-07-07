<?php

namespace Tests\Unit\Tenancy;

use App\Support\Tenancy\TenantTableNames;
use Tests\TestCase;

class TenantTableNamesTest extends TestCase
{
    public function test_it_leaves_table_names_unchanged_without_prefix(): void
    {
        config(['database.connections.tenant.prefix' => '']);

        $tableNames = new TenantTableNames;

        $this->assertFalse($tableNames->usesPrefix());
        $this->assertSame('cms_pages', $tableNames->toPhysical('cms_pages'));
        $this->assertSame('cms_pages', $tableNames->toLogical('cms_pages'));
        $this->assertTrue($tableNames->belongsToTenant('cms_pages'));
    }

    public function test_it_converts_between_logical_and_physical_table_names(): void
    {
        config(['database.connections.tenant.prefix' => 't_demo_']);

        $tableNames = new TenantTableNames;

        $this->assertTrue($tableNames->usesPrefix());
        $this->assertSame('t_demo_cms_pages', $tableNames->toPhysical('cms_pages'));
        $this->assertSame('t_demo_cms_pages', $tableNames->toPhysical('t_demo_cms_pages'));
        $this->assertSame('cms_pages', $tableNames->toLogical('t_demo_cms_pages'));
        $this->assertSame('other_cms_pages', $tableNames->toLogical('other_cms_pages'));
        $this->assertTrue($tableNames->belongsToTenant('t_demo_cms_pages'));
        $this->assertFalse($tableNames->belongsToTenant('other_cms_pages'));
    }

    public function test_it_quotes_physical_table_names_safely(): void
    {
        $tableNames = new TenantTableNames;

        $this->assertSame('`cms_pages`', $tableNames->quote('cms_pages'));
        $this->assertSame('`bad``name`', $tableNames->quote('bad`name'));
    }
}
