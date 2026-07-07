<?php

namespace App\Http\Requests\Admin\Locale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $availableLocales = config('app.available_locales', [config('app.locale', 'en')]);

        return [
            'locale' => ['required', 'string', Rule::in($availableLocales)],
        ];
    }
}
