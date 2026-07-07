<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportCmsThemeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'theme_zip' => ['required', 'file', 'mimes:zip', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'theme_zip.required' => 'Selecteer een theme ZIP bestand.',
            'theme_zip.mimes' => 'Het theme bestand moet een ZIP bestand zijn.',
            'theme_zip.max' => 'Het theme ZIP bestand mag maximaal 20MB zijn.',
        ];
    }
}
