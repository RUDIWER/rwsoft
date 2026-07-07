<?php

namespace App\Support\Translations;

use App\Actions\Admin\Cms\SyncPublicTextKeysAction;
use App\Models\Cms\CmsPublicText;
use App\Models\Cms\CmsPublicTextTranslation;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsPublicTextCache;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicTextTranslationManager
{
    public function __construct(
        private readonly CmsLanguageSettings $languageSettings,
        private readonly SyncPublicTextKeysAction $syncPublicTextKeys,
        private readonly CmsPublicTextCache $publicTextCache,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        $locales = $this->locales();

        return CmsPublicText::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('group')
            ->orderBy('key')
            ->get()
            ->map(fn (CmsPublicText $text): array => $this->rowPayload($text, $locales))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function locales(): array
    {
        return $this->languageSettings->activeLocales();
    }

    /**
     * @return array<string, mixed>
     */
    public function updateByRowId(string $rowId, string $locale, string $value): array
    {
        $locales = $this->locales();

        if (! in_array($locale, $locales, true)) {
            throw ValidationException::withMessages([
                'locale' => __('translation_editor_ui.errors.unknown_locale'),
            ]);
        }

        $text = $this->findTextByRowId($rowId);

        CmsPublicTextTranslation::query()->updateOrCreate(
            [
                'cms_public_text_id' => $text->id,
                'locale' => $locale,
            ],
            ['value' => $value],
        );

        $this->publicTextCache->flush($locale);

        $text->load('translations');

        return $this->rowPayload($text, $locales);
    }

    /**
     * @param  array<string, string>  $updatesByRowId
     * @return array{updated:int}
     */
    public function updateManyByRowIds(string $locale, array $updatesByRowId): array
    {
        $updated = 0;

        DB::transaction(function () use ($locale, $updatesByRowId, &$updated): void {
            foreach ($updatesByRowId as $rowId => $value) {
                $this->updateByRowId((string) $rowId, $locale, (string) $value);
                $updated++;
            }
        });

        return ['updated' => $updated];
    }

    /**
     * @return array{texts_created:int,translations_created:int}
     */
    public function syncMissing(): array
    {
        $result = $this->syncPublicTextKeys->handle();

        $this->publicTextCache->flush();

        return $result;
    }

    private function findTextByRowId(string $rowId): CmsPublicText
    {
        $id = (int) preg_replace('/\D+/', '', $rowId);

        if ($id < 1) {
            throw ValidationException::withMessages([
                'row' => __('translation_editor_ui.errors.row_not_found'),
            ]);
        }

        $text = CmsPublicText::query()->find($id);

        if (! $text instanceof CmsPublicText) {
            throw ValidationException::withMessages([
                'row' => __('translation_editor_ui.errors.row_not_found'),
            ]);
        }

        return $text;
    }

    /**
     * @param  array<int, string>  $locales
     * @return array<string, mixed>
     */
    private function rowPayload(CmsPublicText $text, array $locales): array
    {
        /** @var EloquentCollection<int, CmsPublicTextTranslation> $translations */
        $translations = $text->translations;
        $missingLocales = [];
        $row = [
            'id' => 'public_'.$text->id,
            'source_label' => __('translation_editor_ui.tabs.public_site'),
            'source_color' => 'blue',
            'key' => $text->group.'.'.$text->key,
            'description' => $text->description,
            'status_label' => __('translation_editor_ui.status.complete'),
            'status_color' => 'green',
            'missing_locales' => [],
            'missing_locales_display' => '',
            'missing_count' => 0,
        ];

        foreach ($locales as $locale) {
            $value = (string) ($translations->firstWhere('locale', $locale)?->value ?? '');
            $row['value_'.$locale] = $value;

            if (trim($value) === '') {
                $missingLocales[] = $locale;
            }
        }

        $row['missing_locales'] = $missingLocales;
        $row['missing_locales_display'] = collect($missingLocales)->map(fn (string $locale): string => strtoupper($locale))->implode(', ');
        $row['missing_count'] = count($missingLocales);

        if ($missingLocales !== []) {
            $row['status_label'] = __('translation_editor_ui.status.missing');
            $row['status_color'] = 'orange';
        }

        return $row;
    }
}
