<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatabaseColumnMetadata extends Model
{
    protected $table = 'rw_db_column_metadata';

    protected $fillable = [
        'table_name',
        'column_name',
        'render_as_file_upload',
        'upload_config',
    ];

    protected function casts(): array
    {
        return [
            'render_as_file_upload' => 'boolean',
            'upload_config' => 'array',
        ];
    }
}
