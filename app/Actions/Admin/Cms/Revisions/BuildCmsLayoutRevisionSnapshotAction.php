<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsLayout;

class BuildCmsLayoutRevisionSnapshotAction
{
    public function __construct(
        private readonly EnsureCmsRevisionKeysAction $ensureRevisionKeys,
        private readonly BuildCmsSectionRevisionSnapshotAction $buildSections,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsLayout $layout): array
    {
        $zones = ['head', 'header', 'footer', 'body_end'];

        $this->ensureRevisionKeys->handle($layout, $zones);
        $layout->loadMissing('sections.placements.block');
        $layout->refresh()->load('sections.placements.block');

        return [
            'schema_version' => 1,
            'subject' => [
                'type' => 'cms_layout',
                'id' => $layout->id,
            ],
            'layout' => [
                'name' => $layout->name,
                'locale' => $layout->locale,
                'is_default' => (bool) $layout->is_default,
                'is_active' => (bool) $layout->is_active,
                'cache_strategy' => $layout->cache_strategy,
                'settings' => $layout->settings ?? [],
            ],
            'sections' => collect($zones)
                ->mapWithKeys(fn (string $zone): array => [
                    $zone => $this->buildSections->handle($layout->sections->where('zone', $zone)),
                ])
                ->all(),
        ];
    }
}
