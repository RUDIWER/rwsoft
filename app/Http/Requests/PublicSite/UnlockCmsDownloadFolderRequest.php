<?php

namespace App\Http\Requests\PublicSite;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UnlockCmsDownloadFolderRequest extends FormRequest
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
            'password' => ['required', 'string', 'max:255'],
            'redirect_to' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
