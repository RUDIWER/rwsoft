<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsMailTemplate;

class BuildCmsMailTemplateRevisionSnapshotAction
{
    public function __construct(
        private readonly EnsureCmsRevisionKeysAction $ensureRevisionKeys,
        private readonly BuildCmsSectionRevisionSnapshotAction $buildSections,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsMailTemplate $template): array
    {
        $this->ensureRevisionKeys->handle($template, ['content']);
        $template->loadMissing('sections.placements.block');
        $template->refresh()->load('sections.placements.block');

        return [
            'schema_version' => 1,
            'subject' => [
                'type' => 'cms_mail_template',
                'id' => $template->id,
            ],
            'mail_template' => [
                'name' => $template->name,
                'key' => $template->key,
                'description' => $template->description,
                'context_key' => $template->context_key,
                'body_blocks' => $template->body_blocks ?? [],
                'settings' => $template->settings ?? [],
                'is_active' => (bool) $template->is_active,
            ],
            'sections' => [
                'content' => $this->buildSections->handle($template->sections->where('zone', 'content')),
            ],
        ];
    }
}
