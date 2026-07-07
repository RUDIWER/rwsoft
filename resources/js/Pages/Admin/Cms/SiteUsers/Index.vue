<template>
    <Head :title="t('public_account.meta_title', 'Website accounts')" />

    <AdminLayout>
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
                            <span class="mdi mdi-account-group text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{
                                    t(
                                        'public_account.index_title',
                                        'Website accounts',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'public_account.index_subtitle',
                                        'Manage public website accounts and account module settings.',
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
                                :href="route('admin')"
                                :aria-label="commonT('actions.back', 'Back')"
                                :title="commonT('actions.back', 'Back')"
                            >
                                <span
                                    class="mdi mdi-arrow-left-circle text-lg"
                                    aria-hidden="true"
                                />
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <form
                    class="border-b border-slate-200 px-4 py-3 sm:px-5"
                    @submit.prevent="submitSettings"
                >
                    <div
                        class="grid w-full gap-3 rounded border border-slate-200 bg-slate-50 p-3"
                    >
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                {{
                                    t(
                                        'public_account.settings_title',
                                        'Account settings',
                                    )
                                }}
                            </h2>
                            <p class="mt-1 text-xs text-slate-600">
                                {{
                                    t(
                                        'public_account.settings_description',
                                        'Configure registration, email verification and two-factor authentication for public website users.',
                                    )
                                }}
                            </p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="settingsForm.registration_enabled"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600"
                                />
                                {{
                                    t(
                                        'public_account.registration_enabled',
                                        'Allow public registration',
                                    )
                                }}
                            </label>

                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="
                                        settingsForm.email_verification_required
                                    "
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600"
                                />
                                {{
                                    t(
                                        'public_account.email_verification_required',
                                        'Require email verification before dashboard access',
                                    )
                                }}
                            </label>

                            <label class="grid gap-1 text-sm text-slate-700">
                                <span>
                                    {{
                                        t(
                                            'public_account.two_factor_mode',
                                            'Two-factor authentication',
                                        )
                                    }}
                                </span>
                                <select
                                    v-model="settingsForm.two_factor_mode"
                                    class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm"
                                >
                                    <option value="disabled">
                                        {{
                                            t(
                                                'public_account.two_factor_modes.disabled',
                                                'Disabled',
                                            )
                                        }}
                                    </option>
                                    <option value="optional">
                                        {{
                                            t(
                                                'public_account.two_factor_modes.optional',
                                                'Optional',
                                            )
                                        }}
                                    </option>
                                    <option value="required">
                                        {{
                                            t(
                                                'public_account.two_factor_modes.required',
                                                'Required',
                                            )
                                        }}
                                    </option>
                                </select>
                            </label>
                        </div>

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="settingsForm.processing"
                                class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                            >
                                <span
                                    v-if="settingsForm.processing"
                                    class="mdi mdi-loading animate-spin text-base text-green-700"
                                    aria-hidden="true"
                                />
                                <span
                                    v-else
                                    class="mdi mdi-content-save text-base text-green-700"
                                    aria-hidden="true"
                                />
                                {{ commonT('actions.save', 'Save') }}
                            </Button>
                        </div>
                    </div>
                </form>

                <RwTable
                    table-id="admin-cms-site-users-table-v1"
                    :data="tableData"
                    :columns="columns"
                    :rows-per-page="25"
                    sort-field="id"
                    sort-order="desc"
                    :row-options="[25, 50, 100, 250]"
                    :row-menu="true"
                    :row-menu-items="rowMenuItems"
                    excel="true"
                    @on-row-menu-item-click="onRowMenuItemClick"
                />
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import RwTable from '@/Components/RwTable.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    siteUsers: {
        type: Array,
        required: true,
    },
    settings: {
        type: Object,
        required: true,
    },
});

const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');

const settingsForm = useForm({
    registration_enabled: Boolean(props.settings.registration_enabled),
    email_verification_required: Boolean(
        props.settings.email_verification_required,
    ),
    two_factor_mode: props.settings.two_factor_mode || 'disabled',
});

const tableData = computed(() => ({
    data: props.siteUsers,
    total: props.siteUsers.length,
}));

const columns = computed(() => [
    {
        key: 'id',
        label: t('public_account.columns.id', 'ID'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        width: 90,
    },
    {
        key: 'name',
        label: t('public_account.columns.name', 'Name'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'email',
        label: t('public_account.columns.email', 'Email'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'status',
        label: t('public_account.columns.status', 'Status'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'email_verified',
        label: t('public_account.columns.email_verified', 'Email verified'),
        type: 'boolean',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'two_factor_enabled',
        label: t('public_account.columns.two_factor_enabled', '2FA enabled'),
        type: 'boolean',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'last_login_at',
        label: t('public_account.columns.last_login_at', 'Last login'),
        type: 'datetime',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'created_at',
        label: t('public_account.columns.created_at', 'Created'),
        type: 'datetime',
        selected: true,
        sortable: true,
        filterable: true,
    },
]);

function rowMenuItems(row) {
    const items = [];

    if (row?.status === 'active') {
        items.push({
            key: 'deactivate',
            label: t('public_account.actions.deactivate', 'Deactivate account'),
            icon: 'mdi-account-off-outline',
            color: 'orange',
        });
    } else {
        items.push({
            key: 'activate',
            label: t('public_account.actions.activate', 'Activate account'),
            icon: 'mdi-account-check-outline',
            color: 'green',
        });
    }

    items.push({
        key: 'reset-two-factor',
        label: t('public_account.actions.reset_two_factor', 'Reset 2FA'),
        icon: 'mdi-shield-refresh-outline',
        disabled: !row?.two_factor_enabled,
    });

    return items;
}

function onRowMenuItemClick({ item, row }) {
    if (!item?.key || !row?.id) {
        return;
    }

    const routeNameByAction = {
        activate: 'admin.cms.site-users.activate',
        deactivate: 'admin.cms.site-users.deactivate',
        'reset-two-factor': 'admin.cms.site-users.reset-two-factor',
    };
    const routeName = routeNameByAction[item.key];

    if (!routeName) {
        return;
    }

    router.post(
        route(routeName, { siteUser: row.id }),
        {},
        { preserveScroll: true },
    );
}

function submitSettings() {
    settingsForm.post(route('admin.cms.site-users.settings.store'), {
        preserveScroll: true,
    });
}
</script>
