<?php

namespace App\Support\Ai;

use App\Models\Cms\CmsLayout;
use Illuminate\Support\Arr;
use RuntimeException;

use function Laravel\Ai\agent;

class CmsLayoutTranslationAiService
{
    public function __construct(private readonly AiProviderSettings $providerSettings) {}

    /**
     * @return array{name?: string, sections?: array<int, array<string, mixed>>}
     */
    public function translate(CmsLayout $sourceLayout, string $targetLocale): array
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
                    'You translate CMS layout content for a public website.',
                    'Only translate human-readable layout labels and block text fields.',
                    'Preserve HTML tags, placeholders, URLs, technical identifiers, system blocks, and all code snippets exactly.',
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
                $this->buildPrompt($sourceLayout, $targetLocale),
                provider: $provider,
                model: $model !== '' ? $model : null,
                timeout: 120,
            );
        } finally {
            if ($apiKey !== '') {
                config([$providerKeyPath => $originalProviderKey]);
            }
        }

        return $this->translatedPayload($response->toArray(), $sourceLayout);
    }

    private function buildPrompt(CmsLayout $sourceLayout, string $targetLocale): string
    {
        return implode("\n\n", [
            'Translate this CMS layout from '.$sourceLayout->locale.' to '.$targetLocale.'.',
            'Rules:',
            '- Keep meaning, tone, and call-to-action intent.',
            '- Keep all URLs, form keys, category IDs, tag IDs, media IDs, settings, code, scripts, styles, meta snippets, and system blocks unchanged.',
            '- Do not translate custom_head_code or custom_body_end_code content.',
            '- Only translate human-readable text fields.',
            'Layout JSON:',
            json_encode($this->sourcePayload($sourceLayout), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sourcePayload(CmsLayout $sourceLayout): array
    {
        return [
            'name' => (string) $sourceLayout->name,
            'sections' => $sourceLayout->sections
                ->where('is_active', true)
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($section, int $sectionIndex): array => [
                    'index' => $sectionIndex,
                    'zone' => (string) $section->zone,
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

        if (in_array($block->type, ['site_head', 'site_header', 'site_footer', 'custom_head_code', 'custom_body_end_code'], true)) {
            return [
                'index' => $placementIndex,
                'type' => (string) $block->type,
                'locked' => true,
            ];
        }

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
    private function translatedPayload(array $response, CmsLayout $sourceLayout): array
    {
        return [
            'name' => mb_substr(trim((string) Arr::get($response, 'name', $sourceLayout->name)), 0, 255),
            'sections' => Arr::get($response, 'sections', []),
        ];
    }
}
