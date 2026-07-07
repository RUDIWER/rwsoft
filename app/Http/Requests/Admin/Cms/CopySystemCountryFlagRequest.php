<?php

namespace App\Http\Requests\Admin\Cms;

use App\Support\Cms\CmsCountryFlagCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopySystemCountryFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $codes = collect(app(CmsCountryFlagCatalog::class)->all())
            ->pluck('code')
            ->filter()
            ->values()
            ->all();

        return [
            'country_code' => ['required', 'string', Rule::in($codes)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'country_code.required' => __('cms_admin_ui.validation.country_flag_required'),
            'country_code.in' => __('cms_admin_ui.validation.country_flag_not_found'),
        ];
    }
}
