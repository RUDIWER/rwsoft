<?php

namespace App\Support\Ai;

use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Support\PublicSite\CmsFormOptionNormalizer;
use Illuminate\Support\Arr;
use RuntimeException;

use function Laravel\Ai\agent;

class CmsFormTranslationAiService
{
    public function __construct(private readonly AiProviderSettings $providerSettings) {}

    /**
     * @return array{title?: string, description?: string|null, submit_button_label?: string|null, success_message?: string|null, fields?: array<int, array<string, mixed>>}
     */
    public function translate(CmsForm $sourceForm, string $targetLocale): array
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
                    'You translate public CMS form labels and helper texts for a website.',
                    'Preserve technical identifiers, option keys, placeholders, URLs, names, numbers, and identifiers.',
                    'Never include personal data examples. Never add explanations. Return translated content only through the structured schema.',
                ]),
                schema: fn ($schema): array => [
                    'title' => $schema->string()->required(),
                    'description' => $schema->string(),
                    'submit_button_label' => $schema->string(),
                    'success_message' => $schema->string(),
                    'fields' => $schema
                        ->array()
                        ->items($schema->object([
                            'translation_key' => $schema->string()->required(),
                            'label' => $schema->string()->required(),
                            'placeholder' => $schema->string(),
                            'help_text' => $schema->string(),
                            'options' => $schema
                                ->array()
                                ->items($schema->object([
                                    'key' => $schema->string()->required(),
                                    'label' => $schema->string()->required(),
                                ])),
                        ]))
                        ->required(),
                ],
            )->prompt(
                $this->buildPrompt($sourceForm, $targetLocale),
                provider: $provider,
                model: $model !== '' ? $model : null,
                timeout: 120,
            );
        } finally {
            if ($apiKey !== '') {
                config([$providerKeyPath => $originalProviderKey]);
            }
        }

        return $this->translatedPayload($response->toArray(), $sourceForm);
    }

    private function buildPrompt(CmsForm $sourceForm, string $targetLocale): string
    {
        return implode("\n\n", [
            'Translate this CMS form from '.$sourceForm->locale.' to '.$targetLocale.'.',
            'Rules:',
            '- Only translate human-readable text fields.',
            '- Keep form translation_key, field translation_key, and option keys unchanged.',
            '- Keep placeholders like :name, {{ token }}, {0}, and [shortcodes] unchanged.',
            '- Do not invent fields or options.',
            'Form JSON:',
            json_encode($this->sourcePayload($sourceForm), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sourcePayload(CmsForm $sourceForm): array
    {
        $sourceForm->loadMissing('fields');

        return [
            'translation_key' => (string) $sourceForm->translation_key,
            'title' => (string) $sourceForm->title,
            'description' => (string) ($sourceForm->description ?? ''),
            'submit_button_label' => (string) ($sourceForm->submit_button_label ?? ''),
            'success_message' => (string) ($sourceForm->success_message ?? ''),
            'fields' => $sourceForm->fields
                ->map(fn (CmsFormField $field): array => [
                    'translation_key' => (string) $field->translation_key,
                    'label' => (string) $field->label,
                    'placeholder' => (string) ($field->placeholder ?? ''),
                    'help_text' => (string) ($field->help_text ?? ''),
                    'options' => CmsFormOptionNormalizer::normalize($field->options ?? []),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{title?: string, description?: string|null, submit_button_label?: string|null, success_message?: string|null, fields?: array<int, array<string, mixed>>}
     */
    private function translatedPayload(array $response, CmsForm $sourceForm): array
    {
        $sourceFields = $sourceForm->fields->keyBy('translation_key');
        $fields = collect(Arr::get($response, 'fields', []))
            ->filter(fn (mixed $field): bool => is_array($field) && $sourceFields->has((string) ($field['translation_key'] ?? '')))
            ->map(fn (array $field): array => $this->translatedFieldPayload($field, $sourceFields->get((string) $field['translation_key'])))
            ->values()
            ->all();

        return [
            'title' => $this->limit((string) Arr::get($response, 'title', $sourceForm->title), 255),
            'description' => $this->nullableLimit(Arr::get($response, 'description'), 5000),
            'submit_button_label' => $this->nullableLimit(Arr::get($response, 'submit_button_label'), 120),
            'success_message' => $this->nullableLimit(Arr::get($response, 'success_message'), 1000),
            'fields' => $fields,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function translatedFieldPayload(array $field, CmsFormField $sourceField): array
    {
        $sourceOptions = collect(CmsFormOptionNormalizer::normalize($sourceField->options ?? []))
            ->keyBy(fn (array $option): string => (string) $option['key']);

        return [
            'translation_key' => (string) $sourceField->translation_key,
            'label' => $this->limit((string) Arr::get($field, 'label', $sourceField->label), 255),
            'placeholder' => $this->nullableLimit(Arr::get($field, 'placeholder'), 255),
            'help_text' => $this->nullableLimit(Arr::get($field, 'help_text'), 1000),
            'options' => collect(Arr::get($field, 'options', []))
                ->filter(fn (mixed $option): bool => is_array($option) && $sourceOptions->has((string) ($option['key'] ?? '')))
                ->map(fn (array $option): array => [
                    'key' => (string) $option['key'],
                    'label' => $this->limit((string) ($option['label'] ?? $sourceOptions->get((string) $option['key'])['label']), 255),
                ])
                ->values()
                ->all(),
        ];
    }

    private function nullableLimit(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $this->limit($value, $limit) : null;
    }

    private function limit(string $value, int $limit): string
    {
        return mb_substr(trim($value), 0, $limit);
    }
}
