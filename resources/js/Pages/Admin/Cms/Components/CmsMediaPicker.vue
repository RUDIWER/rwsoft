<template>
    <div class="grid gap-3">
        <div
            v-if="selectedAsset"
            class="overflow-hidden rounded-lg border border-slate-200 bg-white"
        >
            <div class="aspect-video bg-slate-100">
                <img
                    :src="selectedAsset.url"
                    :alt="assetLabel(selectedAsset)"
                    class="h-full w-full object-cover"
                    :style="previewImageStyle"
                />
            </div>
            <div class="grid gap-1 p-3 text-sm">
                <div class="truncate font-medium text-slate-900">
                    {{ assetLabel(selectedAsset) }}
                </div>
                <div class="text-xs text-slate-500">
                    {{ selectedAsset.width || '?' }} x
                    {{ selectedAsset.height || '?' }} px
                </div>
            </div>
        </div>

        <div
            v-else
            class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500"
        >
            {{
                t(
                    'components.media_picker.empty',
                    'Geen afbeelding geselecteerd.',
                )
            }}
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                variant="outline"
                class="shadow-none"
                @click="dialogOpen = true"
            >
                {{ t('components.media_picker.choose', 'Afbeelding kiezen') }}
            </Button>
            <Button
                v-if="selectedAsset"
                type="button"
                variant="outline"
                class="shadow-none"
                :disabled="!selectedAssetCanBeEdited"
                :title="selectedAssetEditTitle"
                @click="openEditor"
            >
                {{ t('media.editor.button', 'Edit copy') }}
            </Button>
            <Button
                v-if="selectedAsset"
                type="button"
                variant="ghost"
                @click="clearSelection"
            >
                {{ t('components.media_picker.clear', 'Leegmaken') }}
            </Button>
        </div>

        <p
            v-if="selectedAsset && !selectedAssetCanBeEdited"
            class="text-xs text-orange-700"
        >
            {{ selectedAssetEditTitle }}
        </p>

        <Dialog v-model:open="dialogOpen">
            <DialogContent
                class="flex max-h-[calc(100vh-2rem)] max-w-7xl flex-col overflow-hidden p-0 shadow-none"
            >
                <DialogHeader
                    class="shrink-0 border-b border-slate-200 px-6 py-4"
                >
                    <DialogTitle>{{
                        t('components.media_picker.choose', 'Afbeelding kiezen')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'components.media_picker.description',
                                'Kies een bestaande afbeelding of upload direct een nieuwe afbeelding.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="min-h-0 flex-1 overflow-hidden px-6 py-5">
                    <CmsMediaPickerPanel
                        v-model="selectedMediaAssetId"
                        class="h-[calc(100vh-14rem)] max-h-full"
                        :assets="localAssets"
                        :folders="localFolders"
                        :uploaded-from="uploadedFrom"
                        :upload-context-type="uploadContextType"
                        :upload-context-id="uploadContextId"
                        @update:assets="updateAssets"
                        @update:folders="updateFolders"
                        @select="selectAsset"
                    />
                </div>
            </DialogContent>
        </Dialog>

        <CmsImageEditorDialog
            v-model:open="editorOpen"
            :asset="selectedAsset"
            :upload-context-type="uploadContextType"
            :upload-context-id="uploadContextId"
            @saved="selectEditedAsset"
        />
    </div>
</template>

<script setup>
import { Button } from '@/components/ui/button';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import CmsImageEditorDialog from '@/Pages/Admin/Cms/Components/CmsImageEditorDialog.vue';
import CmsMediaPickerPanel from '@/Pages/Admin/Cms/Components/CmsMediaPickerPanel.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { computed, ref, watch } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: null,
    },
    assets: {
        type: Array,
        required: true,
    },
    folders: {
        type: Array,
        default: () => [],
    },
    previewOpacity: {
        type: Number,
        default: 100,
    },
    uploadedFrom: {
        type: String,
        default: 'media_picker',
    },
    uploadContextType: {
        type: String,
        default: '',
    },
    uploadContextId: {
        type: [Number, String],
        default: null,
    },
});

const emit = defineEmits([
    'update:modelValue',
    'update:assets',
    'update:folders',
]);

const dialogOpen = ref(false);
const editorOpen = ref(false);
const localAssets = ref([...props.assets]);
const localFolders = ref([...props.folders]);

const selectedAsset = computed(() =>
    localAssets.value.find(
        (asset) => Number(asset.id) === Number(props.modelValue),
    ),
);

const selectedAssetCanBeEdited = computed(() =>
    ['jpg', 'jpeg', 'png', 'webp'].includes(
        String(selectedAsset.value?.extension || '').toLowerCase(),
    ),
);

const selectedAssetEditTitle = computed(() =>
    selectedAssetCanBeEdited.value
        ? t('media.editor.button', 'Edit copy')
        : t(
              'media.editor.unsupported_format',
              'Only JPG, PNG and WebP images can be edited.',
          ),
);

const previewImageStyle = computed(() => ({
    opacity: String(normalizeOpacity(props.previewOpacity) / 100),
}));

const selectedMediaAssetId = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

watch(
    () => props.assets,
    (assets) => {
        localAssets.value = [...assets];
    },
);

watch(
    () => props.folders,
    (folders) => {
        localFolders.value = [...folders];
    },
);

function assetLabel(asset) {
    return (
        asset.alt_text ||
        asset.original_filename ||
        asset.filename ||
        asset.path
    );
}

function normalizeOpacity(value) {
    const opacity = Number(value ?? 100);

    if (!Number.isFinite(opacity)) {
        return 100;
    }

    return Math.min(100, Math.max(0, Math.round(opacity)));
}

function selectAsset(asset) {
    emit('update:modelValue', asset.id);
    dialogOpen.value = false;
}

function clearSelection() {
    emit('update:modelValue', null);
}

function openEditor() {
    if (!selectedAssetCanBeEdited.value) {
        return;
    }

    editorOpen.value = true;
}

function selectEditedAsset(asset) {
    updateAssets([
        asset,
        ...localAssets.value.filter(
            (item) => Number(item.id) !== Number(asset.id),
        ),
    ]);
    emit('update:modelValue', asset.id);
}

function updateAssets(assets) {
    localAssets.value = [...assets];
    emit('update:assets', localAssets.value);
}

function updateFolders(folders) {
    localFolders.value = [...folders];
    emit('update:folders', localFolders.value);
}
</script>
