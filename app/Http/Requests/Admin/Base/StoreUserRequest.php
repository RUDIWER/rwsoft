<?php

namespace App\Http\Requests\Admin\Base;

use App\Models\User;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
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
        $userId = (int) $this->route('id');
        $userModel = new User;
        $usersTable = $userModel->getConnectionName().'.'.$userModel->getTable();

        $activeContentLocales = collect(app(CmsLanguageSettings::class)->activeLocales())
            ->map(static fn (mixed $locale): string => trim((string) $locale))
            ->filter(static fn (string $locale): bool => $locale !== '')
            ->values()
            ->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique($usersTable, 'email')->ignore($userId > 0 ? $userId : null),
            ],
            'password' => [
                $userId > 0 ? 'nullable' : 'required',
                'string',
                'min:8',
                'max:255',
            ],
            'admin_locale' => ['nullable', 'string', Rule::in(config('app.available_locales', [config('app.locale', 'en')]))],
            'allowed_content_locales' => ['nullable', 'array'],
            'allowed_content_locales.*' => ['string', Rule::in($activeContentLocales)],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:acl_roles,id'],
            'database_view_access' => ['nullable', 'boolean'],
            'database_edit_access' => ['nullable', 'boolean'],
            'database_add_access' => ['nullable', 'boolean'],
            'database_delete_access' => ['nullable', 'boolean'],
            'database_export_access' => ['nullable', 'boolean'],
            'database_sql_query_access' => ['nullable', 'boolean'],
            'database_sql_destructive_access' => ['nullable', 'boolean'],
            'database_full_backup_access' => ['nullable', 'boolean'],
        ];
    }
}
