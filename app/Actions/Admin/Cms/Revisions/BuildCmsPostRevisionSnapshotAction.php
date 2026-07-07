<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsPost;

class BuildCmsPostRevisionSnapshotAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(CmsPost $post): array
    {
        $post->loadMissing(['categories:id', 'tags:id']);

        return [
            'schema_version' => 1,
            'subject' => [
                'type' => 'cms_post',
                'id' => $post->id,
            ],
            'post' => [
                'title' => $post->title,
                'locale' => $post->locale,
                'status' => $post->status,
                'excerpt' => $post->excerpt,
                'content_blocks' => $post->content_blocks ?? [],
                'featured_media_asset_id' => $post->featured_media_asset_id,
                'seo_title' => $post->seo_title,
                'seo_description' => $post->seo_description,
                'canonical_url' => $post->canonical_url,
                'og_image_path' => $post->og_image_path,
                'noindex' => (bool) $post->noindex,
                'is_featured' => (bool) $post->is_featured,
                'is_searchable' => (bool) $post->is_searchable,
                'published_at' => $post->published_at?->toDateTimeString(),
                'settings' => $post->settings ?? [],
                'category_ids' => $post->categories->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
                'tag_ids' => $post->tags->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
            ],
        ];
    }
}
