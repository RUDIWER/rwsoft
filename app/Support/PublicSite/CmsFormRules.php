<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsFormField;
use Illuminate\Validation\Rule;

class CmsFormRules
{
    /**
     * @return array<int, mixed>
     */
    public function forField(CmsFormField $field): array
    {
        $rules = $field->is_required ? ['required'] : ['nullable'];

        if ($field->type === 'email') {
            $rules[] = 'email';
            $rules[] = 'max:255';

            return $rules;
        }

        if ($field->type === 'checkbox') {
            $rules[] = $field->is_required ? 'accepted' : 'boolean';

            return $rules;
        }

        if ($field->type === 'number') {
            $rules[] = 'numeric';

            return $rules;
        }

        if ($field->type === 'date') {
            $rules[] = 'date_format:Y-m-d';

            return $rules;
        }

        if ($field->type === 'time') {
            $rules[] = 'date_format:H:i';

            return $rules;
        }

        if ($field->type === 'select') {
            $rules[] = 'string';
            $rules[] = 'max:255';
            $rules[] = Rule::in(
                collect(CmsFormOptionNormalizer::normalize($field->options ?? []))
                    ->pluck('key')
                    ->map(fn ($key): string => (string) $key)
                    ->all()
            );

            return $rules;
        }

        if ($field->type === 'combobox') {
            $rules[] = 'string';
            $rules[] = 'max:255';

            return $rules;
        }

        $rules[] = 'string';
        $rules[] = $field->type === 'textarea' ? 'max:10000' : 'max:255';

        return $rules;
    }
}
