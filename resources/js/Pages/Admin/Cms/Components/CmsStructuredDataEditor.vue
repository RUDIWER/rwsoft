<template>
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_280px]">
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label :for="`${idPrefix}_schema_type`">{{
                    t(
                        'components.structured_data.schema_type',
                        'Automatisch JSON-LD type',
                    )
                }}</Label>
                <select
                    :id="`${idPrefix}_schema_type`"
                    :value="schemaType"
                    class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    @change="$emit('update:schemaType', $event.target.value)"
                >
                    <option
                        v-for="option in schemaTypeOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <p class="text-xs text-slate-500">
                    {{
                        t(
                            'components.structured_data.schema_help',
                            'Dit wijzigt alleen het Schema.org type in de automatische JSON-LD. De pagina zelf verandert niet.',
                        )
                    }}
                </p>
            </div>

            <div class="grid gap-2">
                <Label>{{
                    t(
                        'components.structured_data.automatic_json',
                        'Automatisch gegenereerde JSON-LD',
                    )
                }}</Label>
                <RwCodeEditor
                    :model-value="effectiveAutomaticJson"
                    language="json"
                    height="260px"
                    theme="graphite"
                    readonly
                    :line-wrapping="true"
                />
            </div>

            <div class="grid gap-2">
                <Label :for="`${idPrefix}_extra`">{{
                    t('components.structured_data.extra_json', 'Extra JSON-LD')
                }}</Label>
                <RwCodeEditor
                    :id="`${idPrefix}_extra`"
                    :model-value="extraJson"
                    language="json"
                    height="280px"
                    theme="graphite"
                    placeholder='{ "@type": "FAQPage" }'
                    :line-wrapping="true"
                    @update:model-value="$emit('update:extraJson', $event)"
                />
                <p class="text-xs text-slate-500">
                    {{
                        t(
                            'components.structured_data.extra_help',
                            'Voeg alleen extra JSON toe, zonder script-tag. Gebruik placeholders uit de rechterkolom.',
                        )
                    }}
                </p>
                <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
            </div>

            <div class="grid gap-2">
                <Label>{{
                    t(
                        'components.structured_data.final_preview',
                        'Finale preview',
                    )
                }}</Label>
                <RwCodeEditor
                    :model-value="finalPreview"
                    language="json"
                    height="320px"
                    theme="graphite"
                    readonly
                    :line-wrapping="true"
                />
            </div>
        </div>

        <aside
            class="grid content-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4"
        >
            <div>
                <h3 class="text-sm font-semibold text-slate-900">
                    {{
                        t(
                            'components.structured_data.available_fields',
                            'Beschikbare velden',
                        )
                    }}
                </h3>
                <p class="mt-1 text-xs text-slate-500">
                    {{
                        t(
                            'components.structured_data.placeholder_help',
                            'Klik om een placeholder toe te voegen aan de extra JSON-LD editor.',
                        )
                    }}
                </p>
            </div>
            <button
                v-for="placeholder in placeholders"
                :key="placeholder.key"
                type="button"
                class="rounded-md border border-slate-200 bg-white px-3 py-2 text-left text-xs hover:border-blue-300 hover:bg-blue-50"
                @click="insertPlaceholder(placeholder.key)"
            >
                <span class="block font-mono text-blue-700">{{
                    placeholderText(placeholder.key)
                }}</span>
                <span class="mt-1 block text-slate-500">{{
                    placeholder.label
                }}</span>
            </button>
        </aside>
    </div>
</template>

<script setup>
import RwCodeEditor from '@/Components/RwCodeEditor.vue';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { computed } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');

const props = defineProps({
    idPrefix: { type: String, required: true },
    automaticJson: { type: String, default: '{}' },
    schemaType: { type: String, default: 'auto' },
    schemaTypeOptions: { type: Array, default: () => [] },
    extraJson: { type: String, default: '' },
    placeholders: { type: Array, default: () => [] },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:schemaType', 'update:extraJson']);

const effectiveAutomaticJson = computed(() => {
    const automatic = parseJson(props.automaticJson) ?? {};

    if (props.schemaType === 'None') {
        return prettyJson({});
    }

    if (!props.schemaType || props.schemaType === 'auto') {
        return prettyJson(automatic);
    }

    const graph = Array.isArray(automatic?.['@graph'])
        ? automatic['@graph']
        : [];
    const editableTypes = props.schemaTypeOptions
        .map((option) => option.value)
        .filter((value) => !['auto', 'None'].includes(value));
    const next = cloneJson(automatic);
    const nextGraph = Array.isArray(next?.['@graph']) ? next['@graph'] : [];
    const targetIndex = graph.findIndex((node) =>
        editableTypes.includes(node?.['@type']),
    );

    if (targetIndex >= 0 && nextGraph[targetIndex]) {
        nextGraph[targetIndex]['@type'] = props.schemaType;
    }

    return prettyJson(next);
});

const finalPreview = computed(() => {
    if (props.schemaType === 'None') {
        return prettyJson({});
    }

    const automatic = parseJson(effectiveAutomaticJson.value) ?? {};
    const extra = parseJson(props.extraJson);

    if (!extra) {
        return prettyJson(automatic);
    }

    const graph = Array.isArray(automatic?.['@graph'])
        ? [...automatic['@graph']]
        : [];
    const extraItems = Array.isArray(extra) ? extra : [extra];

    return prettyJson({
        '@context': automatic?.['@context'] ?? 'https://schema.org',
        '@graph': [...graph, ...extraItems],
    });
});

function parseJson(value) {
    if (!value || !String(value).trim()) {
        return null;
    }

    try {
        return JSON.parse(value);
    } catch {
        return null;
    }
}

function prettyJson(value) {
    return JSON.stringify(value, null, 2);
}

function cloneJson(value) {
    return JSON.parse(JSON.stringify(value));
}

function placeholderText(key) {
    return `{{ ${key} }}`;
}

function insertPlaceholder(key) {
    const current = String(props.extraJson || '');
    const placeholder = placeholderText(key);
    emit(
        'update:extraJson',
        current ? `${current}${placeholder}` : placeholder,
    );
}
</script>
