<?php

namespace App\Support\Ai;

use App\Models\Cms\CmsMenuItem;
use Illuminate\Support\Arr;
use RuntimeException;

use function Laravel\Ai\agent;

class CmsMenuItemTranslationAiService
{
    public function __construct(private readonly AiProviderSettings $providerSettings) {}

    /**
     * @return array{label?: string, url?: string|null}
     */
    public function translate(CmsMenuItem $sourceItem, string $targetLocale): array
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
                    'You translate CMS navigation menu item text for a public website.',
                    'Preserve external URLs.',
                    'Never add explanations. Return translated content only through the structured schema.',
                ]),
                schema: fn ($schema): array => [
                    'label' => $schema->string()->required(),
                    'url' => $schema->string(),
                ],
            )->prompt(
                $this->buildPrompt($sourceItem, $targetLocale),
                provider: $provider,
                model: $model !== '' ? $model : null,
                timeout: 60,
            );
        } finally {
            if ($apiKey !== '') {
                config([$providerKeyPath => $originalProviderKey]);
            }
        }

        return [
            'label' => mb_substr(trim((string) Arr::get($response->toArray(), 'label', $sourceItem->label)), 0, 160),
            'url' => $this->nullableLimit(Arr::get($response->toArray(), 'url', $sourceItem->url), 2048),
        ];
    }

    private function buildPrompt(CmsMenuItem $sourceItem, string $targetLocale): string
    {
        return implode("\n\n", [
            'Translate this CMS menu item from '.$sourceItem->locale.' to '.$targetLocale.'.',
            'Rules:',
            '- Keep labels short and suitable for navigation.',
            '- Keep external URLs unchanged.',
            '- Keep external URLs unchanged.',
            'Menu item JSON:',
            json_encode([
                'type' => $sourceItem->type,
                'label' => $sourceItem->label,
                'url' => $sourceItem->url,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    private function nullableLimit(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? mb_substr($value, 0, $limit) : null;
    }
}
