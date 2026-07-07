<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsPage;

class BuildCmsPageRevisionSnapshotAction
{
    public function __construct(
        private readonly EnsureCmsRevisionKeysAction $ensureRevisionKeys,
        private readonly BuildCmsSectionRevisionSnapshotAction $buildSections,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsPage $page): array
    {
        $this->ensureRevisionKeys->handle($page, ['content']);
        $page->loadMissing('sections.placements.block');
        $page->refresh()->load('sections.placements.block');

        return [
            'schema_version' => 1,
            'subject' => [
                'type' => 'cms_page',
                'id' => $page->id,
            ],
            'page' => [
                'title' => $page->title,
                'locale' => $page->locale,
                'short_description' => $page->short_description,
                'detail_template_id' => $page->detail_template_id,
                'template_data' => $page->template_data ?? [],
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'canonical_url' => $page->canonical_url,
                'og_image_path' => $page->og_image_path,
                'noindex' => (bool) $page->noindex,
                'is_searchable' => (bool) $page->is_searchable,
                'settings' => $page->settings ?? [],
            ],
            'sections' => [
                'content' => $this->buildSections->handle($page->sections->where('zone', 'content')),
            ],
        ];
    }
}
