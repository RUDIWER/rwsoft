<?php

namespace App\Support\Ai;

use App\Models\Cms\CmsPost;
use Illuminate\Support\Arr;
use RuntimeException;

use function Laravel\Ai\agent;

class CmsPostTranslationAiService
{
    public function __construct(private readonly AiProviderSettings $providerSettings) {}

    /**
     * @return array{title?: string, excerpt?: string|null, seo_title?: string|null, seo_description?: string|null, content_blocks?: array<int, array<string, mixed>>}
     */
    public function translate(CmsPost $sourcePost, string $targetLocale): array
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
                    'You translate CMS blog post content for a public website.',
                    'Preserve HTML tags, markdown, placeholders, shortcodes, numbers, names, URLs, and technical identifiers.',
                    'Never add explanations. Return translated content only through the structured schema.',
                ]),
                schema: fn ($schema): array => [
                    'title' => $schema->string()->required(),
                    'excerpt' => $schema->string(),
                    'seo_title' => $schema->string(),
                    'seo_description' => $schema->string(),
                    'content_blocks' => $schema
                        ->array()
                        ->items($schema->object([
                            'index' => $schema->integer()->required(),
                            'title' => $schema->string(),
                            'text' => $schema->string(),
                            'source' => $schema->string(),
                            'caption' => $schema->string(),
                            'label' => $schema->string(),
                        ]))
                        ->required(),
                ],
            )->prompt(
                $this->buildPrompt($sourcePost, $targetLocale),
                provider: $provider,
                model: $model !== '' ? $model : null,
                timeout: 120,
            );
        } finally {
            if ($apiKey !== '') {
                config([$providerKeyPath => $originalProviderKey]);
            }
        }

        return $this->translatedPayload($response->toArray(), $sourcePost);
    }

    private function buildPrompt(CmsPost $sourcePost, string $targetLocale): string
    {
        return implode("\n\n", [
            'Translate this CMS blog post from '.$sourcePost->locale.' to '.$targetLocale.'.',
            'Rules:',
            '- Keep meaning, tone, and call-to-action intent.',
            '- Keep all URLs unchanged.',
            '- Keep HTML tags and placeholders like :name, {{ token }}, {0}, and [shortcodes] unchanged.',
            '- Only translate human-readable text fields.',
            'Post JSON:',
            json_encode($this->sourcePayload($sourcePost), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sourcePayload(CmsPost $sourcePost): array
    {
        return [
            'title' => (string) $sourcePost->title,
            'excerpt' => (string) ($sourcePost->excerpt ?? ''),
            'seo_title' => (string) ($sourcePost->seo_title ?? ''),
            'seo_description' => (string) ($sourcePost->seo_description ?? ''),
            'content_blocks' => collect($sourcePost->content_blocks ?? [])
                ->filter(fn (mixed $block): bool => is_array($block))
                ->values()
                ->map(fn (array $block, int $index): array => $this->translatableBlockPayload($block, $index))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function translatableBlockPayload(array $block, int $index): array
    {
        return array_filter([
            'index' => $index,
            'type' => (string) ($block['type'] ?? 'text'),
            'title' => $block['title'] ?? null,
            'text' => $block['text'] ?? null,
            'source' => $block['source'] ?? null,
            'caption' => $block['caption'] ?? null,
            'label' => $block['label'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array{title?: string, excerpt?: string|null, seo_title?: string|null, seo_description?: string|null, content_blocks?: array<int, array<string, mixed>>}
     */
    private function translatedPayload(array $response, CmsPost $sourcePost): array
    {
        $contentBlocks = $sourcePost->content_blocks ?? [];
        $translatedBlocks = collect(Arr::get($response, 'content_blocks', []))
            ->filter(fn (mixed $block): bool => is_array($block))
            ->keyBy(fn (array $block): int => (int) Arr::get($block, 'index', -1));

        foreach ($contentBlocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            $translatedBlock = $translatedBlocks->get((int) $index, []);

            foreach (['title', 'text', 'source', 'caption', 'label'] as $field) {
                $translatedValue = trim((string) Arr::get($translatedBlock, $field, ''));

                if ($translatedValue !== '') {
                    $contentBlocks[$index][$field] = $this->limit($translatedValue, $this->blockLimit($field));
                }
            }
        }

        return [
            'title' => $this->limit((string) Arr::get($response, 'title', $sourcePost->title), 255),
            'excerpt' => $this->nullableLimit(Arr::get($response, 'excerpt'), 5000),
            'seo_title' => $this->nullableLimit(Arr::get($response, 'seo_title'), 255),
            'seo_description' => $this->nullableLimit(Arr::get($response, 'seo_description'), 1000),
            'content_blocks' => $contentBlocks,
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

    private function blockLimit(string $field): int
    {
        return match ($field) {
            'title' => 255,
            'source', 'caption' => 500,
            'label' => 120,
            default => 20000,
        };
    }
}
