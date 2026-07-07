<template>
    <Dialog :open="open" @update:open="updateOpen">
        <DialogContent
            class="flex max-h-[calc(100vh-2rem)] max-w-6xl flex-col overflow-hidden p-0 shadow-none"
        >
            <DialogHeader class="shrink-0 border-b border-slate-200 px-6 py-4">
                <DialogTitle>{{
                    t('media.editor.title', 'Edit image copy')
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        t(
                            'media.editor.description',
                            'Create a page-specific copy without changing the original media asset.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                <div
                    v-if="errorMessage"
                    class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                >
                    {{ errorMessage }}
                </div>

                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <div class="grid gap-3">
                        <Cropper
                            v-if="asset?.url"
                            ref="cropper"
                            class="cms-image-editor-cropper h-[min(65vh,640px)] rounded-lg border border-slate-200 bg-slate-950"
                            :src="asset.url"
                            :stencil-props="stencilProps"
                            :auto-zoom="true"
                            :transitions="true"
                            image-restriction="stencil"
                            :style="cropperStyle"
                            @change="onCropChange"
                            @ready="onCropReady"
                        />

                        <div
                            class="grid gap-2 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 sm:grid-cols-3"
                        >
                            <div>
                                <span class="font-medium text-slate-900">
                                    {{
                                        t(
                                            'media.editor.original_size',
                                            'Original',
                                        )
                                    }}:
                                </span>
                                {{ originalSizeLabel }}
                            </div>
                            <div>
                                <span class="font-medium text-slate-900">
                                    {{ t('media.editor.crop_size', 'Crop') }}:
                                </span>
                                {{ cropSizeLabel }}
                            </div>
                            <div>
                                <span class="font-medium text-slate-900">
                                    {{
                                        t('media.editor.output_size', 'Output')
                                    }}:
                                </span>
                                {{ outputSizeLabel }}
                            </div>
                        </div>
                    </div>

                    <div class="grid content-start gap-5">
                        <section
                            class="grid gap-3 rounded-md border border-slate-200 p-3"
                        >
                            <h3 class="text-sm font-semibold text-slate-900">
                                {{ t('media.editor.crop_title', 'Crop') }}
                            </h3>

                            <div class="grid gap-2">
                                <Label>{{
                                    t(
                                        'media.editor.aspect_ratio',
                                        'Aspect ratio',
                                    )
                                }}</Label>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-for="option in aspectRatioOptions"
                                        :key="option.value"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="shadow-none"
                                        :class="
                                            selectedAspectRatio === option.value
                                                ? 'border-blue-300 bg-blue-50 text-blue-700'
                                                : 'text-slate-700'
                                        "
                                        @click="selectAspectRatio(option.value)"
                                    >
                                        {{ option.label }}
                                    </Button>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label>{{
                                    t('media.editor.zoom', 'Zoom')
                                }}</Label>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="shadow-none"
                                        @click="zoomCropper(0.9)"
                                    >
                                        <span
                                            class="mdi mdi-magnify-minus-outline text-base"
                                            aria-hidden="true"
                                        />
                                        {{
                                            t(
                                                'media.editor.zoom_out',
                                                'Zoom out',
                                            )
                                        }}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="shadow-none"
                                        @click="zoomCropper(1.1)"
                                    >
                                        <span
                                            class="mdi mdi-magnify-plus-outline text-base"
                                            aria-hidden="true"
                                        />
                                        {{
                                            t('media.editor.zoom_in', 'Zoom in')
                                        }}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="shadow-none"
                                        @click="resetCropper"
                                    >
                                        <span
                                            class="mdi mdi-restore text-base"
                                            aria-hidden="true"
                                        />
                                        {{
                                            t(
                                                'media.editor.reset_crop',
                                                'Reset crop',
                                            )
                                        }}
                                    </Button>
                                </div>
                                <p class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'media.editor.crop_help',
                                            'Drag or resize the frame on the image. Use mouse wheel or the buttons to zoom.',
                                        )
                                    }}
                                </p>
                            </div>
                        </section>

                        <section
                            class="grid gap-3 rounded-md border border-slate-200 p-3"
                        >
                            <h3 class="text-sm font-semibold text-slate-900">
                                {{
                                    t(
                                        'media.editor.output_title',
                                        'Output size',
                                    )
                                }}
                            </h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="grid gap-1">
                                    <Label for="cms_image_edit_max_width">
                                        {{
                                            t(
                                                'media.editor.max_width',
                                                'Maximum width',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        id="cms_image_edit_max_width"
                                        v-model="form.max_width"
                                        type="number"
                                        min="1"
                                        max="8000"
                                        :placeholder="
                                            t('media.editor.keep_width', 'Keep')
                                        "
                                    />
                                </div>
                                <div class="grid gap-1">
                                    <Label for="cms_image_edit_max_height">
                                        {{
                                            t(
                                                'media.editor.max_height',
                                                'Maximum height',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        id="cms_image_edit_max_height"
                                        v-model="form.max_height"
                                        type="number"
                                        min="1"
                                        max="8000"
                                        :placeholder="
                                            t(
                                                'media.editor.keep_height',
                                                'Keep',
                                            )
                                        "
                                    />
                                </div>
                            </div>
                        </section>

                        <section
                            class="grid gap-3 rounded-md border border-slate-200 p-3"
                        >
                            <h3 class="text-sm font-semibold text-slate-900">
                                {{
                                    t(
                                        'media.editor.filters_title',
                                        'Tone and filters',
                                    )
                                }}
                            </h3>
                            <div class="grid gap-3">
                                <div class="grid gap-1">
                                    <Label for="cms_image_edit_brightness">
                                        {{
                                            t(
                                                'media.editor.brightness',
                                                'Brightness',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        id="cms_image_edit_brightness"
                                        v-model="form.brightness"
                                        type="range"
                                        min="-100"
                                        max="100"
                                    />
                                    <span class="text-xs text-slate-500">{{
                                        form.brightness
                                    }}</span>
                                </div>
                                <div class="grid gap-1">
                                    <Label for="cms_image_edit_contrast">
                                        {{
                                            t(
                                                'media.editor.contrast',
                                                'Contrast',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        id="cms_image_edit_contrast"
                                        v-model="form.contrast"
                                        type="range"
                                        min="-100"
                                        max="100"
                                    />
                                    <span class="text-xs text-slate-500">{{
                                        form.contrast
                                    }}</span>
                                </div>
                                <label
                                    class="flex items-center gap-2 text-sm text-slate-700"
                                >
                                    <input
                                        v-model="form.grayscale"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    {{
                                        t(
                                            'media.editor.grayscale',
                                            'Black and white',
                                        )
                                    }}
                                </label>
                                <div class="grid gap-1">
                                    <Label for="cms_image_edit_quality">
                                        {{
                                            t('media.editor.quality', 'Quality')
                                        }}
                                    </Label>
                                    <Input
                                        id="cms_image_edit_quality"
                                        v-model="form.quality"
                                        type="range"
                                        min="1"
                                        max="100"
                                    />
                                    <span class="text-xs text-slate-500"
                                        >{{ form.quality }}%</span
                                    >
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <DialogFooter class="shrink-0 border-t border-slate-200 px-6 py-4">
                <Button
                    type="button"
                    variant="outline"
                    class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                    :disabled="processing || !asset?.id"
                    @click="saveEditedCopy"
                >
                    <span
                        v-if="processing"
                        class="mdi mdi-loading animate-spin text-base text-green-700"
                        aria-hidden="true"
                    />
                    <span
                        v-else
                        class="mdi mdi-content-save text-base text-green-700"
                        aria-hidden="true"
                    />
                    {{ t('media.editor.save', 'Save edited copy') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup>
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import axios from 'axios';
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import { computed, nextTick, reactive, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    asset: { type: Object, default: null },
    uploadContextType: { type: String, default: '' },
    uploadContextId: { type: [Number, String], default: null },
});

const emit = defineEmits(['update:open', 'saved']);
const { t } = useAdminTranslations('cms_admin_ui');

const cropper = ref(null);
const processing = ref(false);
const errorMessage = ref('');
const cropCoordinates = ref(null);
const selectedAspectRatio = ref('free');
const form = reactive(defaultForm());

const aspectRatioOptions = computed(() => [
    { value: 'free', label: t('media.editor.aspect_free', 'Free') },
    { value: 'original', label: t('media.editor.aspect_original', 'Original') },
    { value: '1:1', label: '1:1' },
    { value: '4:3', label: '4:3' },
    { value: '3:2', label: '3:2' },
    { value: '16:9', label: '16:9' },
]);

const selectedRatio = computed(() => {
    if (selectedAspectRatio.value === 'original') {
        const width = Number(props.asset?.width || 0);
        const height = Number(props.asset?.height || 0);

        return width > 0 && height > 0 ? width / height : null;
    }

    const [width, height] = selectedAspectRatio.value.split(':').map(Number);

    return width > 0 && height > 0 ? width / height : null;
});

const stencilProps = computed(() =>
    selectedRatio.value ? { aspectRatio: selectedRatio.value } : {},
);

const cropperStyle = computed(() => ({
    '--cms-image-editor-filter': [
        form.grayscale ? 'grayscale(1)' : '',
        `brightness(${100 + Number(form.brightness || 0)}%)`,
        `contrast(${100 + Number(form.contrast || 0)}%)`,
    ]
        .filter(Boolean)
        .join(' '),
}));

const originalSizeLabel = computed(() =>
    sizeLabel(props.asset?.width, props.asset?.height),
);
const cropSizeLabel = computed(() =>
    cropCoordinates.value
        ? sizeLabel(cropCoordinates.value.width, cropCoordinates.value.height)
        : '-',
);
const outputSizeLabel = computed(() => {
    if (!cropCoordinates.value) {
        return '-';
    }

    const width =
        numericValue(form.max_width, 0) || cropCoordinates.value.width;
    const height =
        numericValue(form.max_height, 0) || cropCoordinates.value.height;

    return sizeLabel(width, height);
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            resetForm();
        }
    },
);

watch(selectedRatio, () => {
    nextTick(() => cropper.value?.refresh?.());
});

function updateOpen(value) {
    emit('update:open', value);
}

function onCropChange(result) {
    cropCoordinates.value = normalizeCoordinates(result?.coordinates);
}

function onCropReady() {
    cropCoordinates.value = normalizeCoordinates(
        cropper.value?.getResult?.()?.coordinates,
    );
}

function selectAspectRatio(value) {
    selectedAspectRatio.value = value;
}

function zoomCropper(factor) {
    cropper.value?.zoom?.(factor);
}

function resetCropper() {
    cropper.value?.reset?.();
    nextTick(() => {
        cropCoordinates.value = normalizeCoordinates(
            cropper.value?.getResult?.()?.coordinates,
        );
    });
}

async function saveEditedCopy() {
    if (!props.asset?.id) {
        return;
    }

    processing.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.post(
            route('admin.cms.media.edit-copy', { id: props.asset.id }),
            payload(),
            { headers: { Accept: 'application/json' } },
        );

        if (response.data?.asset) {
            emit('saved', response.data.asset);
            emit('update:open', false);
        }
    } catch (error) {
        errorMessage.value =
            Object.values(error?.response?.data?.errors || {})?.[0]?.[0] ||
            error?.response?.data?.message ||
            error?.message ||
            t('media.editor.failed', 'The edited image could not be saved.');
    } finally {
        processing.value = false;
    }
}

function payload() {
    const data = {
        context_type: props.uploadContextType || null,
        context_id: props.uploadContextId || null,
        max_width: numericValue(form.max_width, 0) || null,
        max_height: numericValue(form.max_height, 0) || null,
        grayscale: Boolean(form.grayscale),
        brightness: numericValue(form.brightness, 0),
        contrast: numericValue(form.contrast, 0),
        quality: numericValue(form.quality, 80),
        alt_text: props.asset?.alt_text || null,
        caption: props.asset?.caption || null,
    };

    const coordinates = normalizeCoordinates(
        cropper.value?.getResult?.()?.coordinates || cropCoordinates.value,
    );
    if (coordinates) {
        data.crop = {
            x: coordinates.left,
            y: coordinates.top,
            width: coordinates.width,
            height: coordinates.height,
        };
    }

    return data;
}

function resetForm() {
    Object.assign(form, defaultForm());
    selectedAspectRatio.value = 'free';
    cropCoordinates.value = null;
    errorMessage.value = '';
    nextTick(() => cropper.value?.refresh?.());
}

function defaultForm() {
    return {
        max_width: '',
        max_height: '',
        grayscale: false,
        brightness: 0,
        contrast: 0,
        quality: 80,
    };
}

function normalizeCoordinates(coordinates) {
    if (!coordinates) {
        return null;
    }

    return {
        left: Math.max(0, Math.round(Number(coordinates.left ?? 0))),
        top: Math.max(0, Math.round(Number(coordinates.top ?? 0))),
        width: Math.max(1, Math.round(Number(coordinates.width ?? 0))),
        height: Math.max(1, Math.round(Number(coordinates.height ?? 0))),
    };
}

function sizeLabel(width, height) {
    const safeWidth = numericValue(width, 0);
    const safeHeight = numericValue(height, 0);

    return safeWidth > 0 && safeHeight > 0
        ? `${safeWidth} x ${safeHeight} px`
        : '-';
}

function numericValue(value, fallback) {
    const number = Number(value);

    return Number.isFinite(number) ? Math.round(number) : fallback;
}
</script>

<style scoped>
.cms-image-editor-cropper :deep(.vue-advanced-cropper__image) {
    filter: var(--cms-image-editor-filter);
}
</style>
