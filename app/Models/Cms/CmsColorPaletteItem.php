<?php

namespace App\Models\Cms;

use Illuminate\Database\Eloquent\Model;

class CmsColorPaletteItem extends Model
{
    protected $fillable = [
        'name',
        'hex_color',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, hex_color: string, sort_order: int}>
     */
    public static function activePayload(): array
    {
        $model = new self;

        if (! $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable())) {
            return [];
        }

        return self::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (self $item): array => [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'hex_color' => (string) $item->hex_color,
                'sort_order' => (int) $item->sort_order,
            ])
            ->values()
            ->all();
    }
}
