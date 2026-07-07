<?php

namespace App\Models\Query;

use Illuminate\Database\Eloquent\Model;

class QueryBuilderSelectTable extends Model
{
    protected $fillable = [
        'name',
        'table_name',
        'select_field',
        'label_fields',
        'search_fields',
        'default_filters',
        'default_sort',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'label_fields' => 'array',
        'search_fields' => 'array',
        'default_filters' => 'array',
        'default_sort' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
