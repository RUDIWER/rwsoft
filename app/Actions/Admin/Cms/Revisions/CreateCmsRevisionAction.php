<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsRevision;
use Illuminate\Database\Eloquent\Model;

class CreateCmsRevisionAction
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        Model $subject,
        string $scope,
        array $snapshot,
        ?int $authorId = null,
        ?string $title = null,
        ?int $restoredFromRevisionId = null,
        array $metadata = [],
        bool $forceCreate = false,
    ): ?CmsRevision {
        $subjectType = $subject::class;
        $subjectId = (int) $subject->getKey();
        $hash = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        $lastRevision = CmsRevision::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->latest('revision_number')
            ->lockForUpdate()
            ->first();

        if (! $forceCreate && $lastRevision instanceof CmsRevision && $lastRevision->snapshot_hash === $hash && $restoredFromRevisionId === null) {
            return null;
        }

        return CmsRevision::query()->create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'author_id' => $authorId,
            'revision_number' => $lastRevision instanceof CmsRevision ? ((int) $lastRevision->revision_number) + 1 : 1,
            'scope' => $scope,
            'title' => $title,
            'snapshot' => $snapshot,
            'snapshot_hash' => $hash,
            'restored_from_revision_id' => $restoredFromRevisionId,
            'metadata' => array_merge($this->summary($snapshot), $metadata),
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function summary(array $snapshot): array
    {
        $sections = collect($snapshot['sections'] ?? [])->flatten(1);

        return [
            'sections_count' => $sections->count(),
            'blocks_count' => $sections
                ->sum(fn (array $section): int => count($section['placements'] ?? [])),
        ];
    }
}
