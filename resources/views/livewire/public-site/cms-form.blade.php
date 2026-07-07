<form class="rw-public-form" wire:submit="save">
    <div class="rw-public-form__intro">
        <h2 class="rw-public-form__title">{{ $form['title'] ?? '' }}</h2>
        @if (! empty($form['description']))
            <p class="rw-public-form__description">{{ $form['description'] }}</p>
        @endif
    </div>

    @if ($submitted)
        <div class="rw-public-form__success" role="status">
            {{ $form['success_message'] ?? public_text('form.success_fallback', 'Thank you. Your form has been submitted.', $locale) }}
        </div>
    @endif

    @error('form.system')
        <div class="rw-public-form__error" role="alert">{{ $message }}</div>
    @enderror

    <input class="rw-public-form__honeypot" type="text" wire:model="company" tabindex="-1" autocomplete="off">

    <div class="rw-public-form__grid">
        @foreach ($fields as $field)
            @php
                $key = $field['translation_key'];
                $fieldId = 'cms-form-'.$formTranslationKey.'-'.$key;
                $errorKey = 'values.'.$key;
            @endphp

            <div class="rw-public-form__field @if (($field['width'] ?? 'full') === 'half') rw-public-form__field--half @endif">
                <label class="rw-public-form__label" for="{{ $fieldId }}">
                    {{ $field['label'] }}
                    @if (! empty($field['is_required']))
                        <span class="rw-public-form__required">*</span>
                    @endif
                </label>

                @if ($field['type'] === 'textarea')
                    <textarea
                        id="{{ $fieldId }}"
                        class="rw-public-form__textarea"
                        wire:model.live.blur="values.{{ $key }}"
                        placeholder="{{ $field['placeholder'] }}"
                    ></textarea>
                @elseif ($field['type'] === 'select')
                    <select id="{{ $fieldId }}" class="rw-public-form__select" wire:model.live="values.{{ $key }}">
                        <option value="">{{ public_text('form.select_placeholder', 'Choose an option', $locale) }}</option>
                        @foreach (($field['options'] ?? []) as $option)
                            <option value="{{ $option['key'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                @elseif ($field['type'] === 'combobox')
                    <input
                        id="{{ $fieldId }}"
                        class="rw-public-form__input"
                        type="text"
                        wire:model.live.blur="values.{{ $key }}"
                        placeholder="{{ $field['placeholder'] }}"
                        list="{{ $fieldId }}-options"
                    >
                    <datalist id="{{ $fieldId }}-options">
                        @foreach (($field['options'] ?? []) as $option)
                            <option value="{{ $option['label'] }}"></option>
                        @endforeach
                    </datalist>
                @elseif ($field['type'] === 'checkbox')
                    <label class="rw-public-form__checkbox">
                        <input id="{{ $fieldId }}" type="checkbox" wire:model.live="values.{{ $key }}">
                        <span>{{ $field['placeholder'] ?: $field['label'] }}</span>
                    </label>
                @elseif ($field['type'] === 'number')
                    <input
                        id="{{ $fieldId }}"
                        class="rw-public-form__input rw-public-form__input--number"
                        type="number"
                        inputmode="decimal"
                        wire:model.live.blur="values.{{ $key }}"
                        placeholder="{{ $field['placeholder'] }}"
                    >
                @elseif ($field['type'] === 'date')
                    <input
                        id="{{ $fieldId }}"
                        class="rw-public-form__input rw-public-form__input--date"
                        type="date"
                        wire:model.live.blur="values.{{ $key }}"
                        placeholder="{{ $field['placeholder'] }}"
                    >
                @elseif ($field['type'] === 'time')
                    <input
                        id="{{ $fieldId }}"
                        class="rw-public-form__input rw-public-form__input--time"
                        type="time"
                        wire:model.live.blur="values.{{ $key }}"
                        placeholder="{{ $field['placeholder'] }}"
                    >
                @else
                    <input
                        id="{{ $fieldId }}"
                        class="rw-public-form__input"
                        type="{{ $field['type'] === 'email' ? 'email' : 'text' }}"
                        wire:model.live.blur="values.{{ $key }}"
                        placeholder="{{ $field['placeholder'] }}"
                    >
                @endif

                @if (! empty($field['help_text']) && $field['type'] !== 'checkbox')
                    <div class="rw-public-form__help">{{ $field['help_text'] }}</div>
                @endif

                @error($errorKey)
                    <div class="rw-public-form__error">{{ $message }}</div>
                @enderror
            </div>
        @endforeach
    </div>

    <div class="rw-public-form__actions">
        <button class="rw-public-form__submit" type="submit" wire:loading.attr="disabled">
            <span wire:loading.remove>{{ $form['submit_button_label'] ?? public_text('form.submit_fallback', 'Submit', $locale) }}</span>
            <span wire:loading>{{ public_text('form.submit_loading', 'Submitting...', $locale) }}</span>
        </button>
    </div>
</form>
