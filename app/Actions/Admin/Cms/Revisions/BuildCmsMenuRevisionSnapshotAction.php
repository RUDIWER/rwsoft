<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsMenuTranslation;

class BuildCmsMenuRevisionSnapshotAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(CmsMenu $menu): array
    {
        $menu->loadMissing(['items', 'translations']);

        return [
            'schema_version' => 1,
            'subject' => [
                'type' => 'cms_menu',
                'id' => $menu->id,
            ],
            'menu' => [
                'title' => $menu->title,
                'placements' => array_values((array) ($menu->placements ?? [])),
                'is_active' => (bool) $menu->is_active,
                'settings' => $menu->settings ?? [],
                'translations' => $menu->translations
                    ->sortBy('locale')
                    ->map(fn (CmsMenuTranslation $translation): array => [
                        'locale' => $translation->locale,
                        'title' => $translation->title,
                    ])
                    ->values()
                    ->all(),
                'items' => $menu->items
                    ->sortBy('sort_order')
                    ->map(fn (CmsMenuItem $item): array => $this->itemPayload($item))
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(CmsMenuItem $item): array
    {
        return [
            'id' => $item->id,
            'locale' => $item->locale,
            'translation_key' => $item->translation_key,
            'translated_from_menu_item_id' => $item->translated_from_menu_item_id,
            'parent_id' => $item->parent_id,
            'cms_page_id' => $item->cms_page_id,
            'cms_post_id' => $item->cms_post_id,
            'type' => $item->type,
            'label' => $item->label,
            'url' => $item->url,
            'target' => $item->target,
            'rel' => $item->rel,
            'sort_order' => (int) $item->sort_order,
            'is_active' => (bool) $item->is_active,
            'metadata' => $item->metadata ?? [],
        ];
    }
}
