<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestoreCmsRevisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(['content', 'full'])],
            'confirm_layout_impact' => ['nullable', 'boolean'],
            'confirm_template_impact' => ['nullable', 'boolean'],
        ];
    }
}
