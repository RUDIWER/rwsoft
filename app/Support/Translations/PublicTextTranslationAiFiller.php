<?php

namespace App\Support\Translations;

use App\Support\Ai\AiProviderSettings;
use App\Support\Ai\TranslationAiService;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class PublicTextTranslationAiFiller
{
    public function __construct(
        private readonly PublicTextTranslationManager $translationManager,
        private readonly TranslationAiService $translationAiService,
        private readonly AiProviderSettings $providerSettings,
        private readonly CmsLanguageSettings $languageSettings,
    ) {}

    /**
     * @return array{provider:string,model:string,target_locale:string,source_locale:string,candidates:int,requested:int,updated:int,unresolved:int}
     */
    public function fillMissing(string $targetLocale, ?string $sourceLocale = null, int $limit = 50): array
    {
        $locales = $this->translationManager->locales();

        if (! in_array($targetLocale, $locales, true)) {
            throw ValidationException::withMessages([
                'target_locale' => __('translation_editor_ui.errors.unknown_locale'),
            ]);
        }

        $resolvedSourceLocale = trim((string) ($sourceLocale ?: $this->languageSettings->defaultLocale()));

        if (! in_array($resolvedSourceLocale, $locales, true)) {
            throw ValidationException::withMessages([
                'source_locale' => __('translation_editor_ui.errors.unknown_locale'),
            ]);
        }

        if ($resolvedSourceLocale === $targetLocale) {
            throw ValidationException::withMessages([
                'target_locale' => __('translation_editor_ui.errors.ai_target_matches_source'),
            ]);
        }

        $rows = $this->translationManager->rows();
        $candidates = collect($rows)
            ->filter(function (array $row) use ($targetLocale, $resolvedSourceLocale): bool {
                $sourceValue = trim((string) Arr::get($row, 'value_'.$resolvedSourceLocale, ''));
                $targetValue = trim((string) Arr::get($row, 'value_'.$targetLocale, ''));

                return $sourceValue !== '' && $targetValue === '';
            })
            ->values();
        $limitedCandidates = $candidates->take(max(1, $limit));
        $settings = $this->providerSettings->translationSettings();

        if ($limitedCandidates->isEmpty()) {
            return [
                'provider' => (string) ($settings['provider'] ?? 'gemini'),
                'model' => (string) ($settings['model'] ?? ''),
                'target_locale' => $targetLocale,
                'source_locale' => $resolvedSourceLocale,
                'candidates' => 0,
                'requested' => 0,
                'updated' => 0,
                'unresolved' => 0,
            ];
        }

        $payload = $limitedCandidates
            ->map(fn (array $row): array => [
                'id' => (string) Arr::get($row, 'id', ''),
                'key' => (string) Arr::get($row, 'key', ''),
                'source_text' => (string) Arr::get($row, 'value_'.$resolvedSourceLocale, ''),
            ])
            ->all();
        $translatedValues = $this->translationAiService->translateBatch($payload, $resolvedSourceLocale, $targetLocale);
        $updatesByRowId = [];
        $unresolved = 0;

        foreach ($payload as $item) {
            $id = (string) ($item['id'] ?? '');
            $translatedText = (string) ($translatedValues[$id] ?? '');

            if ($id === '' || trim($translatedText) === '') {
                $unresolved++;

                continue;
            }

            $updatesByRowId[$id] = $translatedText;
        }

        $bulkResult = $this->translationManager->updateManyByRowIds($targetLocale, $updatesByRowId);

        return [
            'provider' => (string) ($settings['provider'] ?? 'gemini'),
            'model' => (string) ($settings['model'] ?? ''),
            'target_locale' => $targetLocale,
            'source_locale' => $resolvedSourceLocale,
            'candidates' => $candidates->count(),
            'requested' => $limitedCandidates->count(),
            'updated' => (int) ($bulkResult['updated'] ?? 0),
            'unresolved' => $unresolved,
        ];
    }
}
