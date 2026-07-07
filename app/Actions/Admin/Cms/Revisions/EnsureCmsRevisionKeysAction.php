<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EnsureCmsRevisionKeysAction
{
    /**
     * @param  array<int, string>  $zones
     */
    public function handle(Model $owner, array $zones): void
    {
        $owner->sections()
            ->whereIn('zone', $zones)
            ->with('placements.block')
            ->get()
            ->each(fn (CmsSection $section): bool => $this->ensureSection($section));
    }

    public function ensureSection(CmsSection $section): bool
    {
        if (blank($section->revision_key)) {
            $section->forceFill(['revision_key' => (string) Str::ulid()])->save();
        }

        $section->placements->each(function (CmsBlockPlacement $placement): void {
            if (blank($placement->revision_key)) {
                $placement->forceFill(['revision_key' => (string) Str::ulid()])->save();
            }

            if ($placement->block instanceof CmsBlock && blank($placement->block->revision_key)) {
                $placement->block->forceFill(['revision_key' => (string) Str::ulid()])->save();
            }
        });

        return true;
    }
}
