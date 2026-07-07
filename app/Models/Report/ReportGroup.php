<?php

namespace App\Models\Report;

use App\Models\Query\Query;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function queries(): HasMany
    {
        return $this->hasMany(Query::class, 'report_group_id');
    }
}
