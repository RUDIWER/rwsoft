<?php

namespace App\Support\Cms\Search;

use App\Models\Cms\CmsSearchChunk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CmsSearchService
{
    /**
     * @param  array<int, string>  $sourceTypes
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, string $locale, array $sourceTypes = [], int $limit = 20): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $limit = min(max($limit, 1), (int) config('cms_search.max_limit', 50));
        $supportsFullText = $this->supportsFullText();
        $chunks = $this->chunkQuery($query, $locale, $sourceTypes, $limit, $supportsFullText)->get();

        if ($supportsFullText && $chunks->isEmpty()) {
            $chunks = $this->chunkQuery($query, $locale, $sourceTypes, $limit, false)->get();
        }

        return $chunks
            ->filter(fn (CmsSearchChunk $chunk): bool => $chunk->document !== null)
            ->groupBy('cms_search_document_id')
            ->map(function ($documentChunks): array {
                /** @var CmsSearchChunk $chunk */
                $chunk = $documentChunks->first();
                $document = $chunk->document;

                return [
                    'title' => $document?->title,
                    'summary' => $document?->summary,
                    'source_type' => $document?->source_type,
                    'canonical_url' => $document?->canonical_url,
                    'markdown_url' => $document?->markdown_url,
                    'snippet' => $this->snippet((string) $chunk->content_text),
                    'heading' => $chunk->heading,
                ];
            })
            ->values()
            ->take($limit)
            ->all();
    }

    /**
     * @param  array<int, string>  $sourceTypes
     * @return Builder<CmsSearchChunk>
     */
    private function chunkQuery(string $query, string $locale, array $sourceTypes, int $limit, bool $useFullText): Builder
    {
        return CmsSearchChunk::query()
            ->with('document')
            ->whereHas('document', function (Builder $documentQuery) use ($locale, $sourceTypes): void {
                $documentQuery
                    ->where('locale', $locale)
                    ->where('is_active', true)
                    ->where('is_searchable', true)
                    ->where('noindex', false);

                if ($sourceTypes !== []) {
                    $documentQuery->whereIn('source_type', $sourceTypes);
                }
            })
            ->when($useFullText, fn (Builder $builder) => $builder->whereFullText(['heading', 'content_text'], $query))
            ->when(! $useFullText, function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $likeQuery) use ($query): void {
                    $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';
                    $likeQuery->where('heading', 'like', $like)->orWhere('content_text', 'like', $like);
                });
            })
            ->limit($limit * 3);
    }

    private function supportsFullText(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function snippet(string $text): string
    {
        return mb_strlen($text) > 220 ? mb_substr($text, 0, 217).'...' : $text;
    }
}
