<template>
    <section class="grid gap-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="grid gap-1">
                <h3 class="text-sm font-semibold text-slate-900">
                    {{ title }}
                </h3>
                <p class="text-sm text-slate-500">
                    {{ description }}
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                @click="addSlot"
            >
                <span
                    class="mdi mdi-plus-circle text-base"
                    aria-hidden="true"
                />
                {{ t('slots.add_slot', 'Add slot') }}
            </Button>
        </div>

        <div
            v-if="slots.length === 0"
            class="rounded border border-dashed border-slate-300 p-4 text-sm text-slate-500"
        >
            {{ emptyText }}
        </div>

        <div v-else class="grid gap-3">
            <div
                v-for="(slot, index) in slots"
                :key="slot._uid"
                class="grid gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="grid gap-1">
                        <div class="text-sm font-semibold text-slate-900">
                            {{ slot.label || slot.key || '-' }}
                        </div>
                        <div class="font-mono text-xs text-slate-500">
                            {{ slot.key || '-' }}
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                        @click="removeSlot(index)"
                    >
                        {{ t('common.actions.delete', 'Delete') }}
                    </Button>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div class="grid gap-2">
                        <Label>{{ t('slots.key', 'Slot key') }}</Label>
                        <Input
                            :model-value="slot.key"
                            class="font-mono"
                            :placeholder="t('slots.key_placeholder', 'actions')"
                            @update:model-value="
                                updateSlot(index, { key: $event })
                            "
                            @blur="normalizeSlotKeyAt(index)"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ t('slots.label', 'Label') }}</Label>
                        <Input
                            :model-value="slot.label"
                            :placeholder="
                                t('slots.label_placeholder', 'Actions')
                            "
                            @update:model-value="
                                updateSlot(index, { label: $event })
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            t('slots.min_items', 'Minimum items')
                        }}</Label>
                        <Input
                            :model-value="slot.min_items"
                            type="number"
                            min="0"
                            @update:model-value="
                                updateSlot(index, {
                                    min_items: nullableNumber($event),
                                })
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            t('slots.max_items', 'Maximum items')
                        }}</Label>
                        <Input
                            :model-value="slot.max_items"
                            type="number"
                            min="0"
                            @update:model-value="
                                updateSlot(index, {
                                    max_items: nullableNumber($event),
                                })
                            "
                        />
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>{{ t('slots.layout', 'Layout') }}</Label>
                        <RwAutoCompleteInput
                            :model-value="slot.layout"
                            :items="layoutOptions"
                            item-title="label"
                            item-value="value"
                            :search-fields="['label', 'value']"
                            @update:model-value="
                                updateSlot(index, { layout: $event })
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            t('slots.responsive', 'Responsive mode')
                        }}</Label>
                        <RwAutoCompleteInput
                            :model-value="slot.responsive"
                            :items="responsiveOptions"
                            item-title="label"
                            item-value="value"
                            :search-fields="['label', 'value']"
                            @update:model-value="
                                updateSlot(index, { responsive: $event })
                            "
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>{{
                        t('slots.allowed_blocks', 'Allowed child blocks')
                    }}</Label>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="block in slotBlockOptions"
                            :key="block.key"
                            class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm"
                        >
                            <input
                                :checked="
                                    slot.allowed_block_keys.includes(block.key)
                                "
                                type="checkbox"
                                :value="block.key"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600"
                                @change="
                                    toggleAllowedBlock(
                                        index,
                                        block.key,
                                        $event.target.checked,
                                    )
                                "
                            />
                            {{ block.label || block.name || block.key }}
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { computed } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    title: { type: String, required: true },
    description: { type: String, required: true },
    emptyText: { type: String, required: true },
    layoutOptions: { type: Array, default: () => [] },
    responsiveOptions: { type: Array, default: () => [] },
    slotBlockOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const slots = computed({
    get: () =>
        props.modelValue.map((slot, index) => normalizeSlot(slot, index)),
    set: (value) =>
        emit(
            'update:modelValue',
            value.map((slot, index) => normalizeSlot(slot, index)),
        ),
});

function addSlot() {
    slots.value = [
        ...slots.value,
        normalizeSlot(
            { key: nextSlotKey(), label: '', allowed_block_keys: [] },
            slots.value.length,
        ),
    ];
}

function removeSlot(index) {
    slots.value = slots.value.filter((slot, slotIndex) => slotIndex !== index);
}

function updateSlot(index, values) {
    slots.value = slots.value.map((slot, slotIndex) =>
        slotIndex === index
            ? normalizeSlot({ ...slot, ...values }, index)
            : slot,
    );
}

function normalizeSlotKeyAt(index) {
    updateSlot(index, { key: normalizeSlotKey(slots.value[index]?.key || '') });
}

function toggleAllowedBlock(index, blockKey, checked) {
    const slot = slots.value[index];

    if (!slot) {
        return;
    }

    const allowed = checked
        ? [...new Set([...slot.allowed_block_keys, blockKey])]
        : slot.allowed_block_keys.filter((key) => key !== blockKey);

    updateSlot(index, { allowed_block_keys: allowed });
}

function normalizeSlot(slot, index = 0) {
    return {
        _uid: slot._uid || uniqueSlotId(),
        key: normalizeSlotKey(slot.key || ''),
        label: slot.label || '',
        allowed_block_keys: Array.isArray(slot.allowed_block_keys)
            ? slot.allowed_block_keys.map(String)
            : [],
        min_items: nullableNumber(slot.min_items),
        max_items: nullableNumber(slot.max_items),
        layout: slot.layout || props.layoutOptions[0]?.value || 'stack',
        responsive:
            slot.responsive ||
            props.responsiveOptions[0]?.value ||
            'stack_mobile',
        sort_order: Number(slot.sort_order ?? (index + 1) * 10),
    };
}

function normalizeSlotKey(value) {
    return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/_{2,}/g, '');
}

function nullableNumber(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = Number(value);

    return Number.isFinite(number) ? Math.max(0, Math.round(number)) : null;
}

function nextSlotKey() {
    let suffix = slots.value.length + 1;
    let candidate = `slot_${suffix}`;
    const existingKeys = new Set(slots.value.map((slot) => slot.key));

    while (existingKeys.has(candidate)) {
        suffix += 1;
        candidate = `slot_${suffix}`;
    }

    return candidate;
}

function uniqueSlotId() {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `slot_${Date.now()}_${Math.random().toString(36).slice(2)}`;
}
</script>
