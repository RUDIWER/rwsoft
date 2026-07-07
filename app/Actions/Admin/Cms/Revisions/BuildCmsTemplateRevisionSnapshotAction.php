<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsTemplate;

class BuildCmsTemplateRevisionSnapshotAction
{
    public function __construct(
        private readonly EnsureCmsRevisionKeysAction $ensureRevisionKeys,
        private readonly BuildCmsSectionRevisionSnapshotAction $buildSections,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsTemplate $template): array
    {
        $this->ensureRevisionKeys->handle($template, ['content']);
        $template->loadMissing('sections.placements.block');
        $template->refresh()->load('sections.placements.block');

        return [
            'schema_version' => 1,
            'subject' => [
                'type' => 'cms_template',
                'id' => $template->id,
            ],
            'template' => [
                'name' => $template->name,
                'locale' => $template->locale,
                'translation_key' => $template->translation_key,
                'translated_from_template_id' => $template->translated_from_template_id,
                'layout_id' => $template->layout_id,
                'template_class' => $template->template_class,
                'template_key' => $template->template_key,
                'is_default' => (bool) $template->is_default,
                'is_active' => (bool) $template->is_active,
                'cache_strategy' => $template->cache_strategy,
                'settings' => $template->settings ?? [],
                'data_contract' => $template->data_contract ?? [],
            ],
            'sections' => [
                'content' => $this->buildSections->handle($template->sections->where('zone', 'content')),
            ],
        ];
    }
}
