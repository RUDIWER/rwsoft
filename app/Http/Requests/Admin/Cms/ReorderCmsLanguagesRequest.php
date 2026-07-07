<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsLanguage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderCmsLanguagesRequest extends FormRequest
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
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['required', 'integer', 'distinct:strict', 'exists:cms_languages,id'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $submittedIds = collect($this->input('languages', []))
                    ->map(fn (mixed $id): int => (int) $id)
                    ->sort()
                    ->values();
                $existingIds = CmsLanguage::query()
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->sort()
                    ->values();

                if ($submittedIds->all() !== $existingIds->all()) {
                    $validator->errors()->add(
                        'languages',
                        __('cms_admin_ui.validation.language_order_complete')
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'languages.required' => __('cms_admin_ui.validation.language_order_required'),
            'languages.array' => __('cms_admin_ui.validation.language_order_required'),
            'languages.min' => __('cms_admin_ui.validation.language_order_required'),
            'languages.*.integer' => __('cms_admin_ui.validation.language_order_invalid'),
            'languages.*.distinct' => __('cms_admin_ui.validation.language_order_unique'),
            'languages.*.exists' => __('cms_admin_ui.validation.language_order_invalid'),
        ];
    }
}
