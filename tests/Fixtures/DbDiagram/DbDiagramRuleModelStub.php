<?php

namespace Tests\Fixtures\DbDiagram;

use Illuminate\Database\Eloquent\Model;

class DbDiagramRuleModelStub extends Model
{
    protected $table = 'db_diagram_rule_models';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    public static function rules(int|string $id = 0): array
    {
        return [
            'title' => 'required|string|max:80',
        ];
    }
}
