<?php

namespace App\Support\Ai;

use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsTemplate;
use App\Support\Cms\CmsTemplateDataContract;
use Illuminate\Support\Arr;
use RuntimeException;

use function Laravel\Ai\agent;

class CmsPageTranslationAiService
{
    public function __construct(
        private readonly AiProviderSettings $providerSettings,
        private readonly CmsTemplateDataContract $templateDataContract,
    ) {}

    /**
     * @return array{title?: string, short_description?: string|null, seo_title?: string|null, seo_description?: string|null, template_data?: array<string, mixed>}
     */
    public function translate(CmsPage $sourcePage, string $targetLocale): array
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
                    'You translate CMS web page content for a public website.',
                    'Preserve HTML tags, markdown, placeholders, shortcodes, numbers, names, URLs, and technical identifiers.',
                    'Never add explanations. Return translated content only through the structured schema.',
                ]),
                schema: fn ($schema): array => [
                    'title' => $schema->string()->required(),
                    'short_description' => $schema->string(),
                    'seo_title' => $schema->string(),
                    'seo_description' => $schema->string(),
                    'template_data' => $schema
                        ->array()
                        ->items($schema->object([
                            'key' => $schema->string()->required(),
                            'value' => $schema->string(),
                        ]))
                        ->required(),
                ],
            )->prompt(
                $this->buildPrompt($sourcePage, $targetLocale),
                provider: $provider,
                model: $model !== '' ? $model : null,
                timeout: 120,
            );
        } finally {
            if ($apiKey !== '') {
                config([$providerKeyPath => $originalProviderKey]);
            }
        }

        return $this->translatedPayload($response->toArray(), $sourcePage);
    }

    private function buildPrompt(CmsPage $sourcePage, string $targetLocale): string
    {
        return implode("\n\n", [
            'Translate this CMS page from '.$sourcePage->locale.' to '.$targetLocale.'.',
            'Rules:',
            '- Keep meaning, tone, and call-to-action intent.',
            '- Keep all URLs unchanged.',
            '- Keep HTML tags and placeholders like :name, {{ token }}, {0}, and [shortcodes] unchanged.',
            '- Only translate human-readable text fields.',
            'Page JSON:',
            json_encode($this->sourcePayload($sourcePage), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sourcePayload(CmsPage $sourcePage): array
    {
        return [
            'title' => (string) $sourcePage->title,
            'short_description' => (string) ($sourcePage->short_description ?? ''),
            'seo_title' => (string) ($sourcePage->seo_title ?? ''),
            'seo_description' => (string) ($sourcePage->seo_description ?? ''),
            'template_data' => $this->translatableTemplateDataPayload($sourcePage),
        ];
    }

    /**
     * @return array<int, array{key: string, value: string}>
     */
    private function translatableTemplateDataPayload(CmsPage $sourcePage): array
    {
        $sourcePage->loadMissing('detailTemplate');
        $template = $sourcePage->detailTemplate;

        if (! $template instanceof CmsTemplate) {
            return [];
        }

        $contract = $this->templateDataContract->normalize($template->data_contract, (string) $template->template_key);

        return collect($contract['template_fields'])
            ->filter(fn (array $field): bool => in_array($field['type'], ['text', 'textarea'], true))
            ->map(function (array $field) use ($sourcePage): ?array {
                $value = Arr::get($sourcePage->template_data ?? [], $field['key']);

                if (! is_scalar($value) || trim((string) $value) === '') {
                    return null;
                }

                return [
                    'key' => (string) $field['key'],
                    'value' => (string) $value,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{title?: string, short_description?: string|null, seo_title?: string|null, seo_description?: string|null, template_data?: array<string, mixed>}
     */
    private function translatedPayload(array $response, CmsPage $sourcePage): array
    {
        return [
            'title' => $this->limit((string) Arr::get($response, 'title', $sourcePage->title), 255),
            'short_description' => $this->nullableLimit(Arr::get($response, 'short_description'), 5000),
            'seo_title' => $this->nullableLimit(Arr::get($response, 'seo_title'), 255),
            'seo_description' => $this->nullableLimit(Arr::get($response, 'seo_description'), 1000),
            'template_data' => $this->translatedTemplateData(Arr::get($response, 'template_data', []), $sourcePage),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function translatedTemplateData(mixed $responseTemplateData, CmsPage $sourcePage): array
    {
        $sourcePage->loadMissing('detailTemplate');
        $template = $sourcePage->detailTemplate;

        if (! $template instanceof CmsTemplate || ! is_array($responseTemplateData)) {
            return [];
        }

        $contract = $this->templateDataContract->normalize($template->data_contract, (string) $template->template_key);
        $textFields = collect($contract['template_fields'])
            ->filter(fn (array $field): bool => in_array($field['type'], ['text', 'textarea'], true))
            ->keyBy('key');
        $templateData = [];

        foreach ($responseTemplateData as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = (string) Arr::get($item, 'key', '');
            $field = $textFields->get($key);
            $value = trim((string) Arr::get($item, 'value', ''));

            if (! is_array($field) || $value === '') {
                continue;
            }

            Arr::set($templateData, $key, $this->limit($value, $field['type'] === 'textarea' ? 10000 : 255));
        }

        return $templateData;
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
