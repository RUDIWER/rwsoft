<template>
    <AdminLayout :suppress-flash="true">
        <Head :title="pageTitle" />

        <form @submit.prevent="submit">
            <Card
                class="flex h-[calc(100vh-8rem)] flex-col overflow-hidden rounded-none shadow-none"
            >
                <CardHeader
                    class="shrink-0 gap-0 border-b border-slate-200 p-0"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-5"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                                aria-hidden="true"
                            >
                                <span
                                    class="mdi mdi-email-edit-outline text-2xl"
                                />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">
                                    {{ pageTitle }}
                                </CardTitle>
                                <CardDescription class="mt-1">
                                    {{
                                        t(
                                            'mail.template_description',
                                            'Define the reusable section layout and mail-safe blocks for emails.',
                                        )
                                    }}
                                </CardDescription>
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <AdminFormBackButton
                                :href="route('admin.cms.mail-templates.index')"
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="commonT('actions.back', 'Back')"
                                @save="submit"
                            />

                            <Button
                                v-if="isEditMode"
                                type="button"
                                variant="outline"
                                class="gap-2 border-slate-200 text-slate-700 shadow-none hover:bg-slate-50 hover:text-slate-900"
                                @click="showRevisionDialog = true"
                            >
                                <span
                                    class="mdi mdi-history text-base text-slate-700"
                                    aria-hidden="true"
                                />
                                {{ t('revisions.open', 'Versions') }}
                            </Button>

                            <AdminFormSaveButton
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="commonT('actions.save', 'Save')"
                            />
                        </div>
                    </div>
                </CardHeader>

                <div
                    class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 sm:px-5"
                >
                    <div class="font-medium text-slate-700">
                        {{ commonT('record_meta.id', 'ID') }}:
                        <span class="ml-1 text-base font-bold text-slate-950">
                            {{ recordIdLabel }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                        <div class="font-medium text-slate-700">
                            {{ commonT('record_meta.updated_at', 'Updated') }}:
                            <span
                                class="ml-1 text-base font-bold text-slate-950"
                            >
                                {{ updatedAtLabel }}
                            </span>
                        </div>
                        <div class="font-medium text-slate-700">
                            {{ commonT('record_meta.created_at', 'Created') }}:
                            <span
                                class="ml-1 text-base font-bold text-slate-950"
                            >
                                {{ createdAtLabel }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    v-if="pageFlash.message"
                    class="shrink-0 border-b border-slate-200 px-4 py-3 sm:px-5"
                >
                    <RwFlashMessage
                        :type="pageFlash.type"
                        :message="pageFlash.message"
                    />
                </div>

                <CardContent class="min-h-0 flex-1 overflow-hidden p-0">
                    <div class="border-b border-slate-200">
                        <div class="flex flex-wrap gap-4 px-4 sm:px-5">
                            <button
                                v-for="tab in tabOptions"
                                :key="tab.value"
                                type="button"
                                class="-mb-px border-b-2 px-1 py-2 text-sm font-medium transition"
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

                    <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">
                        <section
                            v-if="activeTab === 'basis'"
                            class="grid gap-5"
                        >
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label
                                        for="name"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ commonT('columns.name', 'Name') }}
                                    </Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        class="bg-yellow-50"
                                        required
                                    />
                                    <p
                                        v-if="form.errors.name"
                                        class="text-sm text-red-600"
                                    >
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label class="flex items-center gap-1">
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{
                                            t('mail.fields.context', 'Context')
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        v-model="form.context_key"
                                        class="bg-yellow-50"
                                        :items="contextOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        required-highlight-color="#fefce8"
                                    />
                                    <p
                                        v-if="form.errors.context_key"
                                        class="text-sm text-red-600"
                                    >
                                        {{ form.errors.context_key }}
                                    </p>
                                </div>
                            </div>

                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <Checkbox v-model:checked="form.is_active" />
                                {{ commonT('columns.active', 'Active') }}
                            </label>

                            <div class="grid gap-2">
                                <Label for="description">
                                    {{
                                        commonT(
                                            'columns.description',
                                            'Description',
                                        )
                                    }}
                                </Label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="3"
                                    class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                            </div>
                        </section>

                        <section v-else class="grid gap-2">
                            <CmsLayoutZoneEditor
                                :key="`${templateEditorKey}:mail-content`"
                                :model-value="form.sections.content"
                                zone="content"
                                :responsive-grid="true"
                                :title="
                                    t(
                                        'mail.builder.title',
                                        'Mail template layout',
                                    )
                                "
                                :description="
                                    t(
                                        'mail.builder.description',
                                        'Build the email body with mail-safe blocks. Use placement settings to define content keys and labels for the email editor.',
                                    )
                                "
                                :placeable-blocks="placeableBlocks"
                                :layout-locale="page.props?.app?.locale || 'en'"
                                :can-manage-code-blocks="false"
                                :saving="form.processing"
                                :placement-saving="form.processing"
                                v-model:media-options="localMediaOptions"
                                v-model:media-folders="localMediaFolders"
                                @save-requested="submit"
                                @placement-save-requested="submit"
                                @update:model-value="updateTemplateSections"
                            />
                            <p
                                v-if="form.errors.sections"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.sections }}
                            </p>
                        </section>
                    </div>
                </CardContent>
            </Card>
        </form>

        <CmsRevisionHistoryDialog
            v-if="isEditMode"
            v-model:open="showRevisionDialog"
            subject-type="mail_template"
            restore-route-name="admin.cms.mail-templates.revisions.restore"
            :restore-route-params="{ mailTemplate: mailTemplate.id }"
            :revisions="revisions"
            :impact-items-count="mailTemplate?.emails_count || 0"
        />
    </AdminLayout>
</template>

<script setup>
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CmsRevisionHistoryDialog from '@/Pages/Admin/Cms/Components/CmsRevisionHistoryDialog.vue';
import CmsLayoutZoneEditor from '@/Pages/Admin/Cms/Layouts/Partials/CmsLayoutZoneEditor.vue';
import { updatedLayoutSectionsForZone } from '@/Pages/Admin/Cms/Layouts/layoutZoneFormUpdates.js';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    mailTemplate: { type: Object, default: null },
    revisions: { type: Array, default: () => [] },
    contextOptions: { type: Array, required: true },
    placeableBlocks: { type: Array, required: true },
    mediaOptions: { type: Array, default: () => [] },
    mediaFolders: { type: Array, default: () => [] },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const locale = computed(() => page.props?.app?.locale || 'nl-BE');
const isEditMode = computed(() => Number(props.mailTemplate?.id || 0) > 0);
const activeTab = ref('basis');
const showRevisionDialog = ref(false);
const localMediaOptions = ref([...props.mediaOptions]);
const localMediaFolders = ref([...props.mediaFolders]);
const templateEditorKey = computed(() => props.mailTemplate?.id ?? 'new');

const form = useForm({
    name: props.mailTemplate?.name || '',
    description: props.mailTemplate?.description || '',
    context_key: props.mailTemplate?.context_key || 'public_site.auth_email',
    sections: props.mailTemplate?.sections ?? { content: [] },
    is_active: props.mailTemplate?.is_active ?? true,
});

const pageTitle = computed(() =>
    isEditMode.value
        ? t('mail.template_edit_title', 'Edit mail template')
        : t('mail.template_create_title', 'Create mail template'),
);

const tabOptions = computed(() => [
    { value: 'basis', label: t('common.tabs.basic', 'Basis') },
    { value: 'builder', label: t('mail.tabs.builder', 'Builder') },
]);

const recordIdLabel = computed(() => props.mailTemplate?.id ?? '-');
const updatedAtLabel = computed(() =>
    formatDate(props.mailTemplate?.updated_at),
);
const createdAtLabel = computed(() =>
    formatDate(props.mailTemplate?.created_at),
);

const pageFlash = computed(() => {
    const flash = page.props?.flash || {};
    if (flash.error) return { type: 'danger', message: flash.error };
    if (flash.warning) return { type: 'warning', message: flash.warning };
    if (flash.status) return { type: 'success', message: flash.status };
    return { type: '', message: '' };
});

function updateTemplateSections(sections) {
    form.sections = updatedLayoutSectionsForZone(
        form.sections,
        'content',
        sections,
    );
}

function submit() {
    const id = props.mailTemplate?.id || 0;
    form.post(route('admin.cms.mail-templates.store', { id }), {
        preserveScroll: true,
    });
}

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat(locale.value).format(new Date(value));
}
</script>
