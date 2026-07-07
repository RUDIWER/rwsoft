<template>
    <Head :title="t('downloads.groups.page_title', 'Download groups')" />

    <AdminLayout :suppress-flash="true">
        <Card class="rounded-none shadow-none">
            <CardHeader class="gap-0 border-b border-slate-200 p-0">
                <div
                    class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-5"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                            aria-hidden="true"
                        >
                            <span
                                class="mdi mdi-account-group-outline text-2xl"
                            />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">{{
                                t('downloads.groups.title', 'Download groups')
                            }}</CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'downloads.groups.description',
                                        'Manage site-user groups for restricted downloads.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            as-child
                            variant="outline"
                            size="icon"
                            class="text-slate-950 shadow-none hover:bg-slate-50 hover:text-slate-950"
                        >
                            <Link
                                :href="route('admin.cms.downloads.index')"
                                :aria-label="commonT('actions.back', 'Back')"
                                :title="commonT('actions.back', 'Back')"
                            >
                                <span
                                    class="mdi mdi-arrow-left-circle text-lg"
                                    aria-hidden="true"
                                />
                            </Link>
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                            @click="startCreate"
                        >
                            <span
                                class="mdi mdi-plus-circle text-base text-blue-700"
                                aria-hidden="true"
                            />
                            {{ commonT('actions.new', 'New') }}
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <div
                v-if="pageFlash.message"
                class="border-b border-slate-200 px-4 py-3 sm:px-5"
            >
                <RwFlashMessage
                    :type="pageFlash.type"
                    :message="pageFlash.message"
                />
            </div>

            <CardContent class="p-0">
                <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                    <form
                        class="grid gap-3 rounded border border-slate-200 bg-slate-50 p-3 lg:grid-cols-[minmax(180px,1fr)_minmax(180px,1fr)_minmax(260px,2fr)_auto]"
                        @submit.prevent="submit"
                    >
                        <div class="grid gap-1">
                            <Label
                                for="group-name"
                                class="flex items-center gap-1 text-[11px] text-slate-600"
                            >
                                <span class="text-red-600" aria-hidden="true"
                                    >*</span
                                >
                                {{ t('downloads.groups.fields.name', 'Name') }}
                            </Label>
                            <Input
                                id="group-name"
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
                        <div class="grid gap-1">
                            <Label
                                for="group-slug"
                                class="text-[11px] text-slate-600"
                                >{{
                                    t('downloads.groups.fields.slug', 'Slug')
                                }}</Label
                            >
                            <Input id="group-slug" v-model="form.slug" />
                        </div>
                        <div class="grid gap-1">
                            <Label
                                for="group-users"
                                class="text-[11px] text-slate-600"
                                >{{
                                    t('downloads.groups.fields.users', 'Users')
                                }}</Label
                            >
                            <RwAutoCompleteInput
                                id="group-users"
                                v-model="form.site_user_ids"
                                :items="siteUsers"
                                item-title="label"
                                item-value="id"
                                :search-fields="['name', 'email', 'label']"
                                multiple
                            />
                        </div>
                        <div class="flex items-end gap-2">
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="form.processing"
                                class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                            >
                                <span
                                    v-if="form.processing"
                                    class="mdi mdi-loading animate-spin text-base text-green-700"
                                    aria-hidden="true"
                                />
                                <span
                                    v-else
                                    class="mdi mdi-content-save text-base"
                                    :class="
                                        form.isDirty
                                            ? 'text-red-600'
                                            : 'text-green-700'
                                    "
                                    aria-hidden="true"
                                />
                                {{ commonT('actions.save', 'Save') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <RwTable
                    table-id="admin-cms-download-groups-table"
                    :data="tableData"
                    :columns="columns"
                    :initial-height="'calc(100vh - 320px)'"
                    :rows-per-page="25"
                    sort-field="id"
                    sort-order="desc"
                    :row-options="[25, 50, 100, 250]"
                    :cell-class="cellClass"
                    excel="true"
                    @on-cell-click="onCellClick"
                />
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwTable from '@/Components/RwTable.vue';
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
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    groups: { type: Array, required: true },
    siteUsers: { type: Array, default: () => [] },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const editingGroupId = ref(null);

const form = useForm({
    name: '',
    slug: '',
    description: '',
    is_active: true,
    site_user_ids: [],
});

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

const tableData = computed(() => ({
    data: props.groups,
    total: props.groups.length,
}));

const columns = computed(() => [
    {
        key: 'id',
        label: commonT('columns.id', 'ID'),
        type: 'number',
        clickable: true,
    },
    {
        key: 'name',
        label: t('downloads.groups.columns.name', 'Name'),
        type: 'text',
        filterable: true,
    },
    {
        key: 'slug',
        label: t('downloads.groups.columns.slug', 'Slug'),
        type: 'text',
        filterable: true,
    },
    {
        key: 'site_users_label',
        label: t('downloads.groups.columns.users', 'Users'),
        type: 'text',
        filterable: true,
    },
    {
        key: 'updated_at',
        label: commonT('record_meta.updated_at', 'Updated'),
        type: 'datetime',
    },
]);

function startCreate() {
    editingGroupId.value = null;
    form.reset();
    form.clearErrors();
}

function onCellClick(field, id) {
    if (field !== 'id') {
        return;
    }

    const group = props.groups.find((item) => Number(item.id) === Number(id));

    if (!group) {
        return;
    }

    editingGroupId.value = group.id;
    form.name = group.name ?? '';
    form.slug = group.slug ?? '';
    form.description = group.description ?? '';
    form.is_active = group.is_active ?? true;
    form.site_user_ids = group.site_user_ids ?? [];
    form.clearErrors();
}

function submit() {
    const target = editingGroupId.value
        ? route('admin.cms.download-groups.update', {
              group: editingGroupId.value,
          })
        : route('admin.cms.download-groups.store');

    form.post(target, {
        preserveScroll: true,
        onSuccess: () => startCreate(),
    });
}

function cellClass({ col }) {
    return col.clickable ? 'cursor-pointer' : null;
}
</script>
