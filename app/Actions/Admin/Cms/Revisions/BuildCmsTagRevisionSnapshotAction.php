<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsTag;

class BuildCmsTagRevisionSnapshotAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(CmsTag $tag): array
    {
        $tag->refresh()->load('landingPage');
        $page = $tag->landingPage;

        return [
            'schema_version' => 1,
            'subject' => [
                'type' => 'cms_tag',
                'id' => $tag->id,
            ],
            'tag' => [
                'title' => $tag->title,
                'slug' => $tag->slug,
                'locale' => $tag->locale,
                'description' => $tag->description,
                'is_active' => (bool) $tag->is_active,
                'settings' => $tag->settings ?? [],
                'landing_page' => $page ? [
                    'status' => $page->status,
                    'template' => $page->template,
                    'short_description' => $page->short_description,
                    'content_blocks' => $page->content_blocks ?? [],
                    'seo_title' => $page->seo_title,
                    'seo_description' => $page->seo_description,
                    'canonical_url' => $page->canonical_url,
                    'og_image_path' => $page->og_image_path,
                    'noindex' => (bool) $page->noindex,
                    'is_searchable' => (bool) $page->is_searchable,
                    'published_at' => $page->published_at?->toDateTimeString(),
                    'settings' => $page->settings ?? [],
                ] : [],
            ],
        ];
    }
}
