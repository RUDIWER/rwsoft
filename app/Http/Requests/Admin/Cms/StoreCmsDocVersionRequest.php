<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsDocCollection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsDocVersionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = (int) $this->route('version');
        $collectionId = (int) $this->input('cms_doc_collection_id');

        return [
            'cms_doc_collection_id' => ['required', 'integer', Rule::exists((new CmsDocCollection)->getTable(), 'id')],
            'label' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('cms_doc_versions', 'slug')
                    ->where(fn ($query) => $query->where('cms_doc_collection_id', $collectionId))
                    ->ignore($id > 0 ? $id : null),
            ],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
