<?php

namespace App\Actions\Admin\Cms\Search;

use App\Models\Cms\CmsSearchDocument;
use App\Support\Cms\Search\CmsSearchChunker;
use App\Support\Cms\Search\CmsSearchDocumentData;
use App\Support\Cms\Search\CmsSearchDocumentProviderRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReindexCmsSearchDocumentsAction
{
    public function __construct(
        private readonly CmsSearchDocumentProviderRegistry $providers,
        private readonly CmsSearchChunker $chunker,
    ) {}

    /**
     * @return array{documents: int, chunks: int, deleted: int}
     */
    public function handle(?string $locale = null, ?string $sourceType = null, bool $force = false): array
    {
        if (! Schema::hasTable('cms_search_documents') || ! Schema::hasTable('cms_search_chunks')) {
            return ['documents' => 0, 'chunks' => 0, 'deleted' => 0];
        }

        $seen = [];
        $documentCount = 0;
        $chunkCount = 0;

        $deleted = 0;

        DB::transaction(function () use ($locale, $sourceType, $force, &$seen, &$documentCount, &$chunkCount, &$deleted): void {
            foreach ($this->providers->providers($sourceType) as $provider) {
                foreach ($provider->documents($locale) as $data) {
                    if ($sourceType !== null && $sourceType !== '' && $data->sourceType !== $sourceType) {
                        continue;
                    }

                    $seen[] = [$data->sourceType, $data->sourceKey, $data->locale];
                    $document = $this->upsertDocument($data);
                    $documentCount++;

                    if ($force || $document->wasRecentlyCreated || $document->wasChanged('markdown_hash')) {
                        $document->chunks()->delete();
                        $chunks = $this->chunker->chunk($data->markdown);
                        $document->chunks()->createMany($chunks);
                        $chunkCount += count($chunks);
                    }
                }
            }

            $deleted = $this->deleteStale($seen, $locale, $sourceType);
        });

        return ['documents' => $documentCount, 'chunks' => $chunkCount, 'deleted' => $deleted];
    }

    private function upsertDocument(CmsSearchDocumentData $data): CmsSearchDocument
    {
        $document = CmsSearchDocument::query()->firstOrNew([
            'source_type' => $data->sourceType,
            'source_key' => $data->sourceKey,
            'locale' => $data->locale,
        ]);
        $markdownHash = hash('sha256', $data->markdown);

        $document->fill([
            'source_id' => $data->sourceId,
            'title' => $data->title,
            'slug' => $data->slug,
            'summary' => $data->summary,
            'canonical_path' => $data->canonicalPath,
            'canonical_url' => $data->canonicalUrl,
            'markdown_path' => $data->markdownPath,
            'markdown_url' => $data->markdownUrl,
            'source_updated_at' => $data->sourceUpdatedAt,
            'published_at' => $data->publishedAt,
            'is_active' => $data->isActive,
            'is_searchable' => $data->isSearchable,
            'noindex' => $data->noindex,
            'markdown_hash' => $markdownHash,
            'plain_text_hash' => hash('sha256', $data->plainText),
            'markdown' => $data->markdown,
            'plain_text' => $data->plainText,
            'metadata' => $data->metadata,
            'indexed_at' => now(),
        ]);
        $document->save();

        return $document;
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $seen
     */
    private function deleteStale(array $seen, ?string $locale, ?string $sourceType): int
    {
        $query = CmsSearchDocument::query();

        if (is_string($locale) && $locale !== '') {
            $query->where('locale', $locale);
        }

        if (is_string($sourceType) && $sourceType !== '') {
            $query->where('source_type', $sourceType);
        }

        foreach ($seen as [$seenType, $seenKey, $seenLocale]) {
            $query->whereNot(function ($staleQuery) use ($seenType, $seenKey, $seenLocale): void {
                $staleQuery
                    ->where('source_type', $seenType)
                    ->where('source_key', $seenKey)
                    ->where('locale', $seenLocale);
            });
        }

        $count = (clone $query)->count();
        $query->delete();

        return $count;
    }
}
