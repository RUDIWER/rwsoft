<?php

namespace App\Http\Requests\Admin\Dev\Translation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $locales = (array) config('app.available_locales', [config('app.locale', 'en')]);

        return [
            'target_locales' => ['nullable', 'array'],
            'target_locales.*' => ['string', Rule::in($locales)],
        ];
    }
}
