<?php

namespace Tests\Unit;

use App\Actions\Admin\Base\Query\ParseSqlToBuilderAction;
use Tests\TestCase;

class ParseSqlToBuilderActionTest extends TestCase
{
    public function test_it_converts_simple_select_sql_to_builder_payload(): void
    {
        $result = ParseSqlToBuilderAction::handle('select `id`, `name`, `email` from `users`');

        $this->assertTrue((bool) ($result['convertible'] ?? false));
        $this->assertSame('users', data_get($result, 'builder_payload.table_name'));
        $this->assertSame(
            ['users.id', 'users.name', 'users.email'],
            data_get($result, 'builder_payload.selected_fields'),
        );
        $this->assertSame([], data_get($result, 'builder_payload.join_rows'));
        $this->assertSame([], data_get($result, 'builder_payload.where_rows'));
    }

    public function test_it_rejects_complex_sql_for_builder_conversion(): void
    {
        $result = ParseSqlToBuilderAction::handle(
            'with latest as (select * from users) select * from latest',
        );

        $this->assertFalse((bool) ($result['convertible'] ?? true));
        $this->assertSame([], data_get($result, 'builder_payload'));
        $this->assertNotSame('', (string) ($result['message'] ?? ''));
    }
}
