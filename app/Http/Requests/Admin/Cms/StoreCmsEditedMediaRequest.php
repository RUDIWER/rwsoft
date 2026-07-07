<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsEditedMediaRequest extends FormRequest
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
        return [
            'context_type' => ['nullable', 'string', Rule::in(['page', 'post', 'category', 'tag', 'cms_settings'])],
            'context_id' => ['nullable', 'integer', 'min:1'],
            'crop' => ['nullable', 'array:x,y,width,height'],
            'crop.x' => ['nullable', 'numeric', 'min:0'],
            'crop.y' => ['nullable', 'numeric', 'min:0'],
            'crop.width' => ['nullable', 'numeric', 'min:1', 'max:8000'],
            'crop.height' => ['nullable', 'numeric', 'min:1', 'max:8000'],
            'zoom' => ['nullable', 'integer', 'min:100', 'max:400'],
            'focal_x' => ['nullable', 'integer', 'min:0', 'max:100'],
            'focal_y' => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_width' => ['nullable', 'integer', 'min:1', 'max:8000'],
            'max_height' => ['nullable', 'integer', 'min:1', 'max:8000'],
            'grayscale' => ['nullable', 'boolean'],
            'brightness' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'contrast' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'quality' => ['nullable', 'integer', 'min:1', 'max:100'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'crop.array' => __('cms_admin_ui.validation.media_edit_crop_invalid'),
            'zoom.min' => __('cms_admin_ui.validation.media_edit_zoom_invalid'),
            'zoom.max' => __('cms_admin_ui.validation.media_edit_zoom_invalid'),
            'max_width.max' => __('cms_admin_ui.validation.media_file_dimensions'),
            'max_height.max' => __('cms_admin_ui.validation.media_file_dimensions'),
        ];
    }
}
