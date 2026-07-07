<?php

namespace App\Http\Requests\Admin\Cms;

use App\Support\Cms\CmsMediaSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UploadCmsMediaRequest extends FormRequest
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
        $settings = app(CmsMediaSettings::class);

        return [
            'file' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max($settings->maxImageUploadKb())
                    ->dimensions(
                        Rule::dimensions()
                            ->maxWidth((int) config('cms_media.max_width', 8000))
                            ->maxHeight((int) config('cms_media.max_height', 8000)),
                    ),
            ],
            'folder_id' => ['nullable', 'integer', 'exists:cms_media_folders,id'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'uploaded_from' => ['nullable', 'string', 'max:80'],
            'context_type' => ['nullable', 'string', Rule::in(['page', 'post', 'category', 'tag', 'cms_settings'])],
            'context_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('cms_admin_ui.validation.media_file_required'),
            'file.file' => __('cms_admin_ui.validation.media_file_image'),
            'file.uploaded' => __('cms_admin_ui.validation.media_file_uploaded'),
            'file.image' => __('cms_admin_ui.validation.media_file_image'),
            'file.mimes' => __('cms_admin_ui.validation.media_file_mimes'),
            'file.max' => __('cms_admin_ui.validation.media_file_max'),
            'file.dimensions' => __('cms_admin_ui.validation.media_file_dimensions'),
            'folder_id.exists' => __('cms_admin_ui.validation.media_folder_exists'),
        ];
    }
}
