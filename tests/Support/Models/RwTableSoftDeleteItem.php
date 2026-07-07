<?php

namespace Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RwTableSoftDeleteItem extends Model
{
    use SoftDeletes;

    protected $table = 'rwtable_soft_delete_items';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    public static function rules(int|string $id): array
    {
        return [
            'name' => 'required|string|max:255',
            'active' => 'required|boolean',
        ];
    }
}
