<?php

namespace App\Http\Requests\Admin\Dev\Translation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sourceValues = ['all', 'dynamic_prompts', 'rwtable'];

        return [
            'source' => ['nullable', 'string', Rule::in($sourceValues)],
        ];
    }
}
