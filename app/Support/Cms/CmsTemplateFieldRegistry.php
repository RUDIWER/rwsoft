<?php

namespace App\Support\Cms;

class CmsTemplateFieldRegistry
{
    /**
     * @return array<int, array{key: string, label_key: string, group_key: string, type: string}>
     */
    public function fieldsFor(string $templateKey): array
    {
        return $this->definitions()[$templateKey] ?? [];
    }

    /**
     * @return array<string, array<int, array{key: string, label_key: string, group_key: string, type: string}>>
     */
    public function all(): array
    {
        return $this->definitions();
    }

    /**
     * @return array<string, array<int, array{key: string, label_key: string, group_key: string, type: string}>>
     */
    private function definitions(): array
    {
        return [
            'page.detail' => [
                $this->field('page.title', 'templates.fields.page_title', 'templates.field_groups.page', 'text'),
                $this->field('page.short_description', 'templates.fields.page_short_description', 'templates.field_groups.page', 'text'),
                $this->field('page.slug', 'templates.fields.page_slug', 'templates.field_groups.page', 'text'),
                $this->field('page.locale', 'templates.fields.page_locale', 'templates.field_groups.page', 'text'),
                $this->field('page.url', 'templates.fields.page_url', 'templates.field_groups.page', 'url'),
                $this->field('page.seo_title', 'templates.fields.page_seo_title', 'templates.field_groups.seo', 'text'),
                $this->field('page.seo_description', 'templates.fields.page_seo_description', 'templates.field_groups.seo', 'text'),
                $this->field('page.published_at', 'templates.fields.page_published_at', 'templates.field_groups.publication', 'date'),
                $this->field('page.updated_at', 'templates.fields.page_updated_at', 'templates.field_groups.publication', 'date'),
                $this->field('page.breadcrumbs', 'templates.fields.page_breadcrumbs', 'templates.field_groups.navigation', 'list'),
            ],
            'blog.index' => [
                $this->field('blog_index.title', 'templates.fields.blog_index_title', 'templates.field_groups.blog', 'text'),
                $this->field('blog_index.lead', 'templates.fields.blog_index_lead', 'templates.field_groups.blog', 'text'),
                $this->field('blogs', 'templates.fields.blogs', 'templates.field_groups.blog', 'list'),
                $this->field('categories', 'templates.fields.categories', 'templates.field_groups.taxonomy', 'list'),
                $this->field('tags', 'templates.fields.tags', 'templates.field_groups.taxonomy', 'list'),
            ],
            'blog.detail' => [
                $this->field('blog.title', 'templates.fields.blog_title', 'templates.field_groups.blog', 'text'),
                $this->field('blog.excerpt', 'templates.fields.blog_excerpt', 'templates.field_groups.blog', 'text'),
                $this->field('blog.published_at', 'templates.fields.blog_published_at', 'templates.field_groups.blog', 'date'),
                $this->field('blog.author.name', 'templates.fields.blog_author_name', 'templates.field_groups.blog', 'text'),
                $this->field('blog.featured_media', 'templates.fields.blog_featured_media', 'templates.field_groups.media', 'media'),
                $this->field('blog.categories', 'templates.fields.blog_categories', 'templates.field_groups.taxonomy', 'list'),
                $this->field('blog.tags', 'templates.fields.blog_tags', 'templates.field_groups.taxonomy', 'list'),
                $this->field('blog.content', 'templates.fields.blog_content', 'templates.field_groups.content', 'content_slot'),
            ],
            'category.index' => [
                $this->field('category_index.title', 'templates.fields.category_index_title', 'templates.field_groups.taxonomy', 'text'),
                $this->field('categories', 'templates.fields.categories', 'templates.field_groups.taxonomy', 'list'),
                $this->field('root_categories', 'templates.fields.root_categories', 'templates.field_groups.taxonomy', 'list'),
                $this->field('category_count', 'templates.fields.category_count', 'templates.field_groups.taxonomy', 'number'),
            ],
            'category.archive' => [
                $this->field('category.title', 'templates.fields.category_title', 'templates.field_groups.category', 'text'),
                $this->field('category.description', 'templates.fields.category_description', 'templates.field_groups.category', 'text'),
                $this->field('category.parent', 'templates.fields.category_parent', 'templates.field_groups.category', 'object'),
                $this->field('category.children', 'templates.fields.category_children', 'templates.field_groups.taxonomy', 'list'),
                $this->field('category.blogs', 'templates.fields.category_blogs', 'templates.field_groups.blog', 'list'),
                $this->field('category.content', 'templates.fields.category_content', 'templates.field_groups.content', 'content_slot'),
            ],
            'category.detail' => [
                $this->field('category.title', 'templates.fields.category_title', 'templates.field_groups.category', 'text'),
                $this->field('category.description', 'templates.fields.category_description', 'templates.field_groups.category', 'text'),
                $this->field('category.content', 'templates.fields.category_detail_content', 'templates.field_groups.content', 'content_slot'),
                $this->field('category.forms', 'templates.fields.category_forms', 'templates.field_groups.forms', 'list'),
            ],
            'tag.index' => [
                $this->field('tag_index.title', 'templates.fields.tag_index_title', 'templates.field_groups.taxonomy', 'text'),
                $this->field('tags', 'templates.fields.tags', 'templates.field_groups.taxonomy', 'list'),
                $this->field('tag_count', 'templates.fields.tag_count', 'templates.field_groups.taxonomy', 'number'),
            ],
            'tag.archive' => [
                $this->field('tag.title', 'templates.fields.tag_title', 'templates.field_groups.tag', 'text'),
                $this->field('tag.description', 'templates.fields.tag_description', 'templates.field_groups.tag', 'text'),
                $this->field('tag.blogs', 'templates.fields.tag_blogs', 'templates.field_groups.blog', 'list'),
                $this->field('tag.content', 'templates.fields.tag_content', 'templates.field_groups.content', 'content_slot'),
            ],
            'tag.detail' => [
                $this->field('tag.title', 'templates.fields.tag_title', 'templates.field_groups.tag', 'text'),
                $this->field('tag.description', 'templates.fields.tag_description', 'templates.field_groups.tag', 'text'),
                $this->field('tag.content', 'templates.fields.tag_detail_content', 'templates.field_groups.content', 'content_slot'),
                $this->field('tag.forms', 'templates.fields.tag_forms', 'templates.field_groups.forms', 'list'),
            ],
            'search.index' => [
                $this->field('search.title', 'templates.fields.search_title', 'templates.field_groups.search', 'text'),
                $this->field('search.query', 'templates.fields.search_query', 'templates.field_groups.search', 'text'),
                $this->field('search.results', 'templates.fields.search_results', 'templates.field_groups.search', 'list'),
                $this->field('search.result_count', 'templates.fields.search_result_count', 'templates.field_groups.search', 'number'),
            ],
            ...$this->errorDefinitions(),
        ];
    }

    /**
     * @return array<string, array<int, array{key: string, label_key: string, group_key: string, type: string}>>
     */
    private function errorDefinitions(): array
    {
        $fields = [
            $this->field('error.status_code', 'templates.fields.error_status_code', 'templates.field_groups.error', 'number'),
            $this->field('error.title', 'templates.fields.error_title', 'templates.field_groups.error', 'text'),
            $this->field('error.message', 'templates.fields.error_message', 'templates.field_groups.error', 'textarea'),
            $this->field('error.request_path', 'templates.fields.error_request_path', 'templates.field_groups.error', 'text'),
            $this->field('error.home_url', 'templates.fields.error_home_url', 'templates.field_groups.error', 'url'),
        ];

        return collect(['default', '403', '404', '419', '500', '503'])
            ->mapWithKeys(fn (string $status): array => ["error.{$status}" => $fields])
            ->all();
    }

    /**
     * @return array{key: string, label_key: string, group_key: string, type: string}
     */
    private function field(string $key, string $labelKey, string $groupKey, string $type): array
    {
        return [
            'key' => $key,
            'label_key' => $labelKey,
            'group_key' => $groupKey,
            'type' => $type,
        ];
    }
}
