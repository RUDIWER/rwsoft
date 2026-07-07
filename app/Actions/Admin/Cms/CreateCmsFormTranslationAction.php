<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Support\Ai\CmsFormTranslationAiService;
use App\Support\PublicSite\CmsFormOptionNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCmsFormTranslationAction
{
    public function __construct(private readonly CmsFormTranslationAiService $translationAiService) {}

    public function handle(CmsForm $sourceForm, string $targetLocale, bool $useAi = false): CmsForm
    {
        $sourceForm->loadMissing('fields');
        $translationKey = $this->ensureTranslationKey($sourceForm);

        foreach ($sourceForm->fields as $sourceField) {
            $this->ensureFieldTranslationKey($sourceField);
        }

        $translatedData = $useAi
            ? $this->translationAiService->translate($sourceForm, $targetLocale)
            : [];

        return DB::transaction(function () use ($sourceForm, $targetLocale, $translatedData, $useAi, $translationKey): CmsForm {
            $form = new CmsForm;
            $form->fill([
                'title' => (string) ($translatedData['title'] ?? $sourceForm->title),
                'locale' => $targetLocale,
                'translation_key' => $translationKey,
                'translated_from_form_id' => $sourceForm->id,
                'description' => array_key_exists('description', $translatedData) ? $translatedData['description'] : $sourceForm->description,
                'notification_email' => $sourceForm->notification_email,
                'submit_button_label' => array_key_exists('submit_button_label', $translatedData) ? $translatedData['submit_button_label'] : $sourceForm->submit_button_label,
                'success_message' => array_key_exists('success_message', $translatedData) ? $translatedData['success_message'] : $sourceForm->success_message,
                'is_active' => false,
                'settings' => $this->settings($sourceForm, $useAi),
            ]);
            $form->save();

            $translatedFields = collect($translatedData['fields'] ?? [])
                ->filter(fn (mixed $field): bool => is_array($field))
                ->keyBy(fn (array $field): string => (string) ($field['translation_key'] ?? ''));

            foreach ($sourceForm->fields as $sourceField) {
                $fieldTranslationKey = $this->ensureFieldTranslationKey($sourceField);
                $translatedField = $translatedFields->get($fieldTranslationKey, []);

                $form->fields()->create([
                    'type' => $sourceField->type,
                    'translation_key' => $fieldTranslationKey,
                    'translated_from_form_field_id' => $sourceField->id,
                    'label' => (string) ($translatedField['label'] ?? $sourceField->label),
                    'placeholder' => array_key_exists('placeholder', $translatedField) ? $translatedField['placeholder'] : $sourceField->placeholder,
                    'help_text' => array_key_exists('help_text', $translatedField) ? $translatedField['help_text'] : $sourceField->help_text,
                    'options' => $this->translatedOptions($sourceField, $translatedField),
                    'validation_rules' => $sourceField->validation_rules,
                    'sort_order' => $sourceField->sort_order,
                    'is_required' => $sourceField->is_required,
                    'is_active' => $sourceField->is_active,
                    'width' => $sourceField->width,
                    'settings' => $sourceField->settings ?? [],
                ]);
            }

            return $form;
        });
    }

    private function ensureTranslationKey(CmsForm $form): string
    {
        if (filled($form->translation_key)) {
            return (string) $form->translation_key;
        }

        $translationKey = (string) Str::ulid();

        $form->forceFill(['translation_key' => $translationKey])->save();

        return $translationKey;
    }

    private function ensureFieldTranslationKey(CmsFormField $field): string
    {
        if (filled($field->translation_key)) {
            return (string) $field->translation_key;
        }

        $translationKey = (string) Str::ulid();

        $field->forceFill(['translation_key' => $translationKey])->save();

        return $translationKey;
    }

    /**
     * @param  array<string, mixed>  $translatedField
     * @return array<int, array{key: string, label: string}>
     */
    private function translatedOptions(CmsFormField $sourceField, array $translatedField): array
    {
        $translatedOptions = collect(CmsFormOptionNormalizer::normalize($translatedField['options'] ?? []))
            ->keyBy(fn (array $option): string => (string) ($option['key'] ?? ''));

        return collect(CmsFormOptionNormalizer::normalize($sourceField->options ?? []))
            ->map(function (array $option) use ($translatedOptions): array {
                $key = (string) $option['key'];
                $translatedOption = $translatedOptions->get($key, []);

                return [
                    'key' => $key,
                    'label' => (string) ($translatedOption['label'] ?? $option['label']),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(CmsForm $sourceForm, bool $useAi): array
    {
        $settings = $sourceForm->settings ?? [];

        if (! $useAi) {
            unset($settings['translation_source'], $settings['translation_review_status']);

            return $settings;
        }

        return array_merge($settings, [
            'translation_source' => 'ai',
            'translation_review_status' => 'pending',
        ]);
    }
}
