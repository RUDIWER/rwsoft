<?php

namespace Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;

class RwTableTimestampedNoColumnsItem extends Model
{
    protected $table = 'rwtable_timestamped_no_columns_items';

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
