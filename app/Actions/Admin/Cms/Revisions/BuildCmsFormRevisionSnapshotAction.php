<?php

namespace App\Actions\Admin\Cms\Revisions;

use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Support\PublicSite\CmsFormOptionNormalizer;

class BuildCmsFormRevisionSnapshotAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(CmsForm $form): array
    {
        $form->loadMissing('fields');

        return [
            'schema_version' => 1,
            'subject' => [
                'type' => 'cms_form',
                'id' => $form->id,
            ],
            'form' => [
                'title' => $form->title,
                'locale' => $form->locale,
                'description' => $form->description,
                'notification_email' => $form->notification_email,
                'submit_button_label' => $form->submit_button_label,
                'success_message' => $form->success_message,
                'is_active' => (bool) $form->is_active,
                'settings' => $form->settings ?? [],
                'fields' => $form->fields
                    ->sortBy('sort_order')
                    ->map(fn (CmsFormField $field): array => [
                        'id' => $field->id,
                        'type' => $field->type,
                        'translation_key' => $field->translation_key,
                        'translated_from_form_field_id' => $field->translated_from_form_field_id,
                        'label' => $field->label,
                        'placeholder' => $field->placeholder,
                        'help_text' => $field->help_text,
                        'options' => CmsFormOptionNormalizer::normalize($field->options ?? []),
                        'validation_rules' => $field->validation_rules ?? [],
                        'sort_order' => (int) $field->sort_order,
                        'is_required' => (bool) $field->is_required,
                        'is_active' => (bool) $field->is_active,
                        'width' => $field->width ?: 'full',
                        'settings' => $field->settings ?? [],
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }
}
