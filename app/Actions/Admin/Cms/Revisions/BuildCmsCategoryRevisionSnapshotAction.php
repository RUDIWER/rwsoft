<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsCategory;

class BuildCmsCategoryRevisionSnapshotAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(CmsCategory $category): array
    {
        $category->refresh()->load('landingPage');
        $page = $category->landingPage;

        return [
            'schema_version' => 1,
            'subject' => [
                'type' => 'cms_category',
                'id' => $category->id,
            ],
            'category' => [
                'parent_id' => $category->parent_id,
                'type' => $category->type,
                'title' => $category->title,
                'slug' => $category->slug,
                'locale' => $category->locale,
                'description' => $category->description,
                'sort_order' => (int) $category->sort_order,
                'is_active' => (bool) $category->is_active,
                'settings' => $category->settings ?? [],
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
