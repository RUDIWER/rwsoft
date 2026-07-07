<template>
    <div class="grid gap-2">
        <Label :for="inputId">{{ label }}</Label>

        <div class="flex flex-wrap gap-1">
            <button
                v-for="language in languages"
                :key="language.locale"
                type="button"
                class="rounded-full border px-2 py-1 text-xs font-medium transition"
                :class="chipClass(language.locale)"
                @click="activeLocale = language.locale"
            >
                {{ language.locale.toUpperCase() }}
            </button>
        </div>

        <textarea
            v-if="type === 'textarea'"
            :key="`${activeLocale}:${field}:textarea`"
            :id="inputId"
            :value="currentValue"
            :rows="rows"
            class="min-h-24 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            @input="updateValue($event.target.value)"
        ></textarea>
        <Input
            v-else
            :key="`${activeLocale}:${field}:input`"
            :id="inputId"
            :model-value="currentValue"
            :required="activeLocale === defaultLocale && requiredDefault"
            @update:model-value="updateValue"
        />

        <p class="text-xs text-slate-500">
            {{ commonT('locale.active_language', 'Active language') }}:
            {{ activeLanguageLabel }}
        </p>
        <p v-if="error" class="text-sm text-red-600">
            {{ error }}
        </p>
    </div>
</template>

<script setup>
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
    languages: {
        type: Array,
        required: true,
    },
    defaultLocale: {
        type: String,
        required: true,
    },
    field: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    inputId: {
        type: String,
        required: true,
    },
    type: {
        type: String,
        default: 'text',
    },
    rows: {
        type: Number,
        default: 4,
    },
    requiredDefault: {
        type: Boolean,
        default: true,
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);
const { t: commonT } = useAdminTranslations('admin_common_ui');
const activeLocale = ref(props.defaultLocale);

const currentValue = computed(() =>
    String(props.modelValue?.[activeLocale.value]?.[props.field] ?? ''),
);
const activeLanguageLabel = computed(() => {
    const language = props.languages.find(
        (item) => item.locale === activeLocale.value,
    );

    return language
        ? `${language.native_name || language.name} (${language.locale})`
        : activeLocale.value;
});

watch(
    () => props.defaultLocale,
    (locale) => {
        if (!activeLocale.value || !localeExists(activeLocale.value)) {
            activeLocale.value = locale;
        }
    },
);

watch(
    () => props.languages,
    () => {
        ensureEntries();

        if (!localeExists(activeLocale.value)) {
            activeLocale.value =
                props.defaultLocale || props.languages[0]?.locale || '';
        }
    },
    { deep: true },
);

watch(
    () => props.modelValue,
    () => ensureEntries(),
    { deep: true },
);

onMounted(() => {
    activeLocale.value = localeExists(props.defaultLocale)
        ? props.defaultLocale
        : props.languages[0]?.locale || props.defaultLocale;

    ensureEntries();
});

function updateValue(value) {
    emit('update:modelValue', {
        ...props.modelValue,
        [activeLocale.value]: {
            ...(props.modelValue?.[activeLocale.value] ?? {}),
            [props.field]: value,
        },
    });
}

function ensureEntries() {
    const nextValue = { ...(props.modelValue ?? {}) };
    let changed = false;

    props.languages.forEach((language) => {
        if (!nextValue[language.locale]) {
            nextValue[language.locale] = {};
            changed = true;
        }
    });

    if (changed) {
        emit('update:modelValue', nextValue);
    }
}

function localeExists(locale) {
    return props.languages.some((language) => language.locale === locale);
}

function chipClass(locale) {
    const isActive = activeLocale.value === locale;
    const isFilled =
        String(props.modelValue?.[locale]?.[props.field] ?? '').trim() !== '';

    if (isActive) {
        return isFilled
            ? 'border-green-700 bg-green-600 text-white'
            : 'border-red-700 bg-red-600 text-white';
    }

    return isFilled
        ? 'border-green-200 bg-green-50 text-green-800 hover:bg-green-100'
        : 'border-red-200 bg-red-50 text-red-800 hover:bg-red-100';
}
</script>
