<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsTag;
use App\Support\PublicSite\CmsPublicUrlBuilder;
use Illuminate\Database\Eloquent\Model;

class BuildCmsContentStatisticsUrlsAction
{
    public function __construct(private readonly CmsPublicUrlBuilder $urlBuilder) {}

    /**
     * @return array{paths: array<int, string>, urls: array<int, string>, labels: array<int, array{path: string, url: string, label: string}>, warnings: array<int, string>}
     */
    public function handle(string $contentType, int $recordId): array
    {
        $record = $this->record($contentType, $recordId);
        $paths = $this->paths($contentType, $record);

        $urls = collect($paths)
            ->map(fn (string $path): string => url($path))
            ->values()
            ->all();

        return [
            'paths' => $paths,
            'urls' => $urls,
            'labels' => collect($paths)
                ->map(fn (string $path, int $index): array => [
                    'path' => $path,
                    'url' => $urls[$index],
                    'label' => $this->label($contentType, $record, $path),
                ])
                ->values()
                ->all(),
            'warnings' => [],
        ];
    }

    private function record(string $contentType, int $recordId): Model
    {
        return match ($contentType) {
            'page' => CmsPage::query()->findOrFail($recordId),
            'post' => CmsPost::query()->findOrFail($recordId),
            'category' => CmsCategory::query()->findOrFail($recordId),
            'tag' => CmsTag::query()->findOrFail($recordId),
            default => abort(404),
        };
    }

    /**
     * @return array<int, string>
     */
    private function paths(string $contentType, Model $record): array
    {
        return match ($contentType) {
            'page' => [$this->urlBuilder->pagePath(
                $record,
                CmsPage::query()->get(['id', 'parent_id', 'slug', 'locale', 'is_home'])->keyBy('id'),
            )],
            'post' => [$this->urlBuilder->postPath($record)],
            'category' => $this->categoryPaths($record),
            'tag' => $this->tagPaths($record),
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function categoryPaths(CmsCategory $category): array
    {
        $categories = CmsCategory::query()
            ->get(['id', 'parent_id', 'slug', 'locale'])
            ->keyBy('id');

        return [
            $this->urlBuilder->categoryPath($category, $categories),
            $this->urlBuilder->categoryDetailPath($category, $categories),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function tagPaths(CmsTag $tag): array
    {
        return [
            $this->urlBuilder->tagPath($tag),
            $this->urlBuilder->tagDetailPath($tag),
        ];
    }

    private function label(string $contentType, Model $record, string $path): string
    {
        $title = (string) ($record->title ?? $path);

        return match ($contentType) {
            'category' => str_ends_with($path, '/info')
                ? $title.' '.__('cms_admin_ui.statistics.url_label_suffix.info')
                : $title.' '.__('cms_admin_ui.statistics.url_label_suffix.archive'),
            'tag' => str_ends_with($path, '/info')
                ? $title.' '.__('cms_admin_ui.statistics.url_label_suffix.info')
                : $title.' '.__('cms_admin_ui.statistics.url_label_suffix.archive'),
            default => $title,
        };
    }
}
