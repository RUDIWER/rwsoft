<?php

namespace App\Http\Requests\Admin\Dev\Translation;

use Illuminate\Foundation\Http\FormRequest;

class SyncPublicTextTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
