<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsDocPage;
use App\Models\Cms\CmsDocVersion;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsDocPageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale($this->user(), (string) $this->input('locale'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = (int) $this->route('page');
        $versionId = (int) $this->input('cms_doc_version_id');
        $locale = $this->string('locale')->toString();

        return [
            'cms_doc_version_id' => ['required', 'integer', Rule::exists((new CmsDocVersion)->getTable(), 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists((new CmsDocPage)->getTable(), 'id'), Rule::notIn([$id])],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii'],
            'path' => [
                'required',
                'string',
                'max:500',
                'regex:/^[A-Za-z0-9\-\/]+$/',
                Rule::unique('cms_doc_pages', 'path')
                    ->where(fn ($query) => $query->where('cms_doc_version_id', $versionId)->where('locale', $locale))
                    ->ignore($id > 0 ? $id : null),
            ],
            'locale' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2}([_-][A-Z]{2})?$/', Rule::in(app(CmsLanguageSettings::class)->activeLocales())],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'body_format' => ['required', 'string', Rule::in(['markdown'])],
            'body' => ['nullable', 'string', 'max:500000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'noindex' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $pageId = (int) $this->route('page');
                $parentId = (int) $this->input('parent_id');

                if ($pageId > 0 && $parentId > 0 && in_array($parentId, $this->descendantIds($pageId), true)) {
                    $validator->errors()->add('parent_id', __('cms_admin_ui.validation.page_parent_descendant'));
                }

                if ($parentId > 0 && CmsDocPage::query()
                    ->whereKey($parentId)
                    ->where(function ($query): void {
                        $query->where('cms_doc_version_id', '!=', (int) $this->input('cms_doc_version_id'))
                            ->orWhere('locale', '!=', $this->string('locale')->toString());
                    })
                    ->exists()) {
                    $validator->errors()->add('parent_id', __('cms_admin_ui.validation.doc_page_parent_scope'));
                }
            },
        ];
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(int $pageId): array
    {
        $ids = [];
        $children = CmsDocPage::query()->where('parent_id', $pageId)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ($children as $childId) {
            $ids[] = $childId;
            array_push($ids, ...$this->descendantIds($childId));
        }

        return $ids;
    }
}
