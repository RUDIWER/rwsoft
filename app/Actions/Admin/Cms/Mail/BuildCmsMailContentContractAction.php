<?php

namespace App\Actions\Admin\Cms\Mail;

use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsMailTemplate;
use App\Models\Cms\CmsSection;

class BuildCmsMailContentContractAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(CmsMailTemplate $template): array
    {
        $template->loadMissing('sections.placements.block');

        if ($template->sections->isNotEmpty()) {
            return $template->sections
                ->where('zone', 'content')
                ->where('is_active', true)
                ->flatMap(fn (CmsSection $section) => $section->placements->where('is_active', true)->sortBy('sort_order'))
                ->map(fn (CmsBlockPlacement $placement): ?array => $this->placementContractRow($placement))
                ->filter()
                ->values()
                ->all();
        }

        return collect($template->body_blocks ?? [])
            ->filter(fn (mixed $block): bool => is_array($block))
            ->map(fn (array $block): array => [
                'key' => (string) ($block['key'] ?? ''),
                'label' => (string) ($block['label'] ?? $block['key'] ?? ''),
                'type' => (string) ($block['type'] ?? 'text'),
                'fields' => $this->fieldsForType((string) ($block['type'] ?? 'text')),
            ])
            ->values()
            ->all();
    }

    private function placementContractRow(CmsBlockPlacement $placement): ?array
    {
        $rendererKey = (string) ($placement->block?->type ?? '');

        if (! str_starts_with($rendererKey, 'mail_')) {
            return null;
        }

        $key = (string) ($placement->settings['content_key'] ?? '');

        if ($key === '') {
            return null;
        }

        return [
            'key' => $key,
            'label' => (string) ($placement->settings['editor_label'] ?? $placement->block?->name ?? $key),
            'type' => $rendererKey,
            'fields' => $this->fieldsForType($rendererKey),
        ];
    }

    /**
     * @return array<int, array{name: string, type: string, required: bool}>
     */
    private function fieldsForType(string $type): array
    {
        return match ($type) {
            'mail_button', 'button' => [
                ['name' => 'label', 'type' => 'text', 'required' => true],
                ['name' => 'url', 'type' => 'text', 'required' => false],
            ],
            'mail_image' => [
                ['name' => 'media_asset_id', 'type' => 'media', 'required' => false],
                ['name' => 'alt', 'type' => 'text', 'required' => false],
                ['name' => 'caption', 'type' => 'text', 'required' => false],
            ],
            'mail_company_logo', 'mail_divider', 'mail_spacer', 'mail_form_answers', 'company_logo', 'divider', 'spacer', 'form_answers' => [],
            default => [
                ['name' => 'text', 'type' => 'textarea', 'required' => true],
            ],
        };
    }
}
