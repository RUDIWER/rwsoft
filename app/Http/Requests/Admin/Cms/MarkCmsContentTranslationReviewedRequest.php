<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsTag;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkCmsContentTranslationReviewedRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $record = $this->record();

        if (! $record instanceof Model) {
            return false;
        }

        return app(CmsLocalePermission::class)->canEditLocale(
            $this->user(),
            (string) $record->getAttribute('locale'),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['page', 'post', 'category', 'tag', 'form', 'menu_item'])],
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => trim((string) $this->input('type', '')),
            'id' => (int) $this->input('id', 0),
        ]);
    }

    public function record(): ?Model
    {
        $type = trim((string) $this->input('type', ''));
        $id = (int) $this->input('id', 0);

        if ($id < 1) {
            return null;
        }

        return match ($type) {
            'page' => CmsPage::query()->find($id),
            'post' => CmsPost::query()->find($id),
            'category' => CmsCategory::query()->find($id),
            'tag' => CmsTag::query()->find($id),
            'form' => CmsForm::query()->find($id),
            'menu_item' => CmsMenuItem::query()->find($id),
            default => null,
        };
    }
}
