<?php

namespace Tests\Unit\Database;

use App\Support\Database\SqlExecutionGuard;
use Tests\TestCase;

class SqlExecutionGuardTenantPrefixTest extends TestCase
{
    public function test_readonly_select_is_prefixed_in_shared_prefix_mode(): void
    {
        config(['database.connections.tenant.prefix' => 't_demo_']);

        $result = app(SqlExecutionGuard::class)->validateReadonly('select users.id from users join cms_pages on cms_pages.user_id = users.id', false);

        $this->assertFalse($result['error']);
        $this->assertSame(
            'select users.id from `t_demo_users` AS `users` join `t_demo_cms_pages` AS `cms_pages` on cms_pages.user_id = users.id',
            $result['sql'],
        );
    }

    public function test_readonly_sql_blocks_database_qualified_table_references(): void
    {
        config(['database.connections.tenant.prefix' => 't_demo_']);

        $result = app(SqlExecutionGuard::class)->validateReadonly('select id from shared_database.users', false);

        $this->assertTrue($result['error']);
        $this->assertSame(__('db_diagram_ui.sql_editor.errors.forbidden_patterns'), $result['message']);
    }

    public function test_readonly_metadata_statements_are_blocked_in_shared_prefix_mode(): void
    {
        config(['database.connections.tenant.prefix' => 't_demo_']);

        $result = app(SqlExecutionGuard::class)->validateReadonly('show tables', false);

        $this->assertTrue($result['error']);
        $this->assertSame(__('db_diagram_ui.sql_editor.errors.forbidden_patterns'), $result['message']);
    }

    public function test_destructive_sql_is_blocked_in_shared_prefix_mode(): void
    {
        config(['database.connections.tenant.prefix' => 't_demo_']);

        $result = app(SqlExecutionGuard::class)->validateDestructiveDml("update users set name = 'Blocked' where id = 1");

        $this->assertTrue($result['error']);
        $this->assertSame(__('db_diagram_ui.sql_editor.errors.forbidden_patterns'), $result['message']);
    }
}
