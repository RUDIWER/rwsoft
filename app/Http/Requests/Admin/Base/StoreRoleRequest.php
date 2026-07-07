<?php

namespace App\Http\Requests\Admin\Base;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
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
        $roleId = (int) $this->route('id');

        return [
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('acl_roles', 'key')->ignore($roleId > 0 ? $roleId : null),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:acl_permissions,id'],
        ];
    }
}
