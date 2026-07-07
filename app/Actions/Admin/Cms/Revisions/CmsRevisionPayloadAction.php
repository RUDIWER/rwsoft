<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsRevision;
use Illuminate\Database\Eloquent\Model;

class CmsRevisionPayloadAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Model $subject, int $limit = 30): array
    {
        return CmsRevision::query()
            ->with('author:id,name')
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->latest('revision_number')
            ->limit($limit)
            ->get()
            ->map(fn (CmsRevision $revision): array => $this->revisionPayload($revision))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function revisionPayload(CmsRevision $revision): array
    {
        return [
            'id' => $revision->id,
            'revision_number' => (int) $revision->revision_number,
            'scope' => $revision->scope,
            'title' => $revision->title,
            'author_name' => $revision->author?->name,
            'created_at' => $revision->created_at?->toDateTimeString(),
            'is_pinned' => (bool) $revision->is_pinned,
            'restored_from_revision_id' => $revision->restored_from_revision_id,
            'metadata' => $revision->metadata ?? [],
        ];
    }
}
