<?php

namespace Tests\Unit;

use App\Actions\Admin\Base\Query\BuildQueryFromBuilderAction;
use Tests\TestCase;

class BuildQueryFromBuilderActionTest extends TestCase
{
    public function test_it_builds_builder_sql_with_concat_formula_and_having(): void
    {
        $payload = [
            'table_name' => 'users',
            'all_fields' => false,
            'distinct_select' => true,
            'selected_fields' => ['users.id'],
            'join_rows' => [],
            'where_rows' => [
                [
                    'id' => 1,
                    'whereFieldAndOr' => 'AND',
                    'whereField' => 'users.status',
                    'whereFieldCondition' => '=',
                    'varOrValue' => 'Vaste waarde',
                    'value' => 'active',
                ],
                [
                    'id' => 2,
                    'subRow' => true,
                    'parentId' => 1,
                    'whereFieldAndOr' => 'OR',
                    'whereField' => 'users.status',
                    'whereFieldCondition' => '=',
                    'varOrValue' => 'Vaste waarde',
                    'value' => 'pending',
                ],
            ],
            'group_by' => true,
            'group_rows' => ['users.id'],
            'aggregate_rows' => [
                [
                    'func' => 'CONCAT',
                    'fields' => ['users.first_name', 'users.last_name'],
                    'separator' => ' ',
                    'alias' => 'full_name',
                ],
                [
                    'func' => 'SUM',
                    'field' => 'users.points',
                    'alias' => 'total_points',
                ],
                [
                    'func' => 'FORMULA',
                    'formula' => 'SUM(users.points) / NULLIF(COUNT(users.id), 0)',
                    'alias' => 'avg_points_formula',
                ],
            ],
            'having_rows' => [
                [
                    'whereFieldAndOr' => 'AND',
                    'whereField' => 'total_points',
                    'whereFieldCondition' => '>',
                    'varOrValue' => 'Parameter',
                    'variabele' => 'min_total',
                    'testValue' => '10',
                ],
            ],
        ];

        $result = BuildQueryFromBuilderAction::handle($payload);

        $this->assertStringContainsString('SELECT DISTINCT users.id', $result['query']);
        $this->assertStringContainsString("CONCAT_WS(' ', users.first_name, users.last_name) AS full_name", $result['query']);
        $this->assertStringContainsString('SUM(users.points) AS total_points', $result['query']);
        $this->assertStringContainsString('SUM(users.points) / NULLIF(COUNT(users.id), 0) AS avg_points_formula', $result['query']);
        $this->assertStringContainsString("WHERE (users.status = 'active' OR users.status = 'pending')", $result['query']);
        $this->assertStringContainsString('GROUP BY users.id', $result['query']);
        $this->assertStringContainsString('HAVING total_points > :min_total', $result['query']);
        $this->assertStringContainsString('HAVING total_points > 10', $result['test_query']);
    }

    public function test_it_skips_invalid_formula_expression(): void
    {
        $payload = [
            'table_name' => 'users',
            'all_fields' => false,
            'selected_fields' => ['users.id'],
            'group_by' => false,
            'aggregate_rows' => [
                [
                    'func' => 'FORMULA',
                    'formula' => 'SUM(users.points); DROP TABLE users',
                    'alias' => 'dangerous_formula',
                ],
                [
                    'func' => 'COUNT',
                    'field' => 'users.id',
                    'alias' => 'total_users',
                ],
            ],
        ];

        $result = BuildQueryFromBuilderAction::handle($payload);

        $this->assertStringNotContainsString('dangerous_formula', $result['query']);
        $this->assertStringContainsString('COUNT(users.id) AS total_users', $result['query']);
    }

    public function test_it_allows_formula_using_previous_known_alias(): void
    {
        $payload = [
            'table_name' => 'users',
            'selected_fields' => ['users.id'],
            'aggregate_rows' => [
                [
                    'func' => 'SUM',
                    'field' => 'users.points',
                    'alias' => 'total_points',
                ],
                [
                    'func' => 'FORMULA',
                    'formula' => 'total_points / 2',
                    'alias' => 'half_points',
                ],
            ],
        ];

        $result = BuildQueryFromBuilderAction::handle($payload);

        $this->assertStringContainsString('SUM(users.points) AS total_points', $result['query']);
        $this->assertStringContainsString('total_points / 2 AS half_points', $result['query']);
    }

    public function test_it_rejects_formula_with_unknown_alias_reference(): void
    {
        $payload = [
            'table_name' => 'users',
            'selected_fields' => ['users.id'],
            'aggregate_rows' => [
                [
                    'func' => 'FORMULA',
                    'formula' => 'unknown_alias / 2',
                    'alias' => 'invalid_formula',
                ],
                [
                    'func' => 'COUNT',
                    'field' => 'users.id',
                    'alias' => 'total_users',
                ],
            ],
        ];

        $result = BuildQueryFromBuilderAction::handle($payload);

        $this->assertStringNotContainsString('unknown_alias / 2 AS invalid_formula', $result['query']);
        $this->assertStringContainsString('COUNT(users.id) AS total_users', $result['query']);
    }

    public function test_validate_returns_row_level_errors_for_invalid_builder_aggregate_and_having(): void
    {
        $errors = BuildQueryFromBuilderAction::validate([
            'table_name' => 'users',
            'selected_fields' => ['users.id'],
            'group_rows' => ['users.id'],
            'aggregate_rows' => [
                [
                    'func' => 'FORMULA',
                    'formula' => 'unknown_alias + 1',
                    'alias' => 'broken_formula',
                ],
            ],
            'having_rows' => [
                [
                    'whereField' => 'broken_formula',
                    'whereFieldCondition' => '>',
                    'varOrValue' => 'Parameter',
                    'variabele' => '123 invalid',
                    'value' => '',
                ],
            ],
        ]);

        $this->assertArrayHasKey('aggregate_rows.0.formula', $errors);
        $this->assertArrayHasKey('having_rows.0.variabele', $errors);
    }

    public function test_validate_detects_duplicate_aggregate_aliases(): void
    {
        $errors = BuildQueryFromBuilderAction::validate([
            'table_name' => 'users',
            'aggregate_rows' => [
                [
                    'func' => 'COUNT',
                    'field' => 'users.id',
                    'alias' => 'total_users',
                ],
                [
                    'func' => 'SUM',
                    'field' => 'users.id',
                    'alias' => 'total_users',
                ],
            ],
        ]);

        $this->assertArrayHasKey('aggregate_rows.1.alias', $errors);
    }

    public function test_validate_rejects_subrow_without_existing_parent(): void
    {
        $errors = BuildQueryFromBuilderAction::validate([
            'table_name' => 'users',
            'where_rows' => [
                [
                    'id' => 2,
                    'subRow' => true,
                    'parentId' => 999,
                    'whereField' => 'users.id',
                    'whereFieldCondition' => '>',
                    'varOrValue' => 'Vaste waarde',
                    'value' => '0',
                ],
            ],
        ]);

        $this->assertArrayHasKey('where_rows.0.parentId', $errors);
    }

    public function test_validate_rejects_subrow_with_subrow_as_parent(): void
    {
        $errors = BuildQueryFromBuilderAction::validate([
            'table_name' => 'users',
            'having_rows' => [
                [
                    'id' => 1,
                    'subRow' => true,
                    'parentId' => 2,
                    'whereField' => 'users.id',
                    'whereFieldCondition' => '>',
                    'varOrValue' => 'Vaste waarde',
                    'value' => '0',
                ],
                [
                    'id' => 2,
                    'subRow' => true,
                    'parentId' => 3,
                    'whereField' => 'users.id',
                    'whereFieldCondition' => '>',
                    'varOrValue' => 'Vaste waarde',
                    'value' => '1',
                ],
                [
                    'id' => 3,
                    'subRow' => false,
                    'whereField' => 'users.id',
                    'whereFieldCondition' => '>',
                    'varOrValue' => 'Vaste waarde',
                    'value' => '2',
                ],
            ],
        ]);

        $this->assertArrayHasKey('having_rows.0.parentId', $errors);
    }

    public function test_it_builds_having_with_parent_and_subrow_grouping(): void
    {
        $payload = [
            'table_name' => 'users',
            'selected_fields' => ['users.id'],
            'group_by' => true,
            'group_rows' => ['users.id'],
            'aggregate_rows' => [
                [
                    'func' => 'COUNT',
                    'field' => 'users.id',
                    'alias' => 'total_users',
                ],
            ],
            'having_rows' => [
                [
                    'id' => 10,
                    'subRow' => false,
                    'whereFieldAndOr' => 'AND',
                    'whereField' => 'total_users',
                    'whereFieldCondition' => '>',
                    'varOrValue' => 'Vaste waarde',
                    'value' => '0',
                ],
                [
                    'id' => 11,
                    'subRow' => true,
                    'parentId' => 10,
                    'whereFieldAndOr' => 'OR',
                    'whereField' => 'total_users',
                    'whereFieldCondition' => '=',
                    'varOrValue' => 'Vaste waarde',
                    'value' => '999',
                ],
            ],
        ];

        $result = BuildQueryFromBuilderAction::handle($payload);

        $this->assertStringContainsString('HAVING (total_users > 0 OR total_users = 999)', $result['query']);
    }

    public function test_it_builds_between_and_is_null_conditions(): void
    {
        $payload = [
            'table_name' => 'users',
            'selected_fields' => ['users.id'],
            'where_rows' => [
                [
                    'id' => 1,
                    'whereFieldAndOr' => 'AND',
                    'whereField' => 'users.id',
                    'whereFieldCondition' => 'BETWEEN',
                    'varOrValue' => 'Parameter',
                    'variabele' => 'from_id',
                    'variabele_to' => 'to_id',
                    'testValue' => '10',
                    'testValueTo' => '50',
                ],
                [
                    'id' => 2,
                    'whereFieldAndOr' => 'AND',
                    'whereField' => 'users.deleted_at',
                    'whereFieldCondition' => 'IS NULL',
                    'varOrValue' => 'Vaste waarde',
                ],
            ],
        ];

        $result = BuildQueryFromBuilderAction::handle($payload);

        $this->assertStringContainsString('WHERE users.id BETWEEN :from_id AND :to_id', $result['query']);
        $this->assertStringContainsString('AND users.deleted_at IS NULL', $result['query']);
        $this->assertStringContainsString('WHERE users.id BETWEEN 10 AND 50', $result['test_query']);
    }

    public function test_validate_requires_second_parameter_for_between_in_having(): void
    {
        $errors = BuildQueryFromBuilderAction::validate([
            'table_name' => 'users',
            'group_rows' => ['users.id'],
            'aggregate_rows' => [
                [
                    'func' => 'COUNT',
                    'field' => 'users.id',
                    'alias' => 'total_users',
                ],
            ],
            'having_rows' => [
                [
                    'whereField' => 'total_users',
                    'whereFieldCondition' => 'BETWEEN',
                    'varOrValue' => 'Parameter',
                    'variabele' => 'min_total',
                ],
            ],
        ]);

        $this->assertArrayHasKey('having_rows.0.variabele_to', $errors);
    }

    public function test_validate_requires_second_parameter_for_between_in_where(): void
    {
        $errors = BuildQueryFromBuilderAction::validate([
            'table_name' => 'users',
            'where_rows' => [
                [
                    'whereField' => 'users.id',
                    'whereFieldCondition' => 'BETWEEN',
                    'varOrValue' => 'Parameter',
                    'variabele' => 'from_id',
                ],
            ],
        ]);

        $this->assertArrayHasKey('where_rows.0.variabele_to', $errors);
    }
}
