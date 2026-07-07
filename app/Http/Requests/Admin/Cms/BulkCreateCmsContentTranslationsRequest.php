<?php

namespace App\Http\Requests\Admin\Cms;

use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkCreateCmsContentTranslationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale(
            $this->user(),
            (string) $this->input('target_locale'),
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
            'target_locale' => ['required', 'string', Rule::in(app(CmsLanguageSettings::class)->activeLocales())],
            'limit' => ['required', 'integer', 'min:1', 'max:50'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.type' => ['required', 'string', Rule::in(['page', 'post', 'category', 'tag', 'form', 'menu_item'])],
            'items.*.source_id' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'type' => trim((string) ($item['type'] ?? '')),
                'source_id' => (int) ($item['source_id'] ?? 0),
            ])
            ->values()
            ->all();

        $this->merge([
            'target_locale' => trim((string) $this->input('target_locale', '')),
            'limit' => (int) $this->input('limit', 10),
            'items' => $items,
        ]);
    }
}
