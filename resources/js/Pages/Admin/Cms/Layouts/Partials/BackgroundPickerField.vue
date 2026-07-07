<template>
    <section
        class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
    >
        <div class="grid gap-1">
            <h4 class="text-sm font-semibold text-slate-900">
                {{ label }}
            </h4>
            <div class="flex flex-wrap gap-4 border-b border-slate-200">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    class="-mb-px border-b-2 px-1 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-blue-100"
                    :class="
                        activeTab === tab.value
                            ? 'border-blue-600 text-blue-700'
                            : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'
                    "
                    @click="activeTab = tab.value"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <ColorPickerField
            v-if="activeTab === 'color'"
            v-model="colorValue"
            v-model:palette-items="paletteItemsProxy"
            :id-prefix="`${idPrefix}-color`"
            :label="t('layouts.background.color_label', 'Kleur')"
        />

        <div v-else class="grid gap-3">
            <CmsMediaPicker
                v-model="mediaAssetId"
                v-model:assets="assetsProxy"
                v-model:folders="foldersProxy"
                :preview-opacity="imageOpacity"
            />

            <div
                v-if="mediaAssetId"
                class="grid gap-3 rounded-md border border-slate-200 bg-white p-3 md:grid-cols-2"
            >
                <div class="grid gap-1 text-sm">
                    <Label :for="`${idPrefix}-image-mode`">
                        {{ t('layouts.background.image_mode', 'Weergave') }}
                    </Label>
                    <select
                        :id="`${idPrefix}-image-mode`"
                        v-model="imageMode"
                        class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                        <option
                            v-for="option in imageModeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-1 text-sm">
                    <Label :for="`${idPrefix}-image-position`">
                        {{ t('layouts.background.image_position', 'Positie') }}
                    </Label>
                    <select
                        :id="`${idPrefix}-image-position`"
                        v-model="imagePosition"
                        class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                        <option
                            v-for="option in imagePositionOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-2 text-sm md:col-span-2">
                    <div class="flex items-center justify-between gap-3">
                        <Label :for="`${idPrefix}-image-opacity`">
                            {{
                                t(
                                    'layouts.background.image_opacity',
                                    'Afbeelding zichtbaar',
                                )
                            }}
                        </Label>
                        <div class="flex items-center gap-2">
                            <input
                                :id="`${idPrefix}-image-opacity-number`"
                                v-model.number="imageOpacity"
                                type="number"
                                min="0"
                                max="100"
                                step="1"
                                class="h-9 w-20 rounded-md border border-slate-300 bg-white px-2 text-right text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                :aria-label="
                                    t(
                                        'layouts.background.image_opacity_percent',
                                        'Afbeelding zichtbaar in procent',
                                    )
                                "
                            />
                            <span class="text-xs font-medium text-slate-500">
                                %
                            </span>
                        </div>
                    </div>
                    <input
                        :id="`${idPrefix}-image-opacity`"
                        v-model.number="imageOpacity"
                        type="range"
                        min="0"
                        max="100"
                        step="1"
                        class="h-2 w-full cursor-pointer accent-blue-600"
                    />
                    <p class="text-xs text-slate-500">
                        {{
                            t(
                                'layouts.background.image_opacity_help',
                                '100% toont de afbeelding volledig. Lagere waarden maken alleen de afbeelding transparanter, niet de inhoud.',
                            )
                        }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import CmsMediaPicker from '@/Pages/Admin/Cms/Components/CmsMediaPicker.vue';
import ColorPickerField from '@/Pages/Admin/Cms/Layouts/Partials/ColorPickerField.vue';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
    paletteItems: { type: Array, default: () => [] },
    assets: { type: Array, default: () => [] },
    folders: { type: Array, default: () => [] },
    idPrefix: { type: String, required: true },
    label: { type: String, required: true },
});

const emit = defineEmits([
    'update:modelValue',
    'update:paletteItems',
    'update:assets',
    'update:folders',
]);
const { t } = useAdminTranslations('cms_admin_ui');
const activeTab = ref('color');

const tabs = computed(() => [
    { value: 'color', label: t('layouts.background.tab_color', 'Kleur') },
    { value: 'image', label: t('layouts.background.tab_image', 'Afbeelding') },
]);

const imageModeOptions = computed(() => [
    { value: 'cover', label: t('layouts.background.mode_cover', 'Vullend') },
    {
        value: 'contain',
        label: t('layouts.background.mode_contain', 'Passend'),
    },
    {
        value: 'stretch',
        label: t('layouts.background.mode_stretch', 'Uitrekken'),
    },
    {
        value: 'center',
        label: t('layouts.background.mode_center', 'Centreren'),
    },
    { value: 'repeat', label: t('layouts.background.mode_repeat', 'Herhalen') },
    {
        value: 'repeat-x',
        label: t('layouts.background.mode_repeat_x', 'Horizontaal herhalen'),
    },
    {
        value: 'repeat-y',
        label: t('layouts.background.mode_repeat_y', 'Verticaal herhalen'),
    },
]);

const imagePositionOptions = computed(() => [
    {
        value: 'center center',
        label: t('layouts.background.position_center', 'Midden'),
    },
    {
        value: 'center top',
        label: t('layouts.background.position_top', 'Boven'),
    },
    {
        value: 'center bottom',
        label: t('layouts.background.position_bottom', 'Onder'),
    },
    {
        value: 'left center',
        label: t('layouts.background.position_left', 'Links'),
    },
    {
        value: 'right center',
        label: t('layouts.background.position_right', 'Rechts'),
    },
]);

const background = computed(() => normalizeBackground(props.modelValue));

const paletteItemsProxy = computed({
    get: () => props.paletteItems,
    set: (items) => emit('update:paletteItems', items),
});

const assetsProxy = computed({
    get: () => props.assets,
    set: (assets) => emit('update:assets', assets),
});

const foldersProxy = computed({
    get: () => props.folders,
    set: (folders) => emit('update:folders', folders),
});

const colorValue = computed({
    get: () => background.value.color,
    set: (color) => updateBackground({ color: normalizeHexColor(color) }),
});

const mediaAssetId = computed({
    get: () => background.value.media_asset_id,
    set: (mediaAssetId) => updateBackground({ media_asset_id: mediaAssetId }),
});

const imageMode = computed({
    get: () => background.value.mode,
    set: (mode) => updateBackground({ mode }),
});

const imagePosition = computed({
    get: () => background.value.position,
    set: (position) => updateBackground({ position }),
});

const imageOpacity = computed({
    get: () => background.value.image_opacity,
    set: (imageOpacity) => updateBackground({ image_opacity: imageOpacity }),
});

function updateBackground(partial) {
    emit(
        'update:modelValue',
        normalizeBackground({ ...background.value, ...partial }),
    );
}

function normalizeBackground(value) {
    const background = value && typeof value === 'object' ? value : {};

    return {
        color: normalizeHexColor(background.color),
        media_asset_id: background.media_asset_id || null,
        mode: imageModeOptions.value.some(
            (option) => option.value === background.mode,
        )
            ? background.mode
            : 'cover',
        position: imagePositionOptions.value.some(
            (option) => option.value === background.position,
        )
            ? background.position
            : 'center center',
        image_opacity: normalizeImageOpacity(background.image_opacity),
    };
}

function normalizeImageOpacity(value) {
    const opacity = Number(value ?? 100);

    if (!Number.isFinite(opacity)) {
        return 100;
    }

    return Math.min(100, Math.max(0, Math.round(opacity)));
}

function normalizeHexColor(value) {
    const color = String(value || '').trim();

    if (!/^#[0-9A-Fa-f]{6}$/.test(color)) {
        return null;
    }

    return color.toUpperCase();
}
</script>
