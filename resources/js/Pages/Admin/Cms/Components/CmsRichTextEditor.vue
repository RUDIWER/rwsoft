<template>
    <div class="rw-cms-rich-text-editor grid gap-2">
        <div
            :class="[
                'overflow-hidden rounded-md border border-slate-300 bg-white text-sm focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100',
                required ? 'bg-yellow-50' : '',
            ]"
        >
            <textarea
                ref="textareaRef"
                :value="modelValue"
                :placeholder="placeholder"
                :disabled="disabled"
            />
        </div>

        <Dialog v-model:open="mediaDialogOpen">
            <DialogContent
                class="flex max-h-[calc(100vh-2rem)] max-w-7xl flex-col overflow-hidden p-0 shadow-none"
            >
                <DialogHeader
                    class="shrink-0 border-b border-slate-200 px-6 py-4"
                >
                    <DialogTitle>
                        {{
                            t('components.media_picker.choose', 'Choose image')
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'components.block_editor.rich_text_media_help',
                                'Choose an image from the media library to insert it in the rich text.',
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
                        uploaded-from="rich_text_media"
                        :upload-context-type="uploadContextType"
                        :upload-context-id="uploadContextId"
                        @update:assets="updateAssets"
                        @update:folders="updateFolders"
                        @select="insertSelectedAsset"
                    />
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<script setup>
import 'jodit/es2021/jodit.min.css';
import { Jodit } from 'jodit/esm/index.js';
import 'jodit/esm/plugins/all.js';
import CmsMediaPickerPanel from '@/Pages/Admin/Cms/Components/CmsMediaPickerPanel.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    height: { type: Number, default: 260 },
    mediaOptions: { type: Array, default: () => [] },
    mediaFolders: { type: Array, default: () => [] },
    uploadContextType: { type: String, default: '' },
    uploadContextId: { type: [Number, String], default: null },
});

const emit = defineEmits([
    'update:modelValue',
    'update:mediaOptions',
    'update:mediaFolders',
    'blur',
]);

const textareaRef = ref(null);
const mediaDialogOpen = ref(false);
const localAssets = ref([...props.mediaOptions]);
const localFolders = ref([...props.mediaFolders]);
const selectedMediaAssetId = ref(null);
let editor = null;
let syncingFromProps = false;

onMounted(async () => {
    await nextTick();

    if (!textareaRef.value) {
        return;
    }

    editor = Jodit.make(textareaRef.value, {
        buttons: [
            'source',
            'paragraph',
            '|',
            'bold',
            'italic',
            'underline',
            '|',
            'ul',
            'ol',
            '|',
            'link',
            'insertCmsMedia',
            'table',
            'hr',
            '|',
            'undo',
            'redo',
        ],
        readonly: props.disabled,
        height: props.height,
        placeholder: props.placeholder,
        showCharsCounter: false,
        showWordsCounter: false,
        showXPathInStatusbar: false,
        askBeforePasteHTML: false,
        askBeforePasteFromWord: false,
        defaultActionOnPaste: 'insert_clear_html',
        controls: {
            paragraph: {
                list: {
                    p: t(
                        'components.block_editor.paragraph_normal',
                        'Normal text',
                    ),
                    h2: t('components.block_editor.heading_2', 'Heading 2'),
                    h3: t('components.block_editor.heading_3', 'Heading 3'),
                    h4: t('components.block_editor.heading_4', 'Heading 4'),
                    h5: t('components.block_editor.heading_5', 'Heading 5'),
                    h6: t('components.block_editor.heading_6', 'Heading 6'),
                },
            },
            insertCmsMedia: {
                icon: 'image',
                tooltip: t(
                    'components.block_editor.insert_media',
                    'Insert media image',
                ),
                exec: () => {
                    mediaDialogOpen.value = true;
                },
            },
        },
    });

    editor.value = props.modelValue || '';
    editor.events.on('change', (value) => {
        if (!syncingFromProps) {
            emit('update:modelValue', String(value || ''));
        }
    });
    editor.events.on('blur', () => emit('blur'));
});

onUnmounted(() => {
    editor?.destruct();
    editor = null;
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor || editor.value === String(value || '')) {
            return;
        }

        syncingFromProps = true;
        editor.value = String(value || '');
        syncingFromProps = false;
    },
);

watch(
    () => props.disabled,
    (disabled) => {
        editor?.setReadOnly(Boolean(disabled));
    },
);

watch(
    () => props.mediaOptions,
    (assets) => {
        localAssets.value = [...assets];
    },
);

watch(
    () => props.mediaFolders,
    (folders) => {
        localFolders.value = [...folders];
    },
);

function updateAssets(assets) {
    localAssets.value = [...assets];
    emit('update:mediaOptions', localAssets.value);
}

function updateFolders(folders) {
    localFolders.value = [...folders];
    emit('update:mediaFolders', localFolders.value);
}

function insertSelectedAsset(asset) {
    if (!editor || !asset?.url) {
        return;
    }

    selectedMediaAssetId.value = asset.id;
    editor.selection.insertHTML(mediaFigureHtml(asset));
    editor.synchronizeValues();
    emit('update:modelValue', String(editor.value || ''));
    mediaDialogOpen.value = false;
}

function mediaFigureHtml(asset) {
    const url = escapeAttribute(asset.url);
    const alt = escapeAttribute(assetAltText(asset));
    const caption = assetCaption(asset);
    const width = positiveInteger(asset.width);
    const height = positiveInteger(asset.height);
    const sizeAttributes = [
        width ? `width="${width}"` : '',
        height ? `height="${height}"` : '',
    ]
        .filter(Boolean)
        .join(' ');
    const image = `<img src="${url}" alt="${alt}" loading="lazy"${sizeAttributes ? ` ${sizeAttributes}` : ''}>`;

    if (!caption) {
        return `<figure>${image}</figure>`;
    }

    return `<figure>${image}<figcaption>${escapeHtml(caption)}</figcaption></figure>`;
}

function assetAltText(asset) {
    return (
        asset.alt_text ||
        asset.original_filename ||
        asset.filename ||
        asset.path ||
        ''
    );
}

function assetCaption(asset) {
    return String(asset.caption || '').trim();
}

function positiveInteger(value) {
    const number = Number(value || 0);

    return Number.isInteger(number) && number > 0 ? number : null;
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeAttribute(value) {
    return escapeHtml(value).replace(/`/g, '&#096;');
}
</script>

<style scoped>
.rw-cms-rich-text-editor :deep(.jodit-wysiwyg) {
    color: #0f172a;
    font-size: 0.95rem;
    line-height: 1.65;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg > * + *) {
    margin-top: 0.75rem;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg h2),
.rw-cms-rich-text-editor :deep(.jodit-wysiwyg h3),
.rw-cms-rich-text-editor :deep(.jodit-wysiwyg h4),
.rw-cms-rich-text-editor :deep(.jodit-wysiwyg h5),
.rw-cms-rich-text-editor :deep(.jodit-wysiwyg h6) {
    color: #0f172a;
    font-weight: 700;
    line-height: 1.2;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg h2) {
    font-size: 1.5rem;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg h3) {
    font-size: 1.25rem;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg h4) {
    font-size: 1.125rem;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg h5),
.rw-cms-rich-text-editor :deep(.jodit-wysiwyg h6) {
    font-size: 1rem;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg strong),
.rw-cms-rich-text-editor :deep(.jodit-wysiwyg b) {
    font-weight: 700;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg em),
.rw-cms-rich-text-editor :deep(.jodit-wysiwyg i) {
    font-style: italic;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg ul),
.rw-cms-rich-text-editor :deep(.jodit-wysiwyg ol) {
    margin-left: 1.5rem;
    padding-left: 1rem;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg ul) {
    list-style-type: disc;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg ol) {
    list-style-type: decimal;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg blockquote) {
    border-left: 3px solid #bfdbfe;
    color: #334155;
    font-style: italic;
    margin-left: 0;
    padding-left: 1rem;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg a) {
    color: #1d4ed8;
    text-decoration: underline;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg figure) {
    margin: 0;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg figcaption) {
    color: #64748b;
    font-size: 0.85rem;
    margin-top: 0.35rem;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg table) {
    border-collapse: collapse;
    width: 100%;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg th),
.rw-cms-rich-text-editor :deep(.jodit-wysiwyg td) {
    border: 1px solid #cbd5e1;
    padding: 0.5rem;
}

.rw-cms-rich-text-editor :deep(.jodit-wysiwyg th) {
    background: #f8fafc;
    font-weight: 700;
}
</style>
