<template>
    <div class="grid gap-3 rounded-lg border border-slate-200 bg-white p-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h4 class="text-sm font-semibold text-slate-900">
                    {{ title }}
                </h4>
                <p v-if="description" class="text-xs leading-5 text-slate-500">
                    {{ description }}
                </p>
            </div>
            <div v-if="!fixedDevice" class="flex flex-wrap gap-2">
                <button
                    v-for="device in deviceOptions"
                    :key="device.value"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-blue-200"
                    :class="
                        activeDevice === device.value
                            ? 'border-blue-300 bg-blue-50 text-blue-700'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900'
                    "
                    @click="activeDevice = device.value"
                >
                    <span
                        v-if="hasVisibilityControls"
                        :class="visibilityIconClass(device.value)"
                        aria-hidden="true"
                    />
                    {{ device.label }}
                </button>
            </div>
        </div>

        <label
            v-if="hasVisibilityControls"
            class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
        >
            <input
                type="checkbox"
                class="h-4 w-4 rounded border-slate-300"
                :checked="visibilityValue(activeDevice)"
                @change="updateVisibility(activeDevice, $event.target.checked)"
            />
            {{
                t('layouts.box.visible_on_device', 'Zichtbaar op :device', {
                    device: activeDeviceOption.label,
                })
            }}
        </label>

        <div class="grid gap-3">
            <div
                class="grid gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3"
            >
                <div class="flex items-center justify-between gap-3">
                    <span
                        class="text-xs font-semibold uppercase tracking-wide text-amber-800"
                    >
                        {{ t('layouts.box.margin', 'Marge') }}
                    </span>
                    <select
                        :value="groupUnit('margin')"
                        class="h-8 w-20 rounded-md border border-amber-200 bg-white px-2 pr-7 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        :aria-label="
                            t('layouts.box.margin_unit', 'Marge-eenheid')
                        "
                        @change="updateUnit('margin', $event.target.value)"
                    >
                        <option
                            v-for="unit in unitOptions"
                            :key="unit"
                            :value="unit"
                        >
                            {{ unit }}
                        </option>
                    </select>
                </div>

                <div
                    class="grid grid-cols-1 items-center gap-2 xl:grid-cols-[minmax(8rem,1fr)_auto_minmax(8rem,1fr)]"
                >
                    <div class="hidden xl:block" />
                    <SpacingInput
                        :id="fieldId('margin', 'top')"
                        :label="t('layouts.box.top', 'Boven')"
                        :value="sideValue('margin', 'top')"
                        :unit="sideUnit('margin', 'top')"
                        :unit-options="unitOptions"
                        @update:value="updateSide('margin', 'top', $event)"
                        @update:unit="updateSideUnit('margin', 'top', $event)"
                    />
                    <div class="hidden xl:block" />

                    <SpacingInput
                        :id="fieldId('margin', 'left')"
                        :label="t('layouts.box.left', 'Links')"
                        :value="sideValue('margin', 'left')"
                        :unit="sideUnit('margin', 'left')"
                        :unit-options="unitOptions"
                        @update:value="updateSide('margin', 'left', $event)"
                        @update:unit="updateSideUnit('margin', 'left', $event)"
                    />

                    <div
                        class="grid gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span
                                class="text-xs font-semibold uppercase tracking-wide text-blue-800"
                            >
                                {{ t('layouts.box.padding', 'Padding') }}
                            </span>
                            <select
                                :value="groupUnit('padding')"
                                class="h-8 w-20 rounded-md border border-blue-200 bg-white px-2 pr-7 text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                :aria-label="
                                    t(
                                        'layouts.box.padding_unit',
                                        'Padding-eenheid',
                                    )
                                "
                                @change="
                                    updateUnit('padding', $event.target.value)
                                "
                            >
                                <option
                                    v-for="unit in unitOptions"
                                    :key="unit"
                                    :value="unit"
                                >
                                    {{ unit }}
                                </option>
                            </select>
                        </div>

                        <div
                            class="grid grid-cols-1 items-center gap-2 2xl:grid-cols-[minmax(8rem,1fr)_auto_minmax(8rem,1fr)]"
                        >
                            <div class="hidden 2xl:block" />
                            <SpacingInput
                                :id="fieldId('padding', 'top')"
                                :label="t('layouts.box.top', 'Boven')"
                                :value="sideValue('padding', 'top')"
                                :unit="sideUnit('padding', 'top')"
                                :unit-options="unitOptions"
                                :min="0"
                                @update:value="
                                    updateSide('padding', 'top', $event)
                                "
                                @update:unit="
                                    updateSideUnit('padding', 'top', $event)
                                "
                            />
                            <div class="hidden 2xl:block" />

                            <SpacingInput
                                :id="fieldId('padding', 'left')"
                                :label="t('layouts.box.left', 'Links')"
                                :value="sideValue('padding', 'left')"
                                :unit="sideUnit('padding', 'left')"
                                :unit-options="unitOptions"
                                :min="0"
                                @update:value="
                                    updateSide('padding', 'left', $event)
                                "
                                @update:unit="
                                    updateSideUnit('padding', 'left', $event)
                                "
                            />

                            <div
                                class="grid min-h-20 place-items-center rounded-md border border-dashed border-slate-300 bg-white px-4 py-3 text-center text-xs font-medium text-slate-500"
                            >
                                {{ t('layouts.box.content', 'Inhoud') }}
                            </div>

                            <SpacingInput
                                :id="fieldId('padding', 'right')"
                                :label="t('layouts.box.right', 'Rechts')"
                                :value="sideValue('padding', 'right')"
                                :unit="sideUnit('padding', 'right')"
                                :unit-options="unitOptions"
                                :min="0"
                                @update:value="
                                    updateSide('padding', 'right', $event)
                                "
                                @update:unit="
                                    updateSideUnit('padding', 'right', $event)
                                "
                            />

                            <div class="hidden 2xl:block" />
                            <SpacingInput
                                :id="fieldId('padding', 'bottom')"
                                :label="t('layouts.box.bottom', 'Onder')"
                                :value="sideValue('padding', 'bottom')"
                                :unit="sideUnit('padding', 'bottom')"
                                :unit-options="unitOptions"
                                :min="0"
                                @update:value="
                                    updateSide('padding', 'bottom', $event)
                                "
                                @update:unit="
                                    updateSideUnit('padding', 'bottom', $event)
                                "
                            />
                            <div class="hidden 2xl:block" />
                        </div>

                        <div class="flex justify-end pt-1">
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-blue-200 bg-white text-blue-700 shadow-none transition hover:bg-blue-50 hover:text-blue-800"
                                :aria-label="
                                    t(
                                        'layouts.box.copy_padding_to_all_sides',
                                        'Padding naar alle zijden',
                                    )
                                "
                                :title="
                                    t(
                                        'layouts.box.copy_padding_to_all_sides',
                                        'Padding naar alle zijden',
                                    )
                                "
                                @click="copyGroupToAllSides('padding')"
                            >
                                <span
                                    class="mdi mdi-arrow-all text-base"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                    </div>

                    <SpacingInput
                        :id="fieldId('margin', 'right')"
                        :label="t('layouts.box.right', 'Rechts')"
                        :value="sideValue('margin', 'right')"
                        :unit="sideUnit('margin', 'right')"
                        :unit-options="unitOptions"
                        @update:value="updateSide('margin', 'right', $event)"
                        @update:unit="updateSideUnit('margin', 'right', $event)"
                    />

                    <div class="hidden xl:block" />
                    <SpacingInput
                        :id="fieldId('margin', 'bottom')"
                        :label="t('layouts.box.bottom', 'Onder')"
                        :value="sideValue('margin', 'bottom')"
                        :unit="sideUnit('margin', 'bottom')"
                        :unit-options="unitOptions"
                        @update:value="updateSide('margin', 'bottom', $event)"
                        @update:unit="
                            updateSideUnit('margin', 'bottom', $event)
                        "
                    />
                    <div class="hidden xl:block" />
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-blue-200 bg-white text-blue-700 shadow-none transition hover:bg-blue-50 hover:text-blue-800"
                        :aria-label="
                            t(
                                'layouts.box.copy_margin_to_all_sides',
                                'Marge naar alle zijden',
                            )
                        "
                        :title="
                            t(
                                'layouts.box.copy_margin_to_all_sides',
                                'Marge naar alle zijden',
                            )
                        "
                        @click="copyGroupToAllSides('margin')"
                    >
                        <span
                            class="mdi mdi-arrow-all text-base"
                            aria-hidden="true"
                        />
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-red-200 bg-white text-red-700 shadow-none transition hover:bg-red-50 hover:text-red-800"
                        :aria-label="
                            t(
                                'layouts.box.clear_device',
                                'Waarden voor device leegmaken',
                            )
                        "
                        :title="
                            t(
                                'layouts.box.clear_device',
                                'Waarden voor device leegmaken',
                            )
                        "
                        @click="clearDevice"
                    >
                        <span
                            class="mdi mdi-eraser text-base"
                            aria-hidden="true"
                        />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { computed, defineComponent, h, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    idPrefix: { type: String, required: true },
    visibleDesktop: { type: Boolean, default: null },
    visibleTablet: { type: Boolean, default: null },
    visibleMobile: { type: Boolean, default: null },
    fixedDevice: { type: String, default: '' },
});

const emit = defineEmits([
    'update:modelValue',
    'update:visibleDesktop',
    'update:visibleTablet',
    'update:visibleMobile',
]);
const { t } = useAdminTranslations('cms_admin_ui');
const activeDevice = ref('desktop');
const devices = ['desktop', 'tablet', 'mobile'];
const groups = ['padding', 'margin'];
const sides = ['top', 'right', 'bottom', 'left'];
const unitOptions = ['px', 'rem', 'em', '%', 'vw', 'vh'];
const deviceOptions = computed(() => [
    { value: 'desktop', label: t('layouts.sections.desktop', 'Desktop') },
    { value: 'tablet', label: t('layouts.sections.tablet', 'Tablet') },
    { value: 'mobile', label: t('layouts.sections.mobile', 'Mobiel') },
]);
const activeDeviceOption = computed(
    () =>
        deviceOptions.value.find(
            (device) => device.value === activeDevice.value,
        ) || deviceOptions.value[0],
);
const fixedDevice = computed(() =>
    devices.includes(props.fixedDevice) ? props.fixedDevice : '',
);
const hasVisibilityControls = computed(
    () =>
        !fixedDevice.value &&
        props.visibleDesktop !== null &&
        props.visibleTablet !== null &&
        props.visibleMobile !== null,
);

watch(
    fixedDevice,
    (device) => {
        if (device) {
            activeDevice.value = device;
        }
    },
    { immediate: true },
);

const SpacingInput = defineComponent({
    props: {
        id: { type: String, required: true },
        label: { type: String, required: true },
        value: { type: [Number, String], default: null },
        unit: { type: String, required: true },
        unitOptions: { type: Array, required: true },
        min: { type: Number, default: null },
    },
    emits: ['update:value', 'update:unit'],
    setup(inputProps, { emit: inputEmit }) {
        return () =>
            h(
                'label',
                { class: 'grid gap-1 text-center text-[11px] text-slate-500' },
                [
                    h('span', inputProps.label),
                    h(
                        'div',
                        { class: 'flex items-center justify-center gap-1' },
                        [
                            h('input', {
                                id: inputProps.id,
                                type: 'number',
                                step: '0.25',
                                min: inputProps.min ?? undefined,
                                value: inputProps.value ?? '',
                                placeholder: '-',
                                class: 'h-8 w-16 rounded-md border border-slate-300 bg-white px-2 text-center text-xs text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                onInput: (event) =>
                                    inputEmit(
                                        'update:value',
                                        event.target.value,
                                    ),
                            }),
                            h(
                                'select',
                                {
                                    value: inputProps.unit,
                                    class: 'h-8 w-16 rounded-md border border-slate-300 bg-white px-1 text-xs text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                    'aria-label': t(
                                        'layouts.box.side_unit',
                                        ':side eenheid',
                                        { side: inputProps.label },
                                    ),
                                    onChange: (event) =>
                                        inputEmit(
                                            'update:unit',
                                            event.target.value,
                                        ),
                                },
                                inputProps.unitOptions.map((unit) =>
                                    h(
                                        'option',
                                        { key: unit, value: unit },
                                        unit,
                                    ),
                                ),
                            ),
                        ],
                    ),
                ],
            );
    },
});

function normalizedModel() {
    const input = props.modelValue || {};

    return devices.reduce((model, device) => {
        model[device] = groups.reduce((deviceModel, group) => {
            const groupInput = input?.[device]?.[group] || {};
            deviceModel[group] = {
                unit: unitOptions.includes(groupInput.unit)
                    ? groupInput.unit
                    : 'rem',
            };

            sides.forEach((side) => {
                deviceModel[group][side] = normalizeValue(groupInput[side]);
                deviceModel[group][sideUnitKey(side)] = unitOptions.includes(
                    groupInput[sideUnitKey(side)],
                )
                    ? groupInput[sideUnitKey(side)]
                    : deviceModel[group].unit;
            });

            return deviceModel;
        }, {});

        return model;
    }, {});
}

function normalizeValue(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = Number(value);

    return Number.isFinite(number) ? number : null;
}

function groupUnit(group) {
    return normalizedModel()[activeDevice.value][group].unit;
}

function sideValue(group, side) {
    return normalizedModel()[activeDevice.value][group][side];
}

function sideUnit(group, side) {
    return normalizedModel()[activeDevice.value][group][sideUnitKey(side)];
}

function sideUnitKey(side) {
    return `${side}_unit`;
}

function fieldId(group, side) {
    return `${props.idPrefix}-${activeDevice.value}-${group}-${side}`;
}

function visibilityValue(device) {
    return Boolean(
        {
            desktop: props.visibleDesktop,
            tablet: props.visibleTablet,
            mobile: props.visibleMobile,
        }[device],
    );
}

function updateVisibility(device, value) {
    const events = {
        desktop: 'update:visibleDesktop',
        tablet: 'update:visibleTablet',
        mobile: 'update:visibleMobile',
    };

    if (events[device]) {
        emit(events[device], value);
    }
}

function visibilityIconClass(device) {
    return [
        'mdi text-sm',
        visibilityValue(device)
            ? 'mdi-check-circle text-green-700'
            : 'mdi-close-circle text-red-700',
    ];
}

function updateUnit(group, unit) {
    if (!unitOptions.includes(unit)) {
        return;
    }

    updateGroup(group, {
        unit,
        top_unit: unit,
        right_unit: unit,
        bottom_unit: unit,
        left_unit: unit,
    });
}

function updateSide(group, side, value) {
    if (!sides.includes(side)) {
        return;
    }

    const nextValue = normalizeValue(value);
    const normalizedValue =
        group === 'padding' && nextValue !== null
            ? Math.max(0, nextValue)
            : nextValue;

    updateGroup(group, { [side]: normalizedValue });
}

function updateSideUnit(group, side, unit) {
    if (!sides.includes(side) || !unitOptions.includes(unit)) {
        return;
    }

    updateGroup(group, { [sideUnitKey(side)]: unit });
}

function updateGroup(group, patch) {
    const next = normalizedModel();
    next[activeDevice.value][group] = {
        ...next[activeDevice.value][group],
        ...patch,
    };

    emit('update:modelValue', next);
}

function copyGroupToAllSides(group) {
    const current = normalizedModel()[activeDevice.value][group];
    const value =
        current.top ?? current.right ?? current.bottom ?? current.left;

    if (value === null || value === undefined) {
        return;
    }

    updateGroup(group, {
        top: value,
        right: value,
        bottom: value,
        left: value,
        top_unit: current.top_unit,
        right_unit: current.top_unit,
        bottom_unit: current.top_unit,
        left_unit: current.top_unit,
    });
}

function clearDevice() {
    const next = normalizedModel();
    groups.forEach((group) => {
        next[activeDevice.value][group] = {
            unit: next[activeDevice.value][group].unit,
            top_unit: next[activeDevice.value][group].top_unit,
            right_unit: next[activeDevice.value][group].right_unit,
            bottom_unit: next[activeDevice.value][group].bottom_unit,
            left_unit: next[activeDevice.value][group].left_unit,
            top: null,
            right: null,
            bottom: null,
            left: null,
        };
    });

    emit('update:modelValue', next);
}
</script>
