<template>
    <Head :title="t('downloads.edit_title', 'Edit download')" />

    <AdminLayout :suppress-flash="true">
        <Card
            class="flex h-[calc(100vh-8rem)] flex-col overflow-hidden rounded-none shadow-none"
        >
            <CardHeader class="shrink-0 gap-0 border-b border-slate-200 p-0">
                <div
                    class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-5"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                            aria-hidden="true"
                        >
                            <span
                                class="mdi mdi-file-document-edit-outline text-2xl"
                            />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">{{
                                t('downloads.edit_title', 'Edit download')
                            }}</CardTitle>
                            <CardDescription class="mt-1">{{
                                asset.original_filename || asset.filename
                            }}</CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <AdminFormBackButton
                            :href="route('admin.cms.downloads.index')"
                            :dirty="form.isDirty || replaceForm.isDirty"
                            :processing="
                                form.processing || replaceForm.processing
                            "
                            :label="commonT('actions.back', 'Back')"
                            @save="submit"
                        />
                        <AdminFormSaveButton
                            type="button"
                            :dirty="form.isDirty"
                            :processing="form.processing"
                            :label="commonT('actions.save', 'Save')"
                            @click="submit"
                        />
                    </div>
                </div>
            </CardHeader>

            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 sm:px-5"
            >
                <div class="font-medium text-slate-700">
                    {{ commonT('record_meta.id', 'ID') }}:
                    <span class="ml-1 text-base font-bold text-slate-950">{{
                        asset.id
                    }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                    <div class="font-medium text-slate-700">
                        {{ commonT('record_meta.updated_at', 'Updated') }}:
                        <span class="ml-1 text-base font-bold text-slate-950">{{
                            formatDate(asset.updated_at)
                        }}</span>
                    </div>
                    <div class="font-medium text-slate-700">
                        {{ commonT('record_meta.created_at', 'Created') }}:
                        <span class="ml-1 text-base font-bold text-slate-950">{{
                            formatDate(asset.created_at)
                        }}</span>
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

            <CardContent class="flex min-h-0 flex-1 flex-col p-4 sm:p-5">
                <form
                    class="flex min-h-0 flex-1 flex-col gap-5"
                    @submit.prevent="submit"
                >
                    <section class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="title">{{
                                t('downloads.fields.title', 'Title')
                            }}</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                type="text"
                            />
                            <p
                                v-if="form.errors.title"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.title }}
                            </p>
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="description">{{
                                t('downloads.fields.description', 'Description')
                            }}</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="min-h-24 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />
                            <p
                                v-if="form.errors.description"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="folder_id">{{
                                t('downloads.folder', 'Folder')
                            }}</Label>
                            <RwAutoCompleteInput
                                id="folder_id"
                                v-model="form.folder_id"
                                :items="folderOptions"
                                item-title="name"
                                item-value="id"
                                :search-fields="['name']"
                                :placeholder="
                                    t('downloads.no_folder', 'No folder')
                                "
                            />
                            <p
                                v-if="form.errors.folder_id"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.folder_id }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label
                                for="access_mode"
                                class="flex items-center gap-1"
                            >
                                <span class="text-red-600" aria-hidden="true"
                                    >*</span
                                >
                                {{
                                    t(
                                        'downloads.fields.access_mode',
                                        'Access mode',
                                    )
                                }}
                            </Label>
                            <RwAutoCompleteInput
                                id="access_mode"
                                v-model="form.access_mode"
                                :items="accessModeOptions"
                                item-title="label"
                                item-value="value"
                                :search-fields="['label']"
                                required
                                required-highlight-color="#fefce8"
                            />
                            <p
                                v-if="form.errors.access_mode"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.access_mode }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="published_at">{{
                                t(
                                    'downloads.fields.published_at',
                                    'Published at',
                                )
                            }}</Label>
                            <Input
                                id="published_at"
                                v-model="form.published_at"
                                type="datetime-local"
                            />
                            <p
                                v-if="form.errors.published_at"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.published_at }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="expires_at">{{
                                t('downloads.fields.expires_at', 'Expires at')
                            }}</Label>
                            <Input
                                id="expires_at"
                                v-model="form.expires_at"
                                type="datetime-local"
                            />
                            <p
                                v-if="form.errors.expires_at"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.expires_at }}
                            </p>
                        </div>
                    </section>

                    <section
                        v-if="form.access_mode === 'restricted'"
                        class="grid shrink-0 gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'downloads.access_rules.title',
                                            'Access rules',
                                        )
                                    }}
                                </h2>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{
                                        t(
                                            'downloads.access_rules.description',
                                            'Allow access for specific users, download groups or profile field values.',
                                        )
                                    }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                class="gap-2 border-blue-200 bg-white text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                                @click="addAccessRule"
                            >
                                <span
                                    class="mdi mdi-plus-circle text-base text-blue-700"
                                    aria-hidden="true"
                                />
                                {{ commonT('actions.new', 'New') }}
                            </Button>
                        </div>

                        <div
                            v-if="form.access_rules.length === 0"
                            class="rounded border border-dashed border-slate-300 bg-white p-3 text-sm text-slate-500"
                        >
                            {{
                                t(
                                    'downloads.access_rules.empty',
                                    'No access rules have been added yet.',
                                )
                            }}
                        </div>

                        <div v-else class="grid gap-3">
                            <div
                                v-for="(rule, index) in form.access_rules"
                                :key="`access-rule-${index}`"
                                class="grid gap-3 rounded border border-slate-200 bg-white p-3 lg:grid-cols-[minmax(180px,1fr)_minmax(220px,2fr)_auto]"
                            >
                                <div class="grid gap-2">
                                    <Label>
                                        {{
                                            t(
                                                'downloads.access_rules.rule_type',
                                                'Rule type',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        v-model="rule.rule_type"
                                        :items="accessRuleTypeOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label']"
                                        @update:model-value="
                                            resetAccessRuleTarget(rule)
                                        "
                                    />
                                </div>

                                <div
                                    v-if="rule.rule_type === 'site_user'"
                                    class="grid gap-2"
                                >
                                    <Label>
                                        {{
                                            t(
                                                'downloads.access_rules.site_user',
                                                'Site user',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        v-model="rule.site_user_id"
                                        :items="siteUsers"
                                        item-title="label"
                                        item-value="id"
                                        :search-fields="[
                                            'name',
                                            'email',
                                            'label',
                                        ]"
                                    />
                                </div>

                                <div
                                    v-else-if="
                                        rule.rule_type === 'download_group'
                                    "
                                    class="grid gap-2"
                                >
                                    <Label>
                                        {{
                                            t(
                                                'downloads.access_rules.group',
                                                'Download group',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        v-model="rule.cms_download_group_id"
                                        :items="groups"
                                        item-title="name"
                                        item-value="id"
                                        :search-fields="['name', 'slug']"
                                    />
                                </div>

                                <div v-else class="grid gap-3 md:grid-cols-3">
                                    <div class="grid gap-2">
                                        <Label>
                                            {{
                                                t(
                                                    'downloads.access_rules.profile_field_key',
                                                    'Profile field',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            v-model="rule.profile_field_key"
                                            type="text"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>
                                            {{
                                                t(
                                                    'downloads.access_rules.operator',
                                                    'Operator',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            v-model="rule.operator"
                                            :items="profileOperatorOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label']"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>
                                            {{
                                                t(
                                                    'downloads.access_rules.value',
                                                    'Value',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            v-model="rule.value"
                                            type="text"
                                        />
                                    </div>
                                </div>

                                <div class="flex items-end justify-end">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        class="h-9 w-9 border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                                        :title="
                                            commonT('actions.delete', 'Delete')
                                        "
                                        :aria-label="
                                            commonT('actions.delete', 'Delete')
                                        "
                                        @click="removeAccessRule(index)"
                                    >
                                        <span
                                            class="mdi mdi-delete text-base"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        class="grid shrink-0 gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3"
                    >
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t('downloads.replace.title', 'Replace file')
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{
                                    t(
                                        'downloads.replace.description',
                                        'Replacing the file keeps the same download ID and removes the old stored file.',
                                    )
                                }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="grid min-w-64 flex-1 gap-1">
                                <Label
                                    for="replace-file"
                                    class="text-[11px] text-slate-600"
                                    >{{
                                        t('downloads.upload.file', 'File')
                                    }}</Label
                                >
                                <Input
                                    id="replace-file"
                                    type="file"
                                    @change="onReplaceFileChange"
                                />
                                <p
                                    v-if="replaceForm.errors.file"
                                    class="text-sm text-red-600"
                                >
                                    {{ replaceForm.errors.file }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="
                                    replaceForm.processing || !replaceForm.file
                                "
                                class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                                @click="replaceFile"
                            >
                                <span
                                    v-if="replaceForm.processing"
                                    class="mdi mdi-loading animate-spin text-base text-green-700"
                                    aria-hidden="true"
                                />
                                <span
                                    v-else
                                    class="mdi mdi-file-replace-outline text-base text-green-700"
                                    aria-hidden="true"
                                />
                                {{
                                    t(
                                        'downloads.replace.submit',
                                        'Replace file',
                                    )
                                }}
                            </Button>
                        </div>
                    </section>
                </form>
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    asset: { type: Object, required: true },
    folders: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    siteUsers: { type: Array, default: () => [] },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');

const form = useForm({
    folder_id: props.asset.folder_id ?? null,
    title: props.asset.title ?? '',
    description: props.asset.description ?? '',
    access_mode: props.asset.access_mode ?? 'inherit',
    published_at: toDateTimeLocal(props.asset.published_at),
    expires_at: toDateTimeLocal(props.asset.expires_at),
    sort_order: props.asset.sort_order ?? 0,
    access_rules: normalizeAccessRules(props.asset.access_rules),
});

const replaceForm = useForm({ file: null });

const pageFlash = computed(() => {
    const flash = page.props?.flash || {};

    if (flash.error) {
        return { type: 'danger', message: flash.error };
    }

    if (flash.warning) {
        return { type: 'warning', message: flash.warning };
    }

    if (flash.status) {
        return { type: 'success', message: flash.status };
    }

    return { type: '', message: '' };
});

const folderOptions = computed(() => [
    { id: null, name: t('downloads.no_folder', 'No folder') },
    ...props.folders,
]);

const accessModeOptions = computed(() => [
    { value: 'inherit', label: t('downloads.access.inherit', 'Inherit') },
    { value: 'public', label: t('downloads.access.public', 'Public') },
    {
        value: 'authenticated',
        label: t('downloads.access.authenticated', 'Authenticated'),
    },
    { value: 'password', label: t('downloads.access.password', 'Password') },
    {
        value: 'restricted',
        label: t('downloads.access.restricted', 'Restricted'),
    },
]);

const accessRuleTypeOptions = computed(() => [
    {
        value: 'site_user',
        label: t('downloads.access_rules.types.site_user', 'Site user'),
    },
    {
        value: 'download_group',
        label: t(
            'downloads.access_rules.types.download_group',
            'Download group',
        ),
    },
    {
        value: 'profile_field',
        label: t('downloads.access_rules.types.profile_field', 'Profile field'),
    },
]);

const profileOperatorOptions = computed(() => [
    { value: 'equals', label: t('downloads.operators.equals', 'Equals') },
    {
        value: 'not_equals',
        label: t('downloads.operators.not_equals', 'Does not equal'),
    },
    { value: 'in', label: t('downloads.operators.in', 'In list') },
    {
        value: 'not_in',
        label: t('downloads.operators.not_in', 'Not in list'),
    },
    { value: 'contains', label: t('downloads.operators.contains', 'Contains') },
    { value: 'filled', label: t('downloads.operators.filled', 'Is filled') },
]);

function submit() {
    form.transform((data) => ({
        ...data,
        access_rules: data.access_rules.map((rule) => ({
            ...rule,
            value: serializedAccessRuleValue(rule),
        })),
    })).post(
        route('admin.cms.downloads.update', { download: props.asset.id }),
        {
            preserveScroll: true,
        },
    );
}

function onReplaceFileChange(event) {
    replaceForm.file = event.target.files?.[0] ?? null;
}

function replaceFile() {
    replaceForm.post(
        route('admin.cms.downloads.replace-file', { download: props.asset.id }),
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => replaceForm.reset(),
        },
    );
}

function addAccessRule() {
    form.access_rules.push({
        rule_type: 'site_user',
        site_user_id: null,
        cms_download_group_id: null,
        profile_field_key: '',
        operator: 'equals',
        value: '',
    });
}

function removeAccessRule(index) {
    form.access_rules.splice(index, 1);
}

function resetAccessRuleTarget(rule) {
    rule.site_user_id = null;
    rule.cms_download_group_id = null;
    rule.profile_field_key = '';
    rule.operator = 'equals';
    rule.value = '';
}

function normalizeAccessRules(rules) {
    return Array.isArray(rules)
        ? rules.map((rule) => ({
              rule_type: rule.rule_type || 'site_user',
              site_user_id: rule.site_user_id ?? null,
              cms_download_group_id: rule.cms_download_group_id ?? null,
              profile_field_key: rule.profile_field_key ?? '',
              operator: rule.operator || 'equals',
              value: Array.isArray(rule.value)
                  ? rule.value.join(', ')
                  : (rule.value ?? ''),
          }))
        : [];
}

function serializedAccessRuleValue(rule) {
    if (!['in', 'not_in'].includes(rule.operator)) {
        return rule.value;
    }

    return String(rule.value || '')
        .split(',')
        .map((value) => value.trim())
        .filter(Boolean);
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return new Intl.DateTimeFormat('nl-BE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
}

function toDateTimeLocal(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toISOString().slice(0, 16);
}
</script>
