<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsLayout;
use App\Support\Cms\CmsBlockRegistry;

class SaveCmsLayoutSectionsAction
{
    public function __construct(
        private readonly SaveCmsSectionsAction $saveSections,
        private readonly CmsBlockRegistry $blockRegistry,
    ) {}

    /**
     * @param  array{head?: array<int, array<string, mixed>>, header?: array<int, array<string, mixed>>, footer?: array<int, array<string, mixed>>, body_end?: array<int, array<string, mixed>>}  $sections
     */
    public function handle(CmsLayout $layout, array $sections): void
    {
        $this->saveSections->handle($layout, $sections, $this->blockRegistry->layoutZones());
    }
}
