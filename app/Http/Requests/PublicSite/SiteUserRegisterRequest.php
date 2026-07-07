<?php

namespace App\Http\Requests\PublicSite;

use App\Models\PublicSite\SiteUser;
use App\Support\PublicSite\PublicAccountProfileFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class SiteUserRegisterRequest extends FormRequest
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
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(SiteUser::class, 'email')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], app(PublicAccountProfileFields::class)->rules('register'));
    }
}
