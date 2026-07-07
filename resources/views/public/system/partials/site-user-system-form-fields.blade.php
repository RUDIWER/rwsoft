@php
    use App\Models\Cms\CmsForm;
    use Illuminate\Support\Arr;
    use Illuminate\Support\Str;

    $systemKey = (string) ($systemKey ?? '');
    $locale = (string) ($locale ?? ($site['current_locale'] ?? app()->getLocale()));
    $idPrefix = (string) ($idPrefix ?? Str::slug($systemKey));
    $accountAction = $accountAction ?? null;
    $values = (array) ($values ?? []);
    $profileFieldValues = (array) ($profileFieldValues ?? []);
    $showErrors = $accountAction === null || old('_account_action', $accountAction) === $accountAction;
    $systemForm = CmsForm::query()
        ->with(['fields' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
        ->where('form_kind', 'system')
        ->where('system_key', $systemKey)
        ->where('locale', $locale)
        ->where('is_active', true)
        ->first();

    $fieldInputName = static function (string $key): string {
        if (Str::startsWith($key, 'profile_')) {
            return 'profile_fields['.Str::after($key, 'profile_').']';
        }

        return $key;
    };

    $fieldErrorKey = static function (string $key): string {
        if (Str::startsWith($key, 'profile_')) {
            return 'profile_fields.'.Str::after($key, 'profile_');
        }

        return $key;
    };

    $fieldValue = static function (string $key) use ($values, $profileFieldValues) {
        if (Str::startsWith($key, 'profile_')) {
            $profileKey = Str::after($key, 'profile_');

            return old('profile_fields.'.$profileKey, $profileFieldValues[$profileKey] ?? '');
        }

        return old($key, $values[$key] ?? '');
    };
@endphp

@if ($systemForm instanceof CmsForm)
    @foreach ($systemForm->fields as $field)
        @php
            $key = (string) $field->translation_key;
            $fieldId = $idPrefix.'-'.Str::slug($key);
            $inputName = $fieldInputName($key);
            $errorKey = $fieldErrorKey($key);
            $settings = (array) ($field->settings ?? []);
            $inputType = (string) Arr::get($settings, 'input_type', $field->type === 'email' ? 'email' : 'text');
            $currentValue = $fieldValue($key);
        @endphp

        @if ($field->type === 'checkbox')
            <label class="rw-public-form__checkbox">
                <input
                    id="{{ $fieldId }}"
                    type="checkbox"
                    name="{{ $inputName }}"
                    value="1"
                    @checked((bool) old($errorKey, $currentValue))
                >
                <span>{{ $field->placeholder ?: $field->label }}</span>
            </label>
        @else
            <div class="rw-public-form__field @if (($field->width ?: 'full') === 'half') rw-public-form__field--half @endif">
                <label class="rw-public-form__label" for="{{ $fieldId }}">
                    {{ $field->label }}
                    @if ($field->is_required)
                        <span class="rw-public-form__required">*</span>
                    @endif
                </label>

                @if ($field->type === 'textarea')
                    <textarea
                        id="{{ $fieldId }}"
                        class="rw-public-form__textarea"
                        name="{{ $inputName }}"
                        placeholder="{{ $field->placeholder }}"
                        @required($field->is_required)
                    >{{ $currentValue }}</textarea>
                @elseif ($field->type === 'select')
                    <select
                        id="{{ $fieldId }}"
                        class="rw-public-form__select"
                        name="{{ $inputName }}"
                        @required($field->is_required)
                    >
                        <option value="">{{ public_text('form.select_placeholder', 'Choose an option', $locale) }}</option>
                        @foreach ((array) ($field->options ?? []) as $option)
                            <option value="{{ $option['key'] ?? '' }}" @selected((string) $currentValue === (string) ($option['key'] ?? ''))>
                                {{ $option['label'] ?? '' }}
                            </option>
                        @endforeach
                    </select>
                @else
                    <input
                        id="{{ $fieldId }}"
                        class="rw-public-form__input"
                        type="{{ $inputType }}"
                        name="{{ $inputName }}"
                        value="{{ $inputType === 'password' ? '' : $currentValue }}"
                        placeholder="{{ $field->placeholder }}"
                        @if ($key === 'email') autocomplete="username" @endif
                        @if ($key === 'password') autocomplete="{{ $systemKey === 'site_user_login' ? 'current-password' : 'new-password' }}" @endif
                        @if ($key === 'password_confirmation') autocomplete="new-password" @endif
                        @required($field->is_required)
                    >
                @endif

                @if (! empty($field->help_text))
                    <div class="rw-public-form__help">{{ $field->help_text }}</div>
                @endif

                @if ($showErrors)
                    @error($errorKey)
                        <p class="rw-public-form__error">{{ $message }}</p>
                    @enderror
                @endif
            </div>
        @endif
    @endforeach
@endif
