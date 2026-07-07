<?php

namespace Tests\Unit\Query;

use App\Actions\Admin\Base\Query\ValidateSqlQueryAction;
use App\Support\Tenancy\TenantSqlTablePrefixer;
use Tests\TestCase;

class RunSqlQueryActionTablePrefixTest extends TestCase
{
    public function test_it_leaves_sql_unchanged_without_tenant_prefix(): void
    {
        config(['database.connections.tenant.prefix' => '']);

        $sql = 'select users.id from users join cms_pages on cms_pages.user_id = users.id';

        $this->assertSame($sql, $this->prefixer()->applyToSelectSql($sql));
    }

    public function test_it_prefixes_from_and_join_table_identifiers(): void
    {
        config(['database.connections.tenant.prefix' => 't_demo_']);

        $sql = 'select users.id from users join cms_pages on cms_pages.user_id = users.id';

        $this->assertSame(
            'select users.id from `t_demo_users` AS `users` join `t_demo_cms_pages` AS `cms_pages` on cms_pages.user_id = users.id',
            $this->prefixer()->applyToSelectSql($sql),
        );
    }

    public function test_it_preserves_existing_table_aliases(): void
    {
        config(['database.connections.tenant.prefix' => 't_demo_']);

        $sql = 'select u.id from users u join cms_pages as p on p.user_id = u.id';

        $this->assertSame(
            'select u.id from `t_demo_users` AS `u` join `t_demo_cms_pages` AS `p` on p.user_id = u.id',
            $this->prefixer()->applyToSelectSql($sql),
        );
    }

    public function test_it_does_not_double_prefix_physical_table_identifiers(): void
    {
        config(['database.connections.tenant.prefix' => 't_demo_']);

        $sql = 'select id from `t_demo_users` join cms_pages on cms_pages.user_id = users.id';

        $this->assertSame(
            'select id from `t_demo_users` AS `users` join `t_demo_cms_pages` AS `cms_pages` on cms_pages.user_id = users.id',
            $this->prefixer()->applyToSelectSql($sql),
        );
    }

    public function test_it_keeps_common_table_expression_names_logical(): void
    {
        config(['database.connections.tenant.prefix' => 't_demo_']);

        $sql = 'with recent_users as (select id from users) select recent_users.id from recent_users join cms_pages on cms_pages.user_id = recent_users.id';

        $this->assertSame(
            'with recent_users as (select id from `t_demo_users` AS `users`) select recent_users.id from recent_users join `t_demo_cms_pages` AS `cms_pages` on cms_pages.user_id = recent_users.id',
            $this->prefixer()->applyToSelectSql($sql),
        );
    }

    public function test_it_rejects_database_qualified_table_references(): void
    {
        $result = ValidateSqlQueryAction::handle('select id from shared_database.users');

        $this->assertFalse($result['is_valid']);
        $this->assertSame(__('query_builder_ui.runtime.database_qualified_tables_forbidden'), $result['message']);
    }

    private function prefixer(): TenantSqlTablePrefixer
    {
        return app(TenantSqlTablePrefixer::class);
    }
}
