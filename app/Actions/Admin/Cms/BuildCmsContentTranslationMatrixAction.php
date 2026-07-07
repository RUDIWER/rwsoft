<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsTag;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BuildCmsContentTranslationMatrixAction
{
    public function __construct(private readonly CmsLanguageSettings $languageSettings) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, locales: array<int, string>}
     */
    public function handle(): array
    {
        $locales = $this->languageSettings->activeLocales();
        $rows = collect()
            ->merge($this->rowsFor(CmsPage::class, 'page', __('cms_admin_ui.content_matrix.types.page'), 'admin.cms.pages.edit'))
            ->merge($this->rowsFor(CmsPost::class, 'post', __('cms_admin_ui.content_matrix.types.post'), 'admin.cms.posts.edit'))
            ->merge($this->rowsFor(CmsCategory::class, 'category', __('cms_admin_ui.content_matrix.types.category'), 'admin.cms.categories.edit'))
            ->merge($this->rowsFor(CmsTag::class, 'tag', __('cms_admin_ui.content_matrix.types.tag'), 'admin.cms.tags.edit'))
            ->merge($this->rowsFor(CmsForm::class, 'form', __('cms_admin_ui.content_matrix.types.form'), 'admin.cms.forms.edit'))
            ->merge($this->rowsFor(CmsMenuItem::class, 'menu_item', __('cms_admin_ui.content_matrix.types.menu_item'), 'admin.cms.menus.edit', 'label'))
            ->sortBy([
                ['type_label', 'asc'],
                ['source_label', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'locales' => $locales,
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return Collection<int, array<string, mixed>>
     */
    private function rowsFor(
        string $modelClass,
        string $type,
        string $typeLabel,
        string $editRoute,
        string $labelField = 'title'
    ): Collection {
        $records = $modelClass::query()
            ->get()
            ->groupBy(fn (Model $record): string => $this->groupKey($type, $record));

        return $records->map(function (Collection $translations, string $translationKey) use ($type, $typeLabel, $editRoute, $labelField): array {
            $source = $this->sourceRecord($translations, $labelField);
            $missingLocales = [];
            $aiReviewLocales = [];
            $row = [
                'id' => $type.'::'.$translationKey,
                'type' => $type,
                'type_label' => $typeLabel,
                'source_id' => $source->getKey(),
                'source_label' => $this->recordLabel($source, $labelField),
                'source_locale' => (string) $source->getAttribute('locale'),
                'missing_locales' => [],
                'ai_review_locales' => [],
                'missing_locales_display' => '',
                'ai_review_locales_display' => '',
                'missing_count' => 0,
                'ai_review_count' => 0,
            ];

            foreach ($this->languageSettings->activeLocales() as $locale) {
                $translation = $translations->first(fn (Model $record): bool => (string) $record->getAttribute('locale') === $locale);

                if (! $translation instanceof Model) {
                    $missingLocales[] = $locale;
                    $row['value_'.$locale] = __('cms_admin_ui.content_matrix.status.missing');
                    $row['url_'.$locale] = null;

                    continue;
                }

                if ($this->isPendingAiReview($translation)) {
                    $aiReviewLocales[] = $locale;
                }

                $row['value_'.$locale] = $this->statusLabel($translation);
                $row['url_'.$locale] = $this->editUrl($translation, $editRoute, $type);
            }

            $row['missing_locales'] = $missingLocales;
            $row['ai_review_locales'] = $aiReviewLocales;
            $row['missing_locales_display'] = collect($missingLocales)->map(fn (string $locale): string => strtoupper($locale))->implode(', ');
            $row['ai_review_locales_display'] = collect($aiReviewLocales)->map(fn (string $locale): string => strtoupper($locale))->implode(', ');
            $row['missing_count'] = count($missingLocales);
            $row['ai_review_count'] = count($aiReviewLocales);

            return $row;
        })->values();
    }

    private function groupKey(string $type, Model $record): string
    {
        $translationKey = trim((string) $record->getAttribute('translation_key'));

        return $translationKey !== '' ? $translationKey : $type.'-'.$record->getKey();
    }

    private function sourceRecord(Collection $translations, string $labelField): Model
    {
        $defaultLocale = $this->languageSettings->defaultLocale();

        return $translations->first(fn (Model $record): bool => (string) $record->getAttribute('locale') === $defaultLocale)
            ?? $translations->sortBy('id')->first();
    }

    private function recordLabel(Model $record, string $labelField): string
    {
        $label = trim((string) $record->getAttribute($labelField));

        return $label !== '' ? $label : '#'.$record->getKey();
    }

    private function statusLabel(Model $record): string
    {
        if ($this->isPendingAiReview($record)) {
            return __('cms_admin_ui.content_matrix.status.ai_draft');
        }

        $status = trim((string) $record->getAttribute('status'));

        if ($status !== '') {
            return $status;
        }

        if ($record->getAttribute('is_active') !== null) {
            return (bool) $record->getAttribute('is_active')
                ? __('cms_admin_ui.content_matrix.status.active')
                : __('cms_admin_ui.content_matrix.status.inactive');
        }

        return __('cms_admin_ui.content_matrix.status.exists');
    }

    private function isPendingAiReview(Model $record): bool
    {
        $metadata = (array) ($record->getAttribute('metadata') ?? []);
        $settings = (array) ($record->getAttribute('settings') ?? []);
        $source = (string) ($settings['translation_source'] ?? $metadata['translation_source'] ?? '');
        $reviewStatus = (string) ($settings['translation_review_status'] ?? $metadata['translation_review_status'] ?? '');

        return $source === 'ai' && $reviewStatus === 'pending';
    }

    private function editUrl(Model $record, string $editRoute, string $type): string
    {
        if ($type === 'menu_item') {
            return route($editRoute, ['id' => $record->getAttribute('cms_menu_id')]);
        }

        return route($editRoute, ['id' => $record->getKey()]);
    }
}
