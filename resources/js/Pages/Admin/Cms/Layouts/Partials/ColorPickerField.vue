<template>
    <div class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3">
        <div class="grid gap-1">
            <Label :for="`${idPrefix}-color-select`">
                {{ label }}
            </Label>
            <RwAutoCompleteInput
                :id="`${idPrefix}-color-select`"
                v-model="selectedColorOptionValue"
                :items="combinedColorOptions"
                item-title="label"
                item-value="value"
                :search-fields="['label', 'detail', 'hex_color', 'token']"
                :placeholder="
                    t('layouts.colors.choose_color', 'Choose a color')
                "
                :disabled="disabled || combinedColorOptions.length === 0"
                :messages="{ autocomplete: autocompleteMessages }"
            >
                <template #selection="{ item }">
                    <span class="flex min-w-0 flex-1 items-center gap-2">
                        <span
                            :class="[
                                'mdi shrink-0 text-base leading-none',
                                colorOptionIcon(item),
                                item.type === 'theme'
                                    ? 'text-blue-700'
                                    : 'text-amber-600',
                            ]"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1 truncate">
                            {{ item.label }}
                        </span>
                        <span
                            class="h-4 w-4 shrink-0 rounded-full border border-slate-300"
                            :style="{ backgroundColor: item.color }"
                            aria-hidden="true"
                        />
                    </span>
                </template>

                <template #option="{ item, selected }">
                    <span class="flex min-w-0 flex-1 items-center gap-2">
                        <span
                            :class="[
                                'mdi shrink-0 text-base leading-none',
                                colorOptionIcon(item),
                                item.type === 'theme'
                                    ? 'text-blue-700'
                                    : 'text-amber-600',
                            ]"
                            aria-hidden="true"
                        />
                        <span class="grid min-w-0 flex-1 gap-0.5">
                            <span class="truncate font-medium">
                                {{ item.label }}
                            </span>
                            <span class="truncate text-xs text-slate-500">
                                {{ item.detail }}
                            </span>
                        </span>
                        <span
                            class="h-5 w-5 shrink-0 rounded-full border border-slate-300"
                            :style="{ backgroundColor: item.color }"
                            aria-hidden="true"
                        />
                        <span
                            v-if="selected"
                            class="mdi mdi-check shrink-0 text-base leading-none text-blue-600"
                            aria-hidden="true"
                        />
                    </span>
                </template>

                <template #option-action="{ item }">
                    <Button
                        v-if="item.type === 'favorite'"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 shrink-0 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                        :disabled="disabled || favoriteDeletingId === item.id"
                        :aria-label="
                            t('layouts.colors.delete_favorite', 'Delete color')
                        "
                        :title="
                            t('layouts.colors.delete_favorite', 'Delete color')
                        "
                        @mousedown.stop.prevent
                        @click.stop.prevent="deleteFavorite(item)"
                    >
                        <span
                            class="mdi mdi-delete-outline text-base"
                            aria-hidden="true"
                        />
                    </Button>
                </template>
            </RwAutoCompleteInput>
            <p class="text-xs text-slate-600">
                {{
                    t(
                        'layouts.colors.color_source_help',
                        'Theme colors follow the active public theme. Favorite colors are shared across this tenant.',
                    )
                }}
            </p>
        </div>

        <div
            class="grid gap-2 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-end"
        >
            <div class="grid gap-1">
                <input
                    :id="`${idPrefix}-picker`"
                    type="color"
                    :value="pickerValue"
                    :aria-label="label"
                    class="h-10 w-16 cursor-pointer rounded-md border border-slate-300 bg-white p-1"
                    :disabled="disabled"
                    @input="updateColor($event.target.value)"
                />
            </div>

            <div class="grid gap-1">
                <Label :for="`${idPrefix}-hex`">
                    {{
                        allowCssColor
                            ? t('layouts.colors.css_color', 'CSS color')
                            : t('layouts.colors.hex_color', 'Hex color')
                    }}
                </Label>
                <Input
                    :id="`${idPrefix}-hex`"
                    :model-value="localValue"
                    :placeholder="
                        allowCssColor
                            ? 'var(--rw-public-color-primary)'
                            : '#2563eb'
                    "
                    :maxlength="allowCssColor ? 120 : 7"
                    :disabled="disabled"
                    @update:model-value="updateColor"
                />
            </div>

            <Button
                type="button"
                variant="outline"
                size="icon"
                class="h-9 w-9 border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                :disabled="disabled"
                :aria-label="t('layouts.colors.clear', 'Leegmaken')"
                :title="t('layouts.colors.clear', 'Leegmaken')"
                @click="clearColor"
            >
                <span class="mdi mdi-eraser text-base" aria-hidden="true" />
            </Button>
        </div>

        <div class="grid gap-2">
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-48 flex-1">
                    <Label :for="`${idPrefix}-favorite-name`">
                        {{ t('layouts.colors.favorite_name', 'Favorietnaam') }}
                    </Label>
                    <Input
                        :id="`${idPrefix}-favorite-name`"
                        v-model="favoriteName"
                        class="bg-white"
                        :disabled="disabled"
                        :placeholder="
                            t(
                                'layouts.colors.favorite_name_placeholder',
                                'Bijvoorbeeld merkkleur',
                            )
                        "
                    />
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    class="h-9 w-9 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                    :disabled="disabled || !normalizedValue || favoriteSaving"
                    :aria-label="
                        t(
                            'layouts.colors.save_favorite',
                            'Opslaan als favoriet',
                        )
                    "
                    :title="
                        t(
                            'layouts.colors.save_favorite',
                            'Opslaan als favoriet',
                        )
                    "
                    @click="saveFavorite"
                >
                    <span
                        class="mdi mdi-star-plus-outline text-base text-blue-700"
                        aria-hidden="true"
                    />
                </Button>
            </div>
        </div>

        <p v-if="invalidInput" class="text-xs text-red-600">
            {{
                allowCssColor
                    ? t(
                          'layouts.colors.invalid_css_color',
                          'Use a valid CSS color value.',
                      )
                    : t('layouts.colors.invalid_hex', 'Use a valid hex color.')
            }}
        </p>
    </div>
</template>

<script setup>
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: null },
    tokenValue: { type: String, default: '' },
    tokenOptions: { type: Array, default: () => [] },
    paletteItems: { type: Array, default: () => [] },
    idPrefix: { type: String, required: true },
    label: { type: String, required: true },
    disabled: { type: Boolean, default: false },
    allowCssColor: { type: Boolean, default: false },
});

const emit = defineEmits([
    'update:modelValue',
    'update:tokenValue',
    'update:paletteItems',
]);
const { t } = useAdminTranslations('cms_admin_ui');
const localValue = ref(props.modelValue || '');
const favoriteName = ref('');
const favoriteSaving = ref(false);
const favoriteDeletingId = ref(null);

const themeTokenColorFallbacks = {
    page: '#f8fafc',
    surface: '#ffffff',
    'surface-muted': '#f1f5f9',
    text: '#0f172a',
    muted: '#475569',
    border: 'rgba(15, 23, 42, 0.1)',
    primary: '#2563eb',
    'primary-strong': '#1d4ed8',
    'primary-contrast': '#ffffff',
    success: '#166534',
    'success-bg': '#dcfce7',
    error: '#b91c1c',
    'error-bg': '#fee2e2',
};

const normalizedValue = computed(() => normalizeHexColor(localValue.value));
const normalizedColorValue = computed(() =>
    props.allowCssColor
        ? normalizeCssColor(localValue.value)
        : normalizedValue.value,
);
const invalidInput = computed(
    () => localValue.value !== '' && normalizedColorValue.value === null,
);
const pickerValue = computed(() => normalizedValue.value || '#ffffff');
const autocompleteMessages = computed(() => ({
    no_results: t('autocomplete.no_results', 'No results'),
}));
const combinedColorOptions = computed(() => {
    return [
        ...props.tokenOptions.map((option) => ({
            type: 'theme',
            value: `theme:${option.value}`,
            label: String(option.label || option.value),
            detail: t('layouts.colors.theme_color', 'Theme color'),
            token: String(option.value || ''),
            color: themeTokenColor(String(option.value || '')),
        })),
        ...props.paletteItems.map((item) => ({
            type: 'favorite',
            value: `favorite:${item.id}`,
            label: String(item.name || item.hex_color),
            detail: item.hex_color,
            id: item.id,
            hex_color: item.hex_color,
            color: item.hex_color,
        })),
    ].sort((a, b) =>
        a.label.localeCompare(b.label, undefined, { sensitivity: 'base' }),
    );
});
const selectedColorOptionValue = computed({
    get() {
        if (props.tokenValue) {
            return `theme:${props.tokenValue}`;
        }

        const color = normalizedValue.value;

        if (!color) {
            return '';
        }

        const favorite = props.paletteItems.find(
            (item) => item.hex_color === color,
        );

        return favorite ? `favorite:${favorite.id}` : '';
    },
    set(value) {
        selectCombinedColor(value);
    },
});

function themeTokenColor(token) {
    const fallback = themeTokenColorFallbacks[token] || 'transparent';

    return `var(--rw-public-color-${token}, ${fallback})`;
}

function colorOptionIcon(option) {
    return option?.type === 'theme'
        ? 'mdi-alpha-t-circle'
        : 'mdi-alpha-f-circle';
}

function selectCombinedColor(value) {
    if (!value) {
        clearColor();

        return;
    }

    const option = combinedColorOptions.value.find(
        (item) => item.value === value,
    );

    if (!option) {
        return;
    }

    if (option.type === 'theme') {
        updateToken(option.token);
        return;
    }

    updateColor(option.hex_color);
}

watch(
    () => props.modelValue,
    (value) => {
        localValue.value = value || '';
    },
);

onMounted(() => {
    refreshPalette();
});

function updateColor(value) {
    localValue.value = String(value || '').trim();
    emit('update:tokenValue', '');
    emit('update:modelValue', normalizedColorValue.value);
}

function updateToken(value) {
    emit('update:tokenValue', String(value || ''));

    if (value) {
        localValue.value = '';
        emit('update:modelValue', null);
    }
}

function clearColor() {
    localValue.value = '';
    emit('update:tokenValue', '');
    emit('update:modelValue', null);
}

async function saveFavorite() {
    const hexColor = normalizedValue.value;

    if (!hexColor || favoriteSaving.value) {
        return;
    }

    favoriteSaving.value = true;

    try {
        const response = await window.fetch(
            route('admin.cms.color-palette.store'),
            {
                method: 'POST',
                headers: requestHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({
                    name: favoriteName.value || hexColor,
                    hex_color: hexColor,
                }),
            },
        );

        if (!response.ok) {
            return;
        }

        await refreshPalette(response);
        favoriteName.value = '';
    } finally {
        favoriteSaving.value = false;
    }
}

async function deleteFavorite(item) {
    if (favoriteDeletingId.value !== null) {
        return;
    }

    favoriteDeletingId.value = item.id;

    try {
        const response = await window.fetch(
            route('admin.cms.color-palette.destroy', { item: item.id }),
            {
                method: 'DELETE',
                headers: requestHeaders(),
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            return;
        }

        if (normalizedValue.value === item.hex_color) {
            clearColor();
        }

        await refreshPalette(response);
    } finally {
        favoriteDeletingId.value = null;
    }
}

async function refreshPalette(existingResponse = null) {
    let payload = null;

    if (existingResponse?.ok) {
        payload = await existingResponse.json();
    } else {
        const response = await window.fetch(
            route('admin.cms.color-palette.index'),
            {
                method: 'GET',
                headers: requestHeaders(),
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            return;
        }

        payload = await response.json();
    }

    emit('update:paletteItems', payload.items || []);
}

function requestHeaders() {
    const tokens = csrfTokens();

    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': tokens.meta,
        'X-XSRF-TOKEN': tokens.cookie,
    };
}

function csrfTokens() {
    const metaToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    const cookieToken = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return {
        meta: metaToken || '',
        cookie: cookieToken ? decodeURIComponent(cookieToken) : '',
    };
}

function normalizeHexColor(value) {
    const color = String(value || '').trim();
    const match = color.match(/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/);

    if (!match) {
        return null;
    }

    let hex = match[1].toLowerCase();

    if (hex.length === 3) {
        hex = hex
            .split('')
            .map((character) => character + character)
            .join('');
    }

    return `#${hex}`;
}

function normalizeCssColor(value) {
    const color = String(value || '').trim();

    if (color === '') {
        return null;
    }

    if (color.length > 120 || /[;{}<>]|url\s*\(|expression\s*\(/i.test(color)) {
        return null;
    }

    const hexColor = normalizeHexColor(color);

    if (hexColor) {
        return hexColor;
    }

    if (['transparent', 'currentColor'].includes(color)) {
        return color;
    }

    if (/^var\(--rw-public-[a-z0-9_-]+\)$/.test(color)) {
        return color;
    }

    if (/^(?:rgb|rgba|hsl|hsla)\([0-9.%\s,/+-]+\)$/.test(color)) {
        return color.replace(/\s+/g, ' ');
    }

    return null;
}
</script>
