<template>
    <section class="grid gap-4">
        <div class="grid gap-1">
            <h3 class="text-sm font-semibold text-slate-900">
                {{ t('components.block_editor.slots_title', 'Block slots') }}
            </h3>
            <p class="text-sm text-slate-500">
                {{
                    t(
                        'components.block_editor.slots_description',
                        'Add allowed child blocks to the explicit slots of this block.',
                    )
                }}
            </p>
        </div>

        <div
            v-for="slot in slotDefinitions"
            :key="slot.key"
            class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="grid gap-1">
                    <h4 class="text-sm font-semibold text-slate-900">
                        {{ slot.label || slot.key }}
                    </h4>
                    <p class="text-xs text-slate-600">
                        {{ slotSummary(slot) }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    class="gap-2 border-blue-200 bg-white text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                    :disabled="
                        slotIsFull(slot) || allowedOptions(slot).length === 0
                    "
                    @click="addPlacement(slot)"
                >
                    <span
                        class="mdi mdi-plus-circle text-base"
                        aria-hidden="true"
                    />
                    {{
                        t('components.block_editor.add_slot_block', 'Add block')
                    }}
                </Button>
            </div>

            <div
                v-if="allowedOptions(slot).length === 0"
                class="rounded border border-orange-200 bg-orange-50 p-3 text-sm text-orange-800"
            >
                {{
                    t(
                        'components.block_editor.slot_no_allowed_blocks',
                        'No allowed child blocks are available for this slot.',
                    )
                }}
            </div>

            <div
                v-if="slotPlacements(slot).length === 0"
                class="rounded border border-dashed border-slate-300 bg-white p-3 text-sm text-slate-500"
            >
                {{
                    t(
                        'components.block_editor.slot_empty',
                        'No child blocks have been added yet.',
                    )
                }}
            </div>

            <div v-else class="grid gap-3">
                <div
                    v-for="(placement, placementIndex) in slotPlacements(slot)"
                    :key="placement.uid"
                    class="grid gap-3 rounded-md border border-slate-200 bg-white p-3"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div class="grid gap-1">
                            <span class="text-sm font-semibold text-slate-900">
                                {{ blockLabel(placement.block) }} #{{
                                    placementIndex + 1
                                }}
                            </span>
                            <span class="font-mono text-xs text-slate-500">
                                {{ blockKey(placement.block) || '-' }}
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="h-8 w-8 border-slate-300 text-slate-700 shadow-none hover:bg-slate-50 hover:text-slate-900"
                                :title="
                                    t(
                                        'components.block_editor.placement_settings',
                                        'Placement settings',
                                    )
                                "
                                :aria-label="
                                    t(
                                        'components.block_editor.placement_settings',
                                        'Placement settings',
                                    )
                                "
                                @click="requestPlacementSettings(placement)"
                            >
                                <span
                                    class="mdi mdi-cog text-base text-orange-600"
                                    aria-hidden="true"
                                />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="h-8 w-8 border-slate-300 shadow-none"
                                :disabled="placementIndex === 0"
                                :title="t('components.block_editor.up', 'Up')"
                                :aria-label="
                                    t('components.block_editor.up', 'Up')
                                "
                                @click="movePlacement(slot, placementIndex, -1)"
                            >
                                <span
                                    class="mdi mdi-chevron-up text-xl"
                                    aria-hidden="true"
                                />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="h-8 w-8 border-slate-300 shadow-none"
                                :disabled="
                                    placementIndex ===
                                    slotPlacements(slot).length - 1
                                "
                                :title="
                                    t('components.block_editor.down', 'Down')
                                "
                                :aria-label="
                                    t('components.block_editor.down', 'Down')
                                "
                                @click="movePlacement(slot, placementIndex, 1)"
                            >
                                <span
                                    class="mdi mdi-chevron-down text-xl"
                                    aria-hidden="true"
                                />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="h-8 w-8 border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                                :title="t('common.actions.delete', 'Delete')"
                                :aria-label="
                                    t('common.actions.delete', 'Delete')
                                "
                                @click="removePlacement(slot, placementIndex)"
                            >
                                <span
                                    class="mdi mdi-delete text-base"
                                    aria-hidden="true"
                                />
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>
                                {{
                                    t(
                                        'components.block_editor.slot_child_block',
                                        'Child block',
                                    )
                                }}
                            </Label>
                            <RwAutoCompleteInput
                                :model-value="
                                    placement.block.cms_placeable_block_id
                                "
                                :items="allowedOptions(slot)"
                                item-title="label"
                                item-value="value"
                                :search-fields="['label', 'key']"
                                @update:model-value="
                                    updatePlacementBlock(
                                        slot,
                                        placementIndex,
                                        $event,
                                    )
                                "
                            />
                        </div>
                        <label
                            class="mt-7 flex items-center gap-2 text-sm text-slate-700"
                        >
                            <input
                                v-model="placement.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                @change="emitSlots"
                            />
                            {{ t('common.columns.active', 'Active') }}
                        </label>
                    </div>

                    <div
                        v-if="editorFields(placement.block).length > 0"
                        class="grid gap-3 md:grid-cols-2"
                    >
                        <div
                            v-for="field in editorFields(placement.block)"
                            :key="`${placement.uid}-${field.name}`"
                            class="grid gap-2"
                            :class="
                                field.type === 'textarea' ? 'md:col-span-2' : ''
                            "
                        >
                            <Label>{{ fieldLabel(field) }}</Label>
                            <CmsRichTextEditor
                                v-if="field.type === 'rich_text'"
                                v-model="placement.block[field.name]"
                                :media-options="mediaOptions"
                                :media-folders="mediaFolders"
                                :placeholder="fieldPlaceholder(field)"
                                @update:model-value="emitSlots"
                                @blur="emitSlots"
                            />
                            <RwCodeEditor
                                v-else-if="field.type === 'markdown'"
                                v-model="placement.block[field.name]"
                                language="markdown"
                                theme="graphite"
                                height="240px"
                                :line-wrapping="true"
                                :placeholder="fieldPlaceholder(field)"
                                @update:model-value="emitSlots"
                            />
                            <textarea
                                v-else-if="field.type === 'textarea'"
                                v-model="placement.block[field.name]"
                                rows="3"
                                class="min-h-20 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                :placeholder="fieldPlaceholder(field)"
                                @input="emitSlots"
                            ></textarea>
                            <label
                                v-else-if="field.type === 'checkbox'"
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="placement.block[field.name]"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    @change="emitSlots"
                                />
                                {{ fieldLabel(field) }}
                            </label>
                            <RwAutoCompleteInput
                                v-else-if="field.type === 'select'"
                                :model-value="placement.block[field.name]"
                                :items="field.options || []"
                                item-title="label"
                                item-value="value"
                                :search-fields="['label', 'value']"
                                @update:model-value="
                                    updateBlockField(
                                        placement,
                                        field.name,
                                        $event,
                                    )
                                "
                            />
                            <RwAutoCompleteInput
                                v-else-if="field.type === 'download_select'"
                                :model-value="placement.block[field.name]"
                                :items="downloadOptions"
                                item-title="title"
                                item-value="id"
                                :search-fields="[
                                    'title',
                                    'filename',
                                    'original_filename',
                                ]"
                                @update:model-value="
                                    updateBlockField(
                                        placement,
                                        field.name,
                                        $event,
                                    )
                                "
                            />
                            <RwAutoCompleteInput
                                v-else-if="field.type === 'download_list'"
                                :model-value="placement.block[field.name]"
                                :items="downloadOptions"
                                item-title="title"
                                item-value="id"
                                :search-fields="[
                                    'title',
                                    'filename',
                                    'original_filename',
                                ]"
                                :multiple="true"
                                :selection-chips="true"
                                @update:model-value="
                                    updateBlockField(
                                        placement,
                                        field.name,
                                        $event,
                                    )
                                "
                            />
                            <RwAutoCompleteInput
                                v-else-if="
                                    field.type === 'download_folder_select'
                                "
                                :model-value="placement.block[field.name]"
                                :items="downloadFolders"
                                item-title="name"
                                item-value="id"
                                :search-fields="['name']"
                                @update:model-value="
                                    updateBlockField(
                                        placement,
                                        field.name,
                                        $event,
                                    )
                                "
                            />
                            <RwAutoCompleteInput
                                v-else-if="
                                    field.type === 'download_folder_list'
                                "
                                :model-value="placement.block[field.name]"
                                :items="downloadFolders"
                                item-title="name"
                                item-value="id"
                                :search-fields="['name']"
                                :multiple="true"
                                :selection-chips="true"
                                @update:model-value="
                                    updateBlockField(
                                        placement,
                                        field.name,
                                        $event,
                                    )
                                "
                            />
                            <Input
                                v-else
                                v-model="placement.block[field.name]"
                                :type="
                                    field.type === 'number' ? 'number' : 'text'
                                "
                                :placeholder="fieldPlaceholder(field)"
                                @input="emitSlots"
                            />
                        </div>
                    </div>

                    <div
                        v-if="pageEditableEligible(placement.block)"
                        class="grid gap-3 rounded-md border border-blue-100 bg-blue-50 p-3"
                    >
                        <label
                            class="flex items-center gap-2 text-sm font-semibold text-slate-800"
                        >
                            <input
                                v-model="placement.settings.page_editable"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                @change="emitSlots"
                            />
                            {{
                                t(
                                    'components.block_editor.page_editable',
                                    'Editable on pages',
                                )
                            }}
                        </label>

                        <div
                            v-if="placement.settings.page_editable"
                            class="grid gap-3 md:grid-cols-2"
                        >
                            <div class="grid gap-2">
                                <Label>
                                    {{
                                        t(
                                            'components.block_editor.content_key',
                                            'Page data key',
                                        )
                                    }}
                                </Label>
                                <Input
                                    v-model="placement.settings.content_key"
                                    class="font-mono"
                                    :placeholder="
                                        slotChildContentKey(
                                            slot,
                                            placement,
                                            placementIndex,
                                        )
                                    "
                                    @blur="
                                        normalizePlacementContentKey(placement)
                                    "
                                    @input="emitSlots"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>
                                    {{
                                        t(
                                            'components.block_editor.editor_label',
                                            'Page editor label',
                                        )
                                    }}
                                </Label>
                                <Input
                                    v-model="placement.settings.editor_label"
                                    :placeholder="blockLabel(placement.block)"
                                    @input="emitSlots"
                                />
                            </div>
                            <div class="grid gap-2 md:col-span-2">
                                <span
                                    class="text-xs font-semibold text-slate-700"
                                >
                                    {{
                                        t(
                                            'components.block_editor.page_editable_fields',
                                            'Editable fields',
                                        )
                                    }}
                                </span>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label
                                        class="flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                            :checked="
                                                pageEditableMetaEnabled(
                                                    placement,
                                                    'is_active',
                                                )
                                            "
                                            @change="
                                                togglePageEditableMeta(
                                                    placement,
                                                    'is_active',
                                                    $event.target.checked,
                                                )
                                            "
                                        />
                                        {{
                                            t('common.columns.active', 'Active')
                                        }}
                                    </label>
                                    <label
                                        v-for="field in pageEditableFields(
                                            placement.block,
                                        )"
                                        :key="`${placement.uid}-page-field-${field.name}`"
                                        class="flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                            :checked="
                                                pageEditableFieldEnabled(
                                                    placement,
                                                    field.name,
                                                )
                                            "
                                            @change="
                                                togglePageEditableField(
                                                    placement,
                                                    field.name,
                                                    $event.target.checked,
                                                )
                                            "
                                        />
                                        {{ fieldLabel(field) }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import RwCodeEditor from '@/Components/RwCodeEditor.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import CmsRichTextEditor from '@/Pages/Admin/Cms/Components/CmsRichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { computed } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');

const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
    slotDefinitions: { type: Array, default: () => [] },
    placeableBlocks: { type: Array, default: () => [] },
    mediaOptions: { type: Array, default: () => [] },
    mediaFolders: { type: Array, default: () => [] },
    downloadOptions: { type: Array, default: () => [] },
    downloadFolders: { type: Array, default: () => [] },
    parentContentKey: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'settings-requested']);

const slots = computed({
    get: () => normalizeSlots(props.modelValue),
    set: (value) => emit('update:modelValue', normalizeSlots(value)),
});

function normalizeSlots(value) {
    const source = value && typeof value === 'object' ? value : {};
    const normalized = {};

    props.slotDefinitions.forEach((slot) => {
        const slotKey = String(slot?.key || '');

        if (!slotKey) {
            return;
        }

        const slotData = source[slotKey];
        const placements = Array.isArray(slotData?.placements)
            ? slotData.placements
            : Array.isArray(slotData)
              ? slotData
              : [];

        normalized[slotKey] = {
            placements: placements.map((placement, index) =>
                normalizePlacement(placement, index),
            ),
        };
    });

    return normalized;
}

function normalizePlacement(placement, index = 0) {
    const block = normalizeBlock(placement?.block || {});

    return {
        uid:
            placement?.uid ||
            `slot-placement-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        id: placement?.id || null,
        is_active: Boolean(placement?.is_active ?? true),
        visible_mobile: Boolean(placement?.visible_mobile ?? true),
        visible_tablet: Boolean(placement?.visible_tablet ?? true),
        visible_desktop: Boolean(placement?.visible_desktop ?? true),
        mobile_span: Number(placement?.mobile_span || 12),
        tablet_span: Number(placement?.tablet_span || 12),
        desktop_span: Number(placement?.desktop_span || 12),
        layout_config: placement?.layout_config || null,
        style_config: placement?.style_config || {},
        height_mode: placement?.height_mode || 'auto',
        height_value: placement?.height_value || null,
        cache_strategy: placement?.cache_strategy || 'inherit',
        settings: normalizePlacementSettings(placement?.settings, block),
        block,
        sort_order: Number(placement?.sort_order ?? index),
    };
}

function normalizePlacementSettings(settings, block) {
    const source = settings && typeof settings === 'object' ? settings : {};
    const editableFields = pageEditableFields(block).map((field) => field.name);

    return {
        content_key:
            typeof source.content_key === 'string' ? source.content_key : '',
        editor_label:
            typeof source.editor_label === 'string' ? source.editor_label : '',
        page_editable: Boolean(source.page_editable || source.content_key),
        page_editable_fields: Array.isArray(source.page_editable_fields)
            ? source.page_editable_fields.filter(
                  (field) => typeof field === 'string' && field,
              )
            : editableFields,
        page_editable_meta: Array.isArray(source.page_editable_meta)
            ? source.page_editable_meta.filter((field) => field === 'is_active')
            : [],
    };
}

function normalizeBlock(block) {
    const definition = blockDefinitionById(block?.cms_placeable_block_id);
    const blockId = definition?.id || props.placeableBlocks[0]?.id || 0;
    const normalized = {
        id: block?.id || null,
        cms_placeable_block_id: Number(blockId || 0),
        placeable_block_revision_id:
            block?.placeable_block_revision_id ||
            definition?.revision_id ||
            null,
        name: block?.name || '',
    };

    editorFields(normalized).forEach((field) => {
        normalized[field.name] = block?.[field.name] ?? fieldDefault(field);
    });

    return normalized;
}

function emitSlots() {
    slots.value = { ...slots.value };
}

function slotPlacements(slot) {
    return slots.value[slot.key]?.placements || [];
}

function setSlotPlacements(slot, placements) {
    slots.value = {
        ...slots.value,
        [slot.key]: { placements },
    };
}

function addPlacement(slot) {
    const options = allowedOptions(slot);
    const firstOption = options[0];

    if (!firstOption || slotIsFull(slot)) {
        return;
    }

    setSlotPlacements(slot, [
        ...slotPlacements(slot),
        normalizePlacement({
            settings: defaultPageEditableSettings(
                slot,
                firstOption.value,
                slotPlacements(slot).length,
            ),
            block: { cms_placeable_block_id: firstOption.value },
        }),
    ]);
}

function removePlacement(slot, index) {
    setSlotPlacements(
        slot,
        slotPlacements(slot).filter(
            (placement, placementIndex) => placementIndex !== index,
        ),
    );
}

function movePlacement(slot, index, direction) {
    const nextIndex = index + direction;
    const placements = [...slotPlacements(slot)];

    if (nextIndex < 0 || nextIndex >= placements.length) {
        return;
    }

    const [placement] = placements.splice(index, 1);
    placements.splice(nextIndex, 0, placement);
    setSlotPlacements(slot, placements);
}

function updatePlacementBlock(slot, index, blockId) {
    const placements = [...slotPlacements(slot)];

    if (!placements[index]) {
        return;
    }

    placements[index] = normalizePlacement({
        ...placements[index],
        settings: {
            ...placements[index].settings,
            ...defaultPageEditableSettings(slot, blockId, index),
        },
        block: { cms_placeable_block_id: blockId },
    });
    setSlotPlacements(slot, placements);
}

function defaultPageEditableSettings(slot, blockId, index) {
    const block = { cms_placeable_block_id: blockId };
    const definition = blockDefinitionById(blockId);

    return {
        content_key: slotChildContentKey(slot, { block }, index),
        editor_label: definition?.name || definition?.key || '',
        page_editable: true,
        page_editable_fields: pageEditableFields(block).map(
            (field) => field.name,
        ),
        page_editable_meta: ['is_active'],
    };
}

function updateBlockField(placement, fieldName, value) {
    placement.block[fieldName] = value;
    emitSlots();
}

function requestPlacementSettings(placement) {
    emit('settings-requested', placement);
}

function allowedOptions(slot) {
    const allowedKeys = Array.isArray(slot.allowed_block_keys)
        ? slot.allowed_block_keys.map(String)
        : [];

    return props.placeableBlocks
        .filter((block) => allowedKeys.includes(String(block.key || '')))
        .filter((block) => !block.requires_permission)
        .filter((block) => !['code', 'system'].includes(blockCategory(block)))
        .filter(
            (block) =>
                !Array.isArray(block.schema?.slots) ||
                block.schema.slots.length === 0,
        )
        .map((block) => ({
            value: block.id,
            key: block.key,
            label: block.name || block.key,
        }));
}

function slotIsFull(slot) {
    if (slot.max_items === null || slot.max_items === undefined) {
        return false;
    }

    return slotPlacements(slot).length >= Number(slot.max_items);
}

function slotSummary(slot) {
    const parts = [];

    if (slot.min_items !== null && slot.min_items !== undefined) {
        parts.push(
            t('components.block_editor.slot_min_items', 'Minimum: :count', {
                count: slot.min_items,
            }),
        );
    }

    if (slot.max_items !== null && slot.max_items !== undefined) {
        parts.push(
            t('components.block_editor.slot_max_items', 'Maximum: :count', {
                count: slot.max_items,
            }),
        );
    }

    return (
        parts.join(' · ') ||
        t('components.block_editor.slot_open_limit', 'No fixed limit')
    );
}

function blockDefinitionById(id) {
    return props.placeableBlocks.find(
        (block) => Number(block.id) === Number(id),
    );
}

function blockLabel(block) {
    return (
        blockDefinitionById(block?.cms_placeable_block_id)?.name ||
        t('components.block_editor.block_fallback', 'Block')
    );
}

function blockKey(block) {
    return blockDefinitionById(block?.cms_placeable_block_id)?.key || '';
}

function blockCategory(block) {
    return block?.schema?.category || block?.category || 'content';
}

function editorFields(block) {
    const fields = blockDefinitionById(block?.cms_placeable_block_id)?.schema
        ?.editor_fields;

    return Array.isArray(fields) ? fields : [];
}

function fieldLabel(field) {
    if (field.label_key) {
        return t(field.label_key, field.label || field.name || '');
    }

    return field.label || field.name || '';
}

function fieldPlaceholder(field) {
    if (field.placeholder_key) {
        return t(field.placeholder_key, field.placeholder || '');
    }

    return field.placeholder || '';
}

function fieldDefault(field) {
    if (field.type === 'checkbox') {
        return false;
    }

    if (field.type === 'number') {
        return null;
    }

    if (field.type === 'select') {
        return field.options?.[0]?.value ?? '';
    }

    return '';
}

function pageEditableEligible(block) {
    return pageEditableFields(block).length > 0;
}

function pageEditableFields(block) {
    return editorFields(block).filter((field) => field.type !== 'code');
}

function pageEditableFieldEnabled(placement, fieldName) {
    return Array.isArray(placement.settings?.page_editable_fields)
        ? placement.settings.page_editable_fields.includes(fieldName)
        : true;
}

function togglePageEditableField(placement, fieldName, enabled) {
    const fields = new Set(
        Array.isArray(placement.settings.page_editable_fields)
            ? placement.settings.page_editable_fields
            : [],
    );

    if (enabled) {
        fields.add(fieldName);
    } else {
        fields.delete(fieldName);
    }

    placement.settings.page_editable_fields = [...fields];
    emitSlots();
}

function pageEditableMetaEnabled(placement, fieldName) {
    return Array.isArray(placement.settings?.page_editable_meta)
        ? placement.settings.page_editable_meta.includes(fieldName)
        : false;
}

function togglePageEditableMeta(placement, fieldName, enabled) {
    const fields = new Set(
        Array.isArray(placement.settings.page_editable_meta)
            ? placement.settings.page_editable_meta
            : [],
    );

    if (enabled) {
        fields.add(fieldName);
    } else {
        fields.delete(fieldName);
    }

    placement.settings.page_editable_meta = [...fields];
    emitSlots();
}

function normalizePlacementContentKey(placement) {
    placement.settings.content_key = normalizeContentKey(
        placement.settings.content_key,
    );
    emitSlots();
}

function normalizeContentKey(value) {
    const key = String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/^_+|_+$/g, '');

    return /^[a-z][a-z0-9_]{0,79}$/.test(key) ? key : '';
}

function slotChildContentKey(slot, placement, index) {
    const parts = [
        props.parentContentKey,
        slot?.key,
        blockKey(placement?.block),
        Number(index) + 1,
    ].filter(Boolean);

    return normalizeContentKey(parts.join('_')) || 'slot_block';
}
</script>
