<?php

namespace App\Models\Query;

use App\Models\Report\ReportGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Query extends Model
{
    protected $fillable = [
        'slug',
        'description',
        'memo',
        'query_mode',
        'output_mode',
        'report_data_source',
        'report_output_format',
        'report_template_path',
        'report_template_filename',
        'report_template_extension',
        'report_template_size_kb',
        'table_name',
        'all_fields',
        'distinct_select',
        'query',
        'test_query',
        'selected_fields',
        'join_rows',
        'where_rows',
        'group_by',
        'group_rows',
        'aggregate_rows',
        'having_rows',
        'binding_rows',
        'query_group_id',
        'report_group_id',
        'chart_group_id',
        'chart_config',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'selected_fields' => 'array',
        'join_rows' => 'array',
        'where_rows' => 'array',
        'group_by' => 'boolean',
        'group_rows' => 'array',
        'aggregate_rows' => 'array',
        'having_rows' => 'array',
        'binding_rows' => 'array',
        'all_fields' => 'boolean',
        'distinct_select' => 'boolean',
        'chart_config' => 'array',
        'report_template_size_kb' => 'integer',
        'query_group_id' => 'integer',
        'report_group_id' => 'integer',
        'chart_group_id' => 'integer',
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function queryGroup(): BelongsTo
    {
        return $this->belongsTo(QueryGroup::class, 'query_group_id');
    }

    public function reportGroup(): BelongsTo
    {
        return $this->belongsTo(ReportGroup::class, 'report_group_id');
    }

    public function chartGroup(): BelongsTo
    {
        return $this->belongsTo(ChartGroup::class, 'chart_group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
