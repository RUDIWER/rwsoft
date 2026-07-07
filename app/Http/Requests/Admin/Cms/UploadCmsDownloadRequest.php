<?php

namespace App\Http\Requests\Admin\Cms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UploadCmsDownloadRequest extends FormRequest
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
            'file' => [
                'required',
                File::types((array) config('cms_downloads.allowed_extensions', []))
                    ->max((int) config('cms_downloads.max_upload_kb', 20480)),
            ],
            'folder_id' => ['nullable', 'integer', 'exists:cms_download_folders,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'access_mode' => ['nullable', 'string', Rule::in(['inherit', 'public', 'authenticated', 'restricted', 'password'])],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
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
            'file.required' => __('cms_admin_ui.validation.download_file_required'),
            'file.uploaded' => __('cms_admin_ui.validation.download_file_uploaded'),
            'file.mimes' => __('cms_admin_ui.validation.download_file_mimes'),
            'file.max' => __('cms_admin_ui.validation.download_file_max'),
            'folder_id.exists' => __('cms_admin_ui.validation.download_folder_exists'),
        ];
    }
}
