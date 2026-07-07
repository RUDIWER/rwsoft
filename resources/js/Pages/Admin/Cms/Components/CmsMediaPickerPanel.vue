<template>
    <div
        class="grid min-h-0 overflow-hidden rounded-lg border border-slate-200 lg:grid-cols-[260px_minmax(0,1fr)]"
    >
        <aside
            class="min-h-0 overflow-hidden border-b border-slate-200 bg-slate-50 p-3 lg:border-b-0 lg:border-r"
        >
            <div class="grid gap-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">
                            {{ t('media.folders.title', 'Folders') }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{
                                t(
                                    'media.folders.description',
                                    'Select a folder or create a nested structure.',
                                )
                            }}
                        </p>
                    </div>
                    <Button
                        v-if="folderEnabled"
                        type="button"
                        variant="outline"
                        size="icon"
                        class="h-8 w-8 shrink-0 shadow-none"
                        :title="t('media.folders.create_root', 'Create folder')"
                        :aria-label="
                            t('media.folders.create_root', 'Create folder')
                        "
                        @click="openCreateFolder(null)"
                    >
                        <span
                            class="mdi mdi-folder-plus-outline text-base"
                            aria-hidden="true"
                        />
                    </Button>
                </div>

                <div class="grid max-h-[55vh] gap-1 overflow-auto pr-1">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-md px-2 py-2 text-left text-sm transition"
                        :class="
                            selectedFolderId === 'all'
                                ? selectedFolderClass
                                : folderClass
                        "
                        @click="selectFolder('all')"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <span
                                class="mdi mdi-folder-multiple-outline text-base"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{
                                t('media.folders.all_media', 'All media')
                            }}</span>
                        </span>
                        <span class="text-xs">{{ localAssets.length }}</span>
                    </button>

                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-md px-2 py-2 text-left text-sm transition"
                        :class="
                            selectedFolderId === 'none'
                                ? selectedFolderClass
                                : folderClass
                        "
                        @click="selectFolder('none')"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <span
                                class="mdi mdi-folder-off-outline text-base"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{
                                t(
                                    'media.folders.without_folder',
                                    'Without folder',
                                )
                            }}</span>
                        </span>
                        <span class="text-xs">{{ withoutFolderCount }}</span>
                    </button>

                    <div
                        v-for="folder in folderRows"
                        :key="folder.id"
                        class="group flex items-center gap-1"
                        :class="depthClass(folder.depth)"
                    >
                        <button
                            type="button"
                            class="flex min-w-0 flex-1 items-center justify-between rounded-md px-2 py-2 text-left text-sm transition"
                            :class="
                                String(selectedFolderId) === String(folder.id)
                                    ? selectedFolderClass
                                    : folderDropClass(folder)
                            "
                            @click="selectFolder(String(folder.id))"
                            @dragenter.prevent="onFolderDragOver(folder)"
                            @dragover.prevent="onFolderDragOver(folder, $event)"
                            @dragleave="onFolderDragLeave(folder)"
                            @drop.prevent="onFolderDrop(folder)"
                        >
                            <span class="flex min-w-0 items-center gap-2">
                                <span
                                    class="mdi mdi-folder-outline text-base"
                                    aria-hidden="true"
                                />
                                <span class="truncate">{{ folder.name }}</span>
                            </span>
                            <span class="text-xs">{{
                                folderAssetCount(folder.id)
                            }}</span>
                        </button>

                        <Button
                            v-if="folderEnabled"
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 opacity-100 lg:opacity-0 lg:group-hover:opacity-100"
                            :title="
                                t(
                                    'media.folders.create_subfolder',
                                    'Create subfolder',
                                )
                            "
                            :aria-label="
                                t(
                                    'media.folders.create_subfolder',
                                    'Create subfolder',
                                )
                            "
                            @click="openCreateFolder(folder)"
                        >
                            <span
                                class="mdi mdi-folder-plus-outline text-base"
                                aria-hidden="true"
                            />
                        </Button>
                    </div>
                </div>
            </div>
        </aside>

        <section
            class="grid min-h-0 grid-rows-[auto_auto_minmax(0,1fr)] overflow-hidden bg-white"
        >
            <div
                v-if="localFlash.message"
                class="border-b border-slate-200 p-3"
            >
                <div
                    class="rounded-md border px-3 py-2 text-sm"
                    :class="
                        localFlash.type === 'danger'
                            ? 'border-red-200 bg-red-50 text-red-700'
                            : 'border-green-200 bg-green-50 text-green-700'
                    "
                >
                    {{ localFlash.message }}
                </div>
            </div>

            <div class="grid gap-3 border-b border-slate-200 p-3">
                <label
                    v-if="uploadEnabled"
                    :for="fileInputId"
                    class="grid cursor-pointer gap-2 rounded-lg border border-dashed p-4 text-center transition"
                    :class="
                        dragOver
                            ? 'border-blue-400 bg-blue-50'
                            : 'border-slate-300 bg-slate-50 hover:border-blue-300 hover:bg-blue-50/50'
                    "
                    role="button"
                    tabindex="0"
                    @keydown.enter.prevent="openFilePicker"
                    @keydown.space.prevent="openFilePicker"
                    @dragenter.prevent="dragOver = true"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="onDropFiles"
                >
                    <input
                        :id="fileInputId"
                        ref="fileInput"
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="onFileInputChange"
                    />
                    <span
                        class="mdi mdi-cloud-upload-outline text-2xl text-blue-700"
                        aria-hidden="true"
                    />
                    <div class="grid gap-1">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ t('media.upload.title', 'Upload images') }}
                        </p>
                        <p class="text-xs text-slate-600">
                            {{
                                t(
                                    'media.upload.target_folder_inline',
                                    'Target folder: :folder',
                                    { folder: selectedFolderLabel },
                                )
                            }}
                        </p>
                    </div>
                </label>

                <div
                    v-if="visibleUploadItems.length > 0"
                    class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <div
                        v-for="item in visibleUploadItems"
                        :key="item.key"
                        class="grid gap-2 rounded-md border bg-white p-2 text-sm"
                        :class="
                            item.status === 'error'
                                ? 'border-red-200'
                                : 'border-slate-200'
                        "
                    >
                        <div class="flex items-center gap-2">
                            <img
                                :src="uploadPreview(item)"
                                alt=""
                                class="h-10 w-10 rounded object-cover"
                            />
                            <div class="min-w-0 flex-1">
                                <div
                                    class="truncate font-medium text-slate-900"
                                >
                                    {{ item.fileName }}
                                </div>
                                <div
                                    class="text-xs"
                                    :class="uploadStatusClass(item)"
                                >
                                    {{ uploadStatusLabel(item) }}
                                </div>
                            </div>
                        </div>
                        <progress
                            v-if="item.status === 'uploading'"
                            class="h-2 w-full"
                            :value="item.progress"
                            max="100"
                        />
                        <div class="flex justify-end gap-2">
                            <Button
                                v-if="item.status === 'error'"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="shadow-none"
                                @click="retryUpload(item)"
                            >
                                {{ t('media.upload.retry', 'Retry') }}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="removeUploadItem(item)"
                            >
                                {{ t('media.upload.remove', 'Remove') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="grid min-h-0 grid-rows-[auto_minmax(0,1fr)] overflow-hidden"
            >
                <div
                    class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 p-3"
                >
                    <div class="grid gap-1">
                        <Label
                            for="media_picker_search"
                            class="text-[11px] text-slate-600"
                        >
                            {{ t('media.filters.search', 'Search') }}
                        </Label>
                        <Input
                            id="media_picker_search"
                            v-model="searchTerm"
                            type="search"
                            class="w-full sm:w-80"
                            :placeholder="
                                t(
                                    'media.filters.search_placeholder',
                                    'Search filename, alt text or caption',
                                )
                            "
                        />
                    </div>

                    <div class="grid gap-1">
                        <Label class="text-[11px] text-slate-600">
                            {{ t('media.sort.title', 'Sort') }}
                        </Label>
                        <div
                            class="inline-flex rounded-md border border-slate-200 bg-white p-1"
                        >
                            <button
                                type="button"
                                :class="sortButtonClass('az')"
                                :title="t('media.sort.az', 'A-Z')"
                                @click="sortMode = 'az'"
                            >
                                <span
                                    class="mdi mdi-sort-alphabetical-ascending text-base"
                                    aria-hidden="true"
                                />
                            </button>
                            <button
                                type="button"
                                :class="sortButtonClass('za')"
                                :title="t('media.sort.za', 'Z-A')"
                                @click="sortMode = 'za'"
                            >
                                <span
                                    class="mdi mdi-sort-alphabetical-descending text-base"
                                    aria-hidden="true"
                                />
                            </button>
                            <button
                                type="button"
                                :class="sortButtonClass('custom')"
                                :title="t('media.sort.custom', 'Custom order')"
                                @click="sortMode = 'custom'"
                            >
                                <span
                                    class="mdi mdi-sort-variant-remove text-base"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="min-h-0 overflow-y-auto overscroll-contain p-3">
                    <div
                        v-if="visibleAssets.length > 0"
                        class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
                    >
                        <button
                            v-for="asset in visibleAssets"
                            :key="asset.id"
                            type="button"
                            class="group overflow-hidden rounded-md border bg-white text-left transition hover:border-blue-300 hover:shadow-sm"
                            :class="
                                Number(modelValue) === Number(asset.id)
                                    ? 'border-blue-500 ring-2 ring-blue-100'
                                    : 'border-slate-200'
                            "
                            :draggable="moveEnabled"
                            @click="selectAsset(asset)"
                            @dragstart="onAssetDragStart(asset, $event)"
                            @dragend="onAssetDragEnd"
                        >
                            <div class="aspect-square bg-slate-100">
                                <img
                                    :src="asset.url"
                                    :alt="assetLabel(asset)"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                            <div class="grid gap-1 p-2 text-sm">
                                <div
                                    class="truncate font-medium text-slate-900"
                                >
                                    {{ assetLabel(asset) }}
                                </div>
                                <div
                                    class="flex items-center justify-between gap-2 text-xs text-slate-500"
                                >
                                    <span
                                        >{{ asset.width || '?' }} x
                                        {{ asset.height || '?' }}</span
                                    >
                                    <span
                                        v-if="moveEnabled"
                                        class="mdi mdi-drag text-base"
                                        aria-hidden="true"
                                    />
                                </div>
                            </div>
                        </button>
                    </div>

                    <p v-else class="text-sm text-slate-500">
                        {{
                            t(
                                'components.media_picker.empty_search',
                                'Geen media gevonden.',
                            )
                        }}
                    </p>
                </div>
            </div>
        </section>

        <Dialog v-model:open="createDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="border-b border-slate-200 pb-4">
                    <DialogTitle>{{
                        t('media.folders.create_dialog_title', 'Create folder')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        createFolderDescription
                    }}</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitCreateFolder">
                    <div class="grid gap-2">
                        <Label for="media_picker_create_folder_name">
                            {{ t('media.folders.name', 'Folder name') }}
                        </Label>
                        <Input
                            id="media_picker_create_folder_name"
                            v-model="createFolderName"
                        />
                        <p
                            v-if="folderDialogError"
                            class="text-sm text-red-600"
                        >
                            {{ folderDialogError }}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            variant="outline"
                            class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                            :disabled="folderDialogProcessing"
                        >
                            <span
                                class="mdi mdi-content-save text-base text-green-700"
                                aria-hidden="true"
                            />
                            {{ commonT('actions.save', 'Save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
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
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    assets: { type: Array, required: true },
    folders: { type: Array, default: () => [] },
    uploadEnabled: { type: Boolean, default: true },
    folderEnabled: { type: Boolean, default: true },
    moveEnabled: { type: Boolean, default: true },
    autoSelectUploads: { type: Boolean, default: true },
    uploadedFrom: { type: String, default: 'media_picker' },
    uploadContextType: { type: String, default: '' },
    uploadContextId: { type: [Number, String], default: null },
});

const emit = defineEmits([
    'update:modelValue',
    'update:assets',
    'update:folders',
    'select',
]);

const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');

const localAssets = ref([...props.assets]);
const localFolders = ref([...props.folders]);
const selectedFolderId = ref('all');
const sortMode = ref('custom');
const searchTerm = ref('');
const dragOver = ref(false);
const fileInput = ref(null);
const uploadItems = ref([]);
const activeUploads = ref(0);
const draggedAssetId = ref(null);
const dragOverFolderId = ref(null);
const createDialogOpen = ref(false);
const createFolderParentId = ref(null);
const createFolderName = ref('');
const folderDialogProcessing = ref(false);
const folderDialogError = ref('');
const localFlash = ref({ type: '', message: '' });
const maxConcurrentUploads = 3;
const fileInputId = `cms-media-upload-${Date.now()}-${Math.random().toString(36).slice(2)}`;

const selectedFolderClass = 'bg-blue-100 text-blue-800 ring-1 ring-blue-200';
const folderClass = 'text-slate-700 hover:bg-white hover:text-slate-950';

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

onBeforeUnmount(() => {
    uploadItems.value.forEach((item) => {
        if (item.localUrl) {
            URL.revokeObjectURL(item.localUrl);
        }
    });
});

const folderRows = computed(() => flattenFolders(null, 0));
const withoutFolderCount = computed(() => folderAssetCount(null));

const selectedFolderLabel = computed(() => {
    if (selectedFolderId.value === 'all') {
        return t('media.folders.all_media', 'All media');
    }

    if (selectedFolderId.value === 'none') {
        return t('media.folders.without_folder', 'Without folder');
    }

    return (
        folderById(Number(selectedFolderId.value))?.name ??
        t('media.no_folder', 'No folder')
    );
});

const createFolderDescription = computed(() => {
    if (!createFolderParentId.value) {
        return t(
            'media.folders.create_root_description',
            'Create a new folder at root level.',
        );
    }

    return t(
        'media.folders.create_child_description',
        'Create a subfolder under :name.',
        {
            name: folderById(Number(createFolderParentId.value))?.name ?? '-',
        },
    );
});

const visibleUploadItems = computed(() =>
    uploadItems.value.filter((item) =>
        ['queued', 'uploading', 'error'].includes(item.status),
    ),
);

const filteredAssets = computed(() => {
    if (selectedFolderId.value === 'none') {
        return localAssets.value.filter((asset) => !asset.folder_id);
    }

    if (selectedFolderId.value !== 'all') {
        return localAssets.value.filter(
            (asset) =>
                Number(asset.folder_id) === Number(selectedFolderId.value),
        );
    }

    return localAssets.value;
});

const visibleAssets = computed(() => {
    const term = searchTerm.value.trim().toLowerCase();
    let assets = filteredAssets.value;

    if (term) {
        assets = assets.filter((asset) =>
            [
                asset.alt_text,
                asset.caption,
                asset.original_filename,
                asset.filename,
                asset.path,
            ]
                .filter(Boolean)
                .some((value) => value.toString().toLowerCase().includes(term)),
        );
    }

    return [...assets].sort((left, right) => compareAssets(left, right));
});

function selectAsset(asset) {
    emit('update:modelValue', asset.id);
    emit('select', asset);
}

function selectFolder(value) {
    selectedFolderId.value = value;
}

function openCreateFolder(parentFolder = null) {
    createFolderParentId.value = parentFolder?.id ?? null;
    createFolderName.value = '';
    folderDialogError.value = '';
    createDialogOpen.value = true;
}

async function submitCreateFolder() {
    folderDialogProcessing.value = true;
    folderDialogError.value = '';

    try {
        const response = await axios.post(
            route('admin.cms.media-folders.store'),
            {
                name: createFolderName.value,
                parent_id: createFolderParentId.value,
            },
            { headers: { Accept: 'application/json' } },
        );

        updateLocalFolder(response.data.folder);
        selectedFolderId.value = String(response.data.folder.id);
        createDialogOpen.value = false;
        localFlash.value = {
            type: 'success',
            message: t(
                'media.folders.created_flash',
                'Folder created successfully.',
            ),
        };
    } catch (error) {
        folderDialogError.value =
            error?.response?.data?.errors?.name?.[0] ||
            error?.response?.data?.message ||
            t('media.folders.create_failed', 'Creating folder failed.');
    } finally {
        folderDialogProcessing.value = false;
    }
}

function openFilePicker() {
    fileInput.value?.click();
}

function onFileInputChange(event) {
    addFiles(Array.from(event.target.files || []));
    event.target.value = '';
}

function onDropFiles(event) {
    dragOver.value = false;
    addFiles(Array.from(event.dataTransfer?.files || []));
}

function addFiles(files) {
    files
        .filter((file) => file.type.startsWith('image/'))
        .forEach((file) => {
            uploadItems.value.push({
                key: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
                file,
                fileName: file.name,
                localUrl: URL.createObjectURL(file),
                previewUrl: '',
                asset: null,
                folderId: Number.isInteger(Number(selectedFolderId.value))
                    ? Number(selectedFolderId.value)
                    : null,
                status: 'queued',
                progress: 0,
                error: '',
            });
        });

    processUploadQueue();
}

function processUploadQueue() {
    while (activeUploads.value < maxConcurrentUploads) {
        const nextItem = uploadItems.value.find(
            (item) => item.status === 'queued',
        );

        if (!nextItem) {
            return;
        }

        startDirectUpload(nextItem);
    }
}

async function startDirectUpload(item) {
    activeUploads.value += 1;
    item.status = 'uploading';
    item.progress = 0;
    item.error = '';

    const payload = new FormData();
    payload.append('file', item.file);
    payload.append('uploaded_from', props.uploadedFrom);
    if (props.uploadContextType && props.uploadContextId) {
        payload.append('context_type', props.uploadContextType);
        payload.append('context_id', props.uploadContextId);
    }
    if (item.folderId) {
        payload.append('folder_id', item.folderId);
    }

    try {
        const response = await axios.post(
            route('admin.cms.media.store'),
            payload,
            {
                headers: { Accept: 'application/json' },
                onUploadProgress: (event) => {
                    if (event.total) {
                        item.progress = Math.round(
                            (event.loaded / event.total) * 100,
                        );
                    }
                },
            },
        );
        const asset = response.data?.asset ?? null;
        const folders = Array.isArray(response.data?.folders)
            ? response.data.folders
            : null;

        if (folders) {
            localFolders.value = [...folders];
            emit('update:folders', [...localFolders.value]);
        }

        item.asset = asset;
        item.previewUrl = asset?.url ?? '';
        item.status = response.data?.already_exists ? 'duplicate' : 'saved';
        item.progress = 100;

        if (asset) {
            updateLocalAsset(asset);
            if (props.autoSelectUploads) {
                selectAsset(asset);
            }
        }

        removeUploadItem(item);
    } catch (error) {
        item.status = 'error';
        item.error = resolveUploadError(error);
    } finally {
        activeUploads.value -= 1;
        processUploadQueue();
    }
}

function resolveUploadError(error) {
    const errors = error?.response?.data?.errors || {};
    const fieldError = errors.file?.[0] || errors.folder_id?.[0];

    if (fieldError) {
        return fieldError;
    }

    if (error?.response?.status === 413) {
        return t(
            'media.upload.too_large_server',
            'The file is larger than the server upload limit.',
        );
    }

    return (
        error?.response?.data?.message ||
        t('media.upload.failed_message', 'Upload failed.')
    );
}

function uploadPreview(item) {
    return item.previewUrl || item.localUrl || '';
}

function uploadStatusLabel(item) {
    return (
        {
            queued: t('media.upload.status_queued', 'Queued'),
            uploading: t('media.upload.status_uploading', 'Uploading'),
            saved: t('media.upload.status_saved', 'Saved'),
            duplicate: t('media.upload.status_duplicate', 'Already in library'),
            error: item.error || t('media.upload.status_error', 'Failed'),
        }[item.status] ?? item.status
    );
}

function uploadStatusClass(item) {
    return item.status === 'error' ? 'text-red-600' : 'text-slate-500';
}

function retryUpload(item) {
    item.status = 'queued';
    item.error = '';
    item.progress = 0;
    processUploadQueue();
}

function removeUploadItem(item) {
    if (item.localUrl) {
        URL.revokeObjectURL(item.localUrl);
    }

    uploadItems.value = uploadItems.value.filter(
        (uploadItem) => uploadItem.key !== item.key,
    );
}

function onAssetDragStart(asset, event) {
    if (!props.moveEnabled) {
        return;
    }

    draggedAssetId.value = asset.id;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(asset.id));
}

function onAssetDragEnd() {
    draggedAssetId.value = null;
    dragOverFolderId.value = null;
}

function onFolderDragOver(folder, event = null) {
    if (!draggedAssetId.value) {
        return;
    }

    dragOverFolderId.value = folder.id;

    if (event?.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onFolderDragLeave(folder) {
    if (dragOverFolderId.value === folder.id) {
        dragOverFolderId.value = null;
    }
}

async function onFolderDrop(folder) {
    if (!draggedAssetId.value) {
        return;
    }

    const asset = localAssets.value.find(
        (item) => Number(item.id) === Number(draggedAssetId.value),
    );
    dragOverFolderId.value = null;

    if (!asset || Number(asset.folder_id) === Number(folder.id)) {
        onAssetDragEnd();
        return;
    }

    try {
        await moveAssetToFolder(asset, folder.id);
    } finally {
        onAssetDragEnd();
    }
}

async function moveAssetToFolder(asset, folderId) {
    try {
        const response = await axios.patch(
            route('admin.cms.media.metadata', { id: asset.id }),
            {
                folder_id: folderId,
                alt_text: asset.alt_text || null,
                caption: asset.caption || null,
                translations: normalizeMetadataTranslations(asset),
                sort_order: asset.sort_order ?? 0,
            },
            { headers: { Accept: 'application/json' } },
        );

        updateLocalAsset(response.data.asset);
        localFlash.value = {
            type: 'success',
            message: t(
                'media.move_to_folder_saved',
                'Image moved to folder :folder.',
                {
                    folder: folderById(Number(folderId))?.name ?? '-',
                },
            ),
        };
    } catch (error) {
        localFlash.value = {
            type: 'danger',
            message:
                error?.response?.data?.message ||
                t(
                    'media.move_to_folder_failed',
                    'Moving the image to this folder failed.',
                ),
        };
    }
}

function normalizeMetadataTranslations(asset) {
    const existing = asset.translations ?? {};
    const locales = Object.keys(existing);

    if (locales.length === 0) {
        return {};
    }

    return Object.fromEntries(
        locales.map((locale) => [
            locale,
            {
                alt_text:
                    existing[locale]?.alt_text ??
                    (locale === locales[0] ? asset.alt_text : '') ??
                    '',
                caption:
                    existing[locale]?.caption ??
                    (locale === locales[0] ? asset.caption : '') ??
                    '',
            },
        ]),
    );
}

function updateLocalAsset(asset) {
    const index = localAssets.value.findIndex(
        (item) => Number(item.id) === Number(asset.id),
    );

    if (index === -1) {
        localAssets.value.unshift(asset);
    } else {
        localAssets.value.splice(index, 1, asset);
    }

    emit('update:assets', [...localAssets.value]);
}

function updateLocalFolder(folder) {
    const index = localFolders.value.findIndex(
        (item) => Number(item.id) === Number(folder.id),
    );

    if (index === -1) {
        localFolders.value.push(folder);
    } else {
        localFolders.value.splice(index, 1, folder);
    }

    emit('update:folders', [...localFolders.value]);
}

function folderDropClass(folder) {
    if (dragOverFolderId.value === folder.id) {
        return 'bg-blue-50 text-blue-800 ring-1 ring-blue-200';
    }

    return folderClass;
}

function sortButtonClass(mode) {
    const base =
        'inline-flex h-8 w-8 items-center justify-center rounded text-sm transition';

    if (sortMode.value === mode) {
        return `${base} bg-blue-100 text-blue-700`;
    }

    return `${base} text-slate-600 hover:bg-slate-50 hover:text-slate-950`;
}

function compareAssets(left, right) {
    if (sortMode.value === 'az' || sortMode.value === 'za') {
        const compared = assetLabel(left).localeCompare(
            assetLabel(right),
            undefined,
            {
                sensitivity: 'base',
            },
        );

        return sortMode.value === 'za' ? compared * -1 : compared;
    }

    return (
        (left.sort_order ?? 0) - (right.sort_order ?? 0) ||
        (right.id ?? 0) - (left.id ?? 0)
    );
}

function flattenFolders(parentId, depth) {
    return localFolders.value
        .filter(
            (folder) => Number(folder.parent_id || 0) === Number(parentId || 0),
        )
        .sort(
            (left, right) =>
                (left.sort_order ?? 0) - (right.sort_order ?? 0) ||
                left.name.localeCompare(right.name),
        )
        .flatMap((folder) => [
            { ...folder, depth },
            ...flattenFolders(folder.id, depth + 1),
        ]);
}

function folderById(id) {
    return localFolders.value.find(
        (folder) => Number(folder.id) === Number(id),
    );
}

function folderAssetCount(folderId) {
    return localAssets.value.filter((asset) => {
        if (folderId === null) {
            return !asset.folder_id;
        }

        return Number(asset.folder_id) === Number(folderId);
    }).length;
}

function depthClass(depth) {
    return (
        ['pl-0', 'pl-4', 'pl-8', 'pl-12', 'pl-16', 'pl-20'][
            Math.min(depth, 5)
        ] ?? 'pl-20'
    );
}

function assetLabel(asset) {
    if (!asset) {
        return '-';
    }

    return (
        asset.alt_text ||
        asset.original_filename ||
        asset.filename ||
        asset.path ||
        `#${asset.id}`
    );
}
</script>
