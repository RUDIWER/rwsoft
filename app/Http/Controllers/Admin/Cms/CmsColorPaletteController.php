<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StoreCmsColorPaletteItemRequest;
use App\Models\Cms\CmsColorPaletteItem;
use App\Support\Cms\CmsResponsiveLayoutNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsColorPaletteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'items' => CmsColorPaletteItem::activePayload(),
        ]);
    }

    public function store(StoreCmsColorPaletteItemRequest $request, CmsResponsiveLayoutNormalizer $normalizer): JsonResponse
    {
        $validated = $request->validated();
        $hexColor = $normalizer->normalizeHexColor($validated['hex_color'] ?? null);

        abort_if($hexColor === null, 422);

        $item = CmsColorPaletteItem::query()->updateOrCreate(
            ['hex_color' => $hexColor],
            [
                'name' => $validated['name'],
                'sort_order' => (int) ($validated['sort_order'] ?? CmsColorPaletteItem::query()->max('sort_order') + 1),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'updated_by' => $request->user()?->id,
            ],
        );

        if ($item->wasRecentlyCreated) {
            $item->forceFill(['created_by' => $request->user()?->id])->save();
        }

        return response()->json([
            'item' => self::itemPayload($item),
            'items' => CmsColorPaletteItem::activePayload(),
        ]);
    }

    public function destroy(Request $request, int $item): JsonResponse
    {
        $paletteItem = CmsColorPaletteItem::query()->findOrFail($item);

        $paletteItem->forceFill([
            'is_active' => false,
            'updated_by' => $request->user()?->id,
        ])->save();

        return response()->json([
            'items' => CmsColorPaletteItem::activePayload(),
        ]);
    }

    /**
     * @return array{id: int, name: string, hex_color: string, sort_order: int}
     */
    private static function itemPayload(CmsColorPaletteItem $item): array
    {
        return [
            'id' => (int) $item->id,
            'name' => (string) $item->name,
            'hex_color' => (string) $item->hex_color,
            'sort_order' => (int) $item->sort_order,
        ];
    }
}
