<?php

namespace App\Support\Ai;

use App\Models\Cms\CmsTemplate;
use Illuminate\Support\Arr;
use RuntimeException;

use function Laravel\Ai\agent;

class CmsTemplateTranslationAiService
{
    public function __construct(private readonly AiProviderSettings $providerSettings) {}

    /**
     * @return array{name?: string, sections?: array<int, array<string, mixed>>}
     */
    public function translate(CmsTemplate $sourceTemplate, string $targetLocale): array
    {
        $settings = $this->providerSettings->translationSettings();
        $provider = (string) ($settings['provider'] ?? 'gemini');
        $model = (string) ($settings['model'] ?? '');
        $apiKey = trim((string) ($settings['api_key'] ?? ''));
        $providerKeyPath = 'ai.providers.'.$provider.'.key';
        $originalProviderKey = config($providerKeyPath);

        if ($apiKey === '' && trim((string) $originalProviderKey) === '') {
            throw new RuntimeException(__('cms_admin_ui.flash.ai_provider_api_key_missing', [
                'provider' => $provider,
            ]));
        }

        if ($apiKey !== '') {
            config([$providerKeyPath => $apiKey]);
        }

        try {
            $response = agent(
                instructions: implode(' ', [
                    'You translate CMS template content for a public website.',
                    'Only translate human-readable template labels, section names, and block text fields.',
                    'Preserve HTML tags, placeholders, dynamic field keys, content slot keys, URLs, form keys, category IDs, tag IDs, media IDs, technical identifiers, and all code exactly.',
                    'Never add explanations. Return translated content only through the structured schema.',
                ]),
                schema: fn ($schema): array => [
                    'name' => $schema->string()->required(),
                    'sections' => $schema
                        ->array()
                        ->items($schema->object([
                            'index' => $schema->integer()->required(),
                            'name' => $schema->string(),
                            'placements' => $schema
                                ->array()
                                ->items($schema->object([
                                    'index' => $schema->integer()->required(),
                                    'title' => $schema->string(),
                                    'text' => $schema->string(),
                                    'source' => $schema->string(),
                                    'caption' => $schema->string(),
                                    'label' => $schema->string(),
                                    'empty_text' => $schema->string(),
                                ])),
                        ]))
                        ->required(),
                ],
            )->prompt(
                $this->buildPrompt($sourceTemplate, $targetLocale),
                provider: $provider,
                model: $model !== '' ? $model : null,
                timeout: 120,
            );
        } finally {
            if ($apiKey !== '') {
                config([$providerKeyPath => $originalProviderKey]);
            }
        }

        return $this->translatedPayload($response->toArray(), $sourceTemplate);
    }

    private function buildPrompt(CmsTemplate $sourceTemplate, string $targetLocale): string
    {
        return implode("\n\n", [
            'Translate this CMS template from '.$sourceTemplate->locale.' to '.$targetLocale.'.',
            'Rules:',
            '- Keep meaning, tone, and call-to-action intent.',
            '- Keep all placeholders, URLs, form keys, field keys, slot keys, IDs, and system identifiers unchanged.',
            '- Do not translate dynamic_field.field_key or content_slot.slot_key values.',
            '- Only translate human-readable text fields.',
            'Template JSON:',
            json_encode($this->sourcePayload($sourceTemplate), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sourcePayload(CmsTemplate $sourceTemplate): array
    {
        $sourceTemplate->loadMissing('sections.placements.block');

        return [
            'name' => (string) $sourceTemplate->name,
            'sections' => $sourceTemplate->sections
                ->where('zone', 'content')
                ->where('is_active', true)
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($section, int $sectionIndex): array => [
                    'index' => $sectionIndex,
                    'name' => (string) ($section->name ?? ''),
                    'placements' => $section->placements
                        ->where('is_active', true)
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn ($placement, int $placementIndex): array => $this->placementPayload($placement, $placementIndex))
                        ->all(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function placementPayload($placement, int $placementIndex): array
    {
        $block = $placement->block;

        return array_filter(array_merge([
            'index' => $placementIndex,
            'type' => (string) $block->type,
        ], Arr::only($block->content ?? [], [
            'title',
            'text',
            'source',
            'caption',
            'label',
            'empty_text',
        ])), fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array{name?: string, sections?: array<int, array<string, mixed>>}
     */
    private function translatedPayload(array $response, CmsTemplate $sourceTemplate): array
    {
        return [
            'name' => mb_substr(trim((string) Arr::get($response, 'name', $sourceTemplate->name)), 0, 255),
            'sections' => Arr::get($response, 'sections', []),
        ];
    }
}
