<?php

namespace App\Support\Ai;

use Illuminate\Support\Arr;
use RuntimeException;

use function Laravel\Ai\agent;

class TranslationAiService
{
    public function __construct(private readonly AiProviderSettings $providerSettings)
    {
        //
    }

    /**
     * @param  array<int, array{id:string,key:string,source_text:string}>  $items
     * @param  array<int, string>  $rules
     * @return array<string, string>
     */
    public function translateBatch(array $items, string $sourceLocale, string $targetLocale, ?string $instructions = null, array $rules = []): array
    {
        if ($items === []) {
            return [];
        }

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
                instructions: $instructions ?: 'You translate application UI strings. Keep placeholders, variable tokens, and formatting intact. Never add explanations. Return exactly one translated text for each provided id.',
                schema: fn ($schema): array => [
                    'translations' => $schema
                        ->array()
                        ->items($schema->object([
                            'id' => $schema->string()->required(),
                            'text' => $schema->string()->required(),
                        ]))
                        ->required(),
                ],
            )->prompt(
                $this->buildPrompt($items, $sourceLocale, $targetLocale, $rules),
                provider: $provider,
                model: $model !== '' ? $model : null,
            );
        } finally {
            if ($apiKey !== '') {
                config([$providerKeyPath => $originalProviderKey]);
            }
        }

        $translatedItems = Arr::get($response->toArray(), 'translations', []);

        if (! is_array($translatedItems)) {
            return [];
        }

        return collect($translatedItems)
            ->mapWithKeys(static function (mixed $item): array {
                if (! is_array($item)) {
                    return [];
                }

                $id = trim((string) Arr::get($item, 'id', ''));
                $text = trim((string) Arr::get($item, 'text', ''));

                if ($id === '' || $text === '') {
                    return [];
                }

                return [$id => $text];
            })
            ->all();
    }

    /**
     * @param  array<int, array{id:string,key:string,source_text:string}>  $items
     * @param  array<int, string>  $rules
     */
    private function buildPrompt(array $items, string $sourceLocale, string $targetLocale, array $rules = []): string
    {
        $payload = collect($items)
            ->map(static function (array $item): array {
                return [
                    'id' => (string) $item['id'],
                    'key' => (string) $item['key'],
                    'source_text' => (string) $item['source_text'],
                ];
            })
            ->values()
            ->all();

        $rules = $rules !== [] ? $rules : [
            'Preserve placeholders like :name, :count, {0}, {1}, {{ token }}, and HTML tags.',
            'Keep tone concise and suitable for admin interface text.',
            'Return one translation per id.',
        ];

        return implode("\n\n", [
            'Translate these texts for a Laravel application.',
            'Source locale: '.$sourceLocale,
            'Target locale: '.$targetLocale,
            'Rules:',
            collect($rules)->map(fn (string $rule): string => '- '.$rule)->implode("\n"),
            'Items JSON:',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
        ]);
    }
}
