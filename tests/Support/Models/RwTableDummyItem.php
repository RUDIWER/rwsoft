<?php

namespace Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;

class RwTableDummyItem extends Model
{
    protected $table = 'rwtable_dummy_items';

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
            'index' => 'nullable|integer',
            'group_id' => 'nullable|integer',
            'created_on' => 'nullable|date',
            'flagged' => 'nullable|boolean',
        ];
    }
}
