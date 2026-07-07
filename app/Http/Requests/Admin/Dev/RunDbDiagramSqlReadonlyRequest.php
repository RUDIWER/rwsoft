<?php

namespace App\Http\Requests\Admin\Dev;

use App\Support\Database\DatabaseAccessGate;
use Illuminate\Foundation\Http\FormRequest;

class RunDbDiagramSqlReadonlyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return DatabaseAccessGate::canAccess(
            $this->user(),
            'admin.db-diagram.sql-execute',
            ['view', 'sql']
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'query.required' => __('db_diagram_ui.sql_editor.validation.query_required'),
            'query.string' => __('db_diagram_ui.sql_editor.validation.query_string'),
            'query.max' => __('db_diagram_ui.sql_editor.validation.query_max'),
        ];
    }
}
