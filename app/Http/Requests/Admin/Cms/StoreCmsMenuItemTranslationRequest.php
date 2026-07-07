<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsLocalePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsMenuItemTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CmsLocalePermission::class)->canEditLocale($this->user(), (string) $this->input('target_locale'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $activeLocales = app(CmsLanguageSettings::class)->activeLocales();

        return [
            'target_locale' => ['required', 'string', Rule::in($activeLocales)],
            'use_ai' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $menuId = (int) $this->route('menu');
                $itemId = (int) $this->route('item');

                if ($menuId <= 0 || $itemId <= 0) {
                    return;
                }

                $menu = CmsMenu::query()->find($menuId);

                if (! $menu instanceof CmsMenu) {
                    return;
                }

                $item = $menu->items()->find($itemId);

                if (! $item instanceof CmsMenuItem || blank($item->translation_key)) {
                    return;
                }

                $exists = CmsMenuItem::query()
                    ->where('cms_menu_id', $item->cms_menu_id)
                    ->where('translation_key', $item->translation_key)
                    ->where('locale', $this->input('target_locale'))
                    ->whereKeyNot($item->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('target_locale', 'Er bestaat al een menu-itemvertaling voor deze taal.');
                }
            },
        ];
    }
}
