<?php

namespace App\Http\Requests\Admin\Base;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
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
        $permissionId = (int) $this->route('id');

        return [
            'route_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('acl_permissions', 'route_name')->ignore($permissionId > 0 ? $permissionId : null),
            ],
            'description' => ['required', 'string', 'max:255'],
            'module_id' => ['nullable', 'integer', 'exists:acl_permission_modules,id'],
            'action_id' => ['nullable', 'integer', 'exists:acl_permission_actions,id'],
            'type_id' => ['nullable', 'integer', 'exists:acl_permission_types,id'],
            'query_id' => ['nullable', 'integer'],
            'menu' => ['nullable', 'boolean'],
            'url' => ['nullable', 'string', 'max:255'],
        ];
    }
}
