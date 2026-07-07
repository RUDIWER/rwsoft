<?php

namespace App\Http\Requests\Admin\Dev\Query;

use App\Actions\Admin\Base\Query\BuildQueryFromBuilderAction;
use App\Models\Query\Query;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $queryId = $this->routeQueryId();

        return [
            'description' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'alpha_dash',
                Rule::unique('queries', 'slug')->ignore($queryId),
            ],
            'memo' => ['nullable', 'string'],
            'query_mode' => ['required', 'string', Rule::in(['sql', 'builder'])],
            'output_mode' => ['required', 'string', Rule::in(['table', 'report', 'excel', 'chart'])],
            'report_data_source' => [
                Rule::requiredIf(fn (): bool => (string) $this->input('output_mode') === 'report'),
                'nullable',
                'string',
                Rule::in(['query', 'external']),
            ],
            'report_output_format' => [
                Rule::requiredIf(fn (): bool => (string) $this->input('output_mode') === 'report'),
                'nullable',
                'string',
                Rule::in(['same_format', 'pdf', 'csv']),
            ],
            'report_template_upload' => [
                'nullable',
                'file',
                'mimes:xlsx,ods,docx,odt',
                'max:20480',
            ],
            'table_name' => ['nullable', 'string', 'max:160'],
            'all_fields' => ['nullable', 'boolean'],
            'distinct_select' => ['nullable', 'boolean'],
            'query' => ['nullable', 'string'],
            'test_query' => ['nullable', 'string'],
            'selected_fields' => ['nullable', 'array'],
            'selected_fields.*' => ['nullable', 'string', 'max:190'],
            'join_rows' => ['nullable', 'array'],
            'join_rows.*.joinType' => ['nullable', 'string', Rule::in(['LEFT', 'RIGHT', 'INNER'])],
            'join_rows.*.originTable' => ['nullable', 'string', 'max:160'],
            'join_rows.*.relTable' => ['nullable', 'string', 'max:160'],
            'join_rows.*.relFieldT1' => ['nullable', 'string', 'max:160'],
            'join_rows.*.relFieldT2' => ['nullable', 'string', 'max:160'],
            'where_rows' => ['nullable', 'array'],
            'where_rows.*.whereFieldAndOr' => ['nullable', 'string', Rule::in(['AND', 'OR'])],
            'where_rows.*.id' => ['nullable', 'integer'],
            'where_rows.*.subRow' => ['nullable', 'boolean'],
            'where_rows.*.parentId' => ['nullable', 'integer'],
            'where_rows.*.paddingLeft' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'where_rows.*.whereField' => ['nullable', 'string', 'max:190'],
            'where_rows.*.whereFieldCondition' => ['nullable', 'string', Rule::in(['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'IS', 'IS NOT', 'IS NULL', 'IS NOT NULL'])],
            'where_rows.*.varOrValue' => ['nullable', 'string', Rule::in(['Waarde', 'Vaste waarde', 'Parameter', 'Systeemvariabele', 'Json Array'])],
            'where_rows.*.value' => ['nullable'],
            'where_rows.*.value_to' => ['nullable'],
            'where_rows.*.variabele' => ['nullable', 'string', 'max:120'],
            'where_rows.*.variabele_to' => ['nullable', 'string', 'max:120'],
            'where_rows.*.testValue' => ['nullable', 'string', 'max:255'],
            'where_rows.*.testValueTo' => ['nullable', 'string', 'max:255'],
            'group_by' => ['nullable', 'boolean'],
            'group_rows' => ['nullable', 'array'],
            'group_rows.*' => ['nullable', 'string', 'max:190'],
            'aggregate_rows' => ['nullable', 'array'],
            'aggregate_rows.*.func' => ['nullable', 'string', Rule::in(['COUNT', 'SUM', 'MIN', 'MAX', 'AVG', 'GROUP_CONCAT', 'CONCAT', 'FORMULA'])],
            'aggregate_rows.*.field' => ['nullable', 'string', 'max:255'],
            'aggregate_rows.*.fields' => ['nullable', 'array'],
            'aggregate_rows.*.fields.*' => ['nullable', 'string', 'max:190'],
            'aggregate_rows.*.formula' => ['nullable', 'string', 'max:1000'],
            'aggregate_rows.*.alias' => ['nullable', 'string', 'max:120'],
            'aggregate_rows.*.distinct' => ['nullable', 'boolean'],
            'aggregate_rows.*.separator' => ['nullable', 'string', 'max:20'],
            'having_rows' => ['nullable', 'array'],
            'having_rows.*.whereFieldAndOr' => ['nullable', 'string', Rule::in(['AND', 'OR'])],
            'having_rows.*.id' => ['nullable', 'integer'],
            'having_rows.*.subRow' => ['nullable', 'boolean'],
            'having_rows.*.parentId' => ['nullable', 'integer'],
            'having_rows.*.paddingLeft' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'having_rows.*.whereField' => ['nullable', 'string', 'max:190'],
            'having_rows.*.whereFieldCondition' => ['nullable', 'string', Rule::in(['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'IS', 'IS NOT', 'IS NULL', 'IS NOT NULL'])],
            'having_rows.*.varOrValue' => ['nullable', 'string', Rule::in(['Waarde', 'Vaste waarde', 'Parameter', 'Systeemvariabele', 'Json Array'])],
            'having_rows.*.value' => ['nullable'],
            'having_rows.*.value_to' => ['nullable'],
            'having_rows.*.variabele' => ['nullable', 'string', 'max:120'],
            'having_rows.*.variabele_to' => ['nullable', 'string', 'max:120'],
            'having_rows.*.testValue' => ['nullable', 'string', 'max:255'],
            'having_rows.*.testValueTo' => ['nullable', 'string', 'max:255'],
            'binding_rows' => ['nullable', 'array'],
            'binding_rows.*.type' => [
                'nullable',
                'string',
                Rule::in(['text', 'number', 'number_range', 'date', 'date_range', 'source_select']),
            ],
            'binding_rows.*.parameter' => ['nullable', 'string', 'max:120'],
            'binding_rows.*.parameter_to' => ['nullable', 'string', 'max:120'],
            'binding_rows.*.source_table_id' => ['nullable', 'integer', 'exists:query_builder_select_tables,id'],
            'binding_rows.*.title' => ['nullable', 'string', 'max:160'],
            'binding_rows.*.title_key' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
            'binding_rows.*.prompt' => ['nullable', 'string', 'max:255'],
            'binding_rows.*.prompt_key' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
            'binding_rows.*.sort_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'chart_config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'description.required' => __('query_builder_ui.validation.description_required'),
            'slug.required' => __('query_builder_ui.validation.slug_required'),
            'slug.unique' => __('query_builder_ui.validation.slug_unique'),
            'report_data_source.required' => __('query_builder_ui.validation.report_data_source_required'),
            'report_output_format.required' => __('query_builder_ui.validation.report_output_format_required'),
            'report_template_upload.mimes' => __('query_builder_ui.validation.report_template_mimes'),
            'report_template_upload.max' => __('query_builder_ui.validation.report_template_max'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $description = (string) $this->input('description', '');
        $slug = (string) $this->input('slug', '');

        if (trim($slug) === '') {
            $slug = str($description)->slug()->toString();
        }

        $outputMode = (string) $this->input('output_mode', 'table');
        $reportDataSource = $this->input('report_data_source');
        $reportOutputFormat = $this->input('report_output_format');
        $chartConfig = $this->input('chart_config');

        if ($outputMode !== 'report') {
            $reportDataSource = null;
            $reportOutputFormat = null;
        }

        if ($outputMode === 'report' && (! is_string($reportDataSource) || trim($reportDataSource) === '')) {
            $reportDataSource = 'query';
        }

        if ($outputMode === 'report' && (! is_string($reportOutputFormat) || trim($reportOutputFormat) === '')) {
            $reportOutputFormat = 'same_format';
        }

        if ($outputMode !== 'chart') {
            $chartConfig = null;
        }

        $bindingRows = collect((array) $this->input('binding_rows', []))
            ->map(static function (mixed $row): array {
                if (! is_array($row)) {
                    return [];
                }

                if (! array_key_exists('title_key', $row) && array_key_exists('titleKey', $row)) {
                    $row['title_key'] = $row['titleKey'];
                }

                if (! array_key_exists('prompt_key', $row) && array_key_exists('promptKey', $row)) {
                    $row['prompt_key'] = $row['promptKey'];
                }

                return $row;
            })
            ->values()
            ->all();

        $this->merge([
            'slug' => str($slug)->slug()->toString(),
            'report_data_source' => $reportDataSource,
            'report_output_format' => $reportOutputFormat,
            'chart_config' => $chartConfig,
            'binding_rows' => $bindingRows,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->input('query_mode', 'sql') !== 'builder') {
                return;
            }

            $errors = BuildQueryFromBuilderAction::validate($this->all());

            foreach ($errors as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });
    }

    private function routeQueryId(): int
    {
        $query = $this->route('query');

        if ($query instanceof Query) {
            return (int) $query->id;
        }

        if (is_numeric($query)) {
            return max(0, (int) $query);
        }

        return 0;
    }
}
