<?php

namespace App\Http\Requests\Admin\Cms;

use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsMenuRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:160'],
            'placements' => ['required', 'array', 'min:1'],
            'placements.*' => ['required', 'string', Rule::in($this->allowedPlacements())],
            'is_active' => ['sometimes', 'boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*.title' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $defaultLocale = app(CmsLanguageSettings::class)->defaultLocale();
                $title = trim((string) ($this->input("translations.{$defaultLocale}.title") ?? $this->input('title', '')));

                if ($title === '') {
                    $validator->errors()->add("translations.{$defaultLocale}.title", __('cms_admin_ui.validation.menu_title_required'));
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
            'placements.required' => __('cms_admin_ui.validation.menu_placements_required'),
            'placements.min' => __('cms_admin_ui.validation.menu_placements_required'),
            'placements.*.in' => __('cms_admin_ui.validation.menu_placement_invalid'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedPlacements(): array
    {
        return array_keys((array) config('cms_menus.placements', []));
    }
}
