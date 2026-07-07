<template>
    <Head :title="pageTitle" />

    <PlatformLayout :title="layoutTitle">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
            <Card class="border-slate-200 bg-white shadow-sm">
                <CardHeader>
                    <CardTitle>{{ layoutTitle }}</CardTitle>
                    <CardDescription>
                        {{
                            t(
                                'platform.sites.form.database_name_help',
                                'De tenant database naam wordt server-side gegenereerd.',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-5" @submit.prevent="submitSite">
                        <div class="grid gap-2">
                            <Label for="name">{{
                                t('platform.columns.name', 'Naam')
                            }}</Label>
                            <Input id="name" v-model="siteForm.name" required />
                            <p
                                v-if="siteForm.errors.name"
                                class="text-sm text-red-600"
                            >
                                {{ siteForm.errors.name }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="slug">{{
                                t('platform.columns.slug', 'Slug')
                            }}</Label>
                            <Input id="slug" v-model="siteForm.slug" required />
                            <p
                                v-if="siteForm.errors.slug"
                                class="text-sm text-red-600"
                            >
                                {{ siteForm.errors.slug }}
                            </p>
                        </div>

                        <div v-if="!isEditMode" class="grid gap-2">
                            <Label for="primary_domain">{{
                                t(
                                    'platform.sites.form.primary_domain',
                                    'Primary domain',
                                )
                            }}</Label>
                            <Input
                                id="primary_domain"
                                v-model="siteForm.primary_domain"
                                :placeholder="
                                    t(
                                        'platform.sites.form.domain_placeholder',
                                        'example.com',
                                    )
                                "
                            />
                            <p
                                v-if="siteForm.errors.primary_domain"
                                class="text-sm text-red-600"
                            >
                                {{ siteForm.errors.primary_domain }}
                            </p>
                        </div>

                        <div v-if="!isEditMode" class="grid gap-2">
                            <Label for="first_admin_email">{{
                                t(
                                    'platform.sites.form.first_admin',
                                    'First administrator',
                                )
                            }}</Label>
                            <Input
                                id="first_admin_email"
                                v-model="siteForm.first_admin_email"
                                type="email"
                                :placeholder="
                                    t(
                                        'platform.sites.form.first_admin_placeholder',
                                        'existing-user@example.com',
                                    )
                                "
                            />
                            <p class="text-xs text-slate-500">
                                {{
                                    t(
                                        'platform.sites.form.first_admin_help',
                                        'Only existing central users can be linked.',
                                    )
                                }}
                            </p>
                            <p
                                v-if="siteForm.errors.first_admin_email"
                                class="text-sm text-red-600"
                            >
                                {{ siteForm.errors.first_admin_email }}
                            </p>
                        </div>

                        <div
                            v-if="!isEditMode"
                            class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3"
                        >
                            <div>
                                <div class="text-sm font-medium text-slate-900">
                                    {{
                                        t(
                                            'platform.sites.form.tenant_storage.title',
                                            'Tenant data storage',
                                        )
                                    }}
                                </div>
                                <p class="mt-1 text-xs text-slate-600">
                                    {{
                                        t(
                                            'platform.sites.form.tenant_storage.description',
                                            'Choose how this site stores tenant tables. The existing separate database setup remains the default.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <label
                                    v-for="option in tenantStorageOptions"
                                    :key="option.value"
                                    class="flex gap-3 rounded-md border border-slate-200 bg-white p-3 text-sm shadow-none"
                                >
                                    <input
                                        v-model="siteForm.tenant_storage_option"
                                        type="radio"
                                        name="tenant_storage_option"
                                        :value="option.value"
                                        class="mt-1 h-4 w-4 border-slate-300 text-blue-600"
                                    />
                                    <span class="grid gap-1">
                                        <span
                                            class="font-medium text-slate-900"
                                        >
                                            {{ option.label }}
                                        </span>
                                        <span class="text-xs text-slate-600">
                                            {{ option.description }}
                                        </span>
                                    </span>
                                </label>
                            </div>

                            <div
                                v-if="usesNamedTenantDatabase"
                                class="grid gap-2"
                            >
                                <Label for="tenant_database">{{
                                    t(
                                        'platform.sites.form.tenant_database',
                                        'Tenant database',
                                    )
                                }}</Label>
                                <Input
                                    id="tenant_database"
                                    v-model="siteForm.tenant_database"
                                    :required="usesExistingTenantDatabase"
                                    :placeholder="tenantDatabasePlaceholder"
                                />
                                <p class="text-xs text-slate-500">
                                    {{ tenantDatabaseHelp }}
                                </p>
                                <p
                                    v-if="siteForm.errors.tenant_database"
                                    class="text-sm text-red-600"
                                >
                                    {{ siteForm.errors.tenant_database }}
                                </p>
                            </div>

                            <div
                                v-if="usesSharedPrefixedTenantDatabase"
                                class="grid gap-2"
                            >
                                <Label for="tenant_table_prefix">{{
                                    t(
                                        'platform.sites.form.tenant_table_prefix',
                                        'Tenant table prefix',
                                    )
                                }}</Label>
                                <Input
                                    id="tenant_table_prefix"
                                    v-model="siteForm.tenant_table_prefix"
                                    :placeholder="
                                        t(
                                            'platform.sites.form.tenant_table_prefix_placeholder',
                                            't_site_',
                                        )
                                    "
                                />
                                <p class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'platform.sites.form.tenant_table_prefix_help',
                                            'Optional. Leave empty to generate a safe prefix from the site slug.',
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="siteForm.errors.tenant_table_prefix"
                                    class="text-sm text-red-600"
                                >
                                    {{ siteForm.errors.tenant_table_prefix }}
                                </p>
                            </div>

                            <div
                                v-if="usesExistingTenantDatabase"
                                class="grid gap-3 rounded-md border border-slate-200 bg-white p-3"
                            >
                                <div>
                                    <div
                                        class="text-sm font-medium text-slate-900"
                                    >
                                        {{
                                            t(
                                                'platform.sites.form.tenant_connection.title',
                                                'Database connection',
                                            )
                                        }}
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{
                                            t(
                                                'platform.sites.form.tenant_connection.description',
                                                'Optional. Leave these fields empty when the existing database is reachable through the configured tenant database connection.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="tenant_database_url">{{
                                        t(
                                            'platform.sites.form.tenant_database_url',
                                            'Connection URL',
                                        )
                                    }}</Label>
                                    <Input
                                        id="tenant_database_url"
                                        v-model="siteForm.tenant_database_url"
                                        type="password"
                                        :placeholder="
                                            t(
                                                'platform.sites.form.tenant_database_url_placeholder',
                                                'mysql://user:password@host:3306/database',
                                            )
                                        "
                                    />
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'platform.sites.form.tenant_database_url_help',
                                                'Optional alternative to host, port, username and password. This value is stored encrypted.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            siteForm.errors.tenant_database_url
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            siteForm.errors.tenant_database_url
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="tenant_database_host">{{
                                            t(
                                                'platform.sites.form.tenant_database_host',
                                                'Database host or IP address',
                                            )
                                        }}</Label>
                                        <Input
                                            id="tenant_database_host"
                                            v-model="
                                                siteForm.tenant_database_host
                                            "
                                            :placeholder="
                                                t(
                                                    'platform.sites.form.tenant_database_host_placeholder',
                                                    '127.0.0.1',
                                                )
                                            "
                                        />
                                        <p
                                            v-if="
                                                siteForm.errors
                                                    .tenant_database_host
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                siteForm.errors
                                                    .tenant_database_host
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="tenant_database_port">{{
                                            t(
                                                'platform.sites.form.tenant_database_port',
                                                'Database port',
                                            )
                                        }}</Label>
                                        <Input
                                            id="tenant_database_port"
                                            v-model="
                                                siteForm.tenant_database_port
                                            "
                                            inputmode="numeric"
                                            :placeholder="
                                                t(
                                                    'platform.sites.form.tenant_database_port_placeholder',
                                                    '3306',
                                                )
                                            "
                                        />
                                        <p
                                            v-if="
                                                siteForm.errors
                                                    .tenant_database_port
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                siteForm.errors
                                                    .tenant_database_port
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="tenant_database_username">{{
                                            t(
                                                'platform.sites.form.tenant_database_username',
                                                'Database username',
                                            )
                                        }}</Label>
                                        <Input
                                            id="tenant_database_username"
                                            v-model="
                                                siteForm.tenant_database_username
                                            "
                                        />
                                        <p
                                            v-if="
                                                siteForm.errors
                                                    .tenant_database_username
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                siteForm.errors
                                                    .tenant_database_username
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="tenant_database_password">{{
                                            t(
                                                'platform.sites.form.tenant_database_password',
                                                'Database password',
                                            )
                                        }}</Label>
                                        <Input
                                            id="tenant_database_password"
                                            v-model="
                                                siteForm.tenant_database_password
                                            "
                                            type="password"
                                        />
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'platform.sites.form.tenant_database_password_help',
                                                    'Stored encrypted. Leave empty for a passwordless connection or when using a connection URL.',
                                                )
                                            }}
                                        </p>
                                        <p
                                            v-if="
                                                siteForm.errors
                                                    .tenant_database_password
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                siteForm.errors
                                                    .tenant_database_password
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="connectionTest.message"
                                    :class="[
                                        'rounded-md border px-3 py-2 text-sm',
                                        connectionTest.type === 'success'
                                            ? 'border-green-200 bg-green-50 text-green-800'
                                            : 'border-red-200 bg-red-50 text-red-800',
                                    ]"
                                >
                                    {{ connectionTest.message }}
                                </div>

                                <div class="flex justify-end">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="gap-2 shadow-none"
                                        :disabled="
                                            connectionTest.processing ||
                                            !siteForm.tenant_database
                                        "
                                        @click="testTenantConnection"
                                    >
                                        <span
                                            v-if="connectionTest.processing"
                                            class="mdi mdi-loading animate-spin text-base text-blue-700"
                                            aria-hidden="true"
                                        />
                                        <span
                                            v-else
                                            class="mdi mdi-database-check text-base text-blue-700"
                                            aria-hidden="true"
                                        />
                                        {{
                                            connectionTest.processing
                                                ? t(
                                                      'platform.sites.connection_test.testing',
                                                      'Testing...',
                                                  )
                                                : t(
                                                      'platform.sites.connection_test.button',
                                                      'Test connection',
                                                  )
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="isEditMode"
                            class="rounded-lg border border-slate-200 p-3"
                        >
                            <div
                                class="text-xs font-medium uppercase text-slate-500"
                            >
                                {{
                                    t(
                                        'platform.columns.tenant_database',
                                        'Tenant database',
                                    )
                                }}
                            </div>
                            <div class="mt-1 font-mono text-sm text-slate-900">
                                {{ site.tenant_database }}
                            </div>
                            <div
                                v-if="site.tenant_table_prefix"
                                class="mt-2 text-xs text-slate-500"
                            >
                                {{
                                    t(
                                        'platform.sites.form.tenant_table_prefix',
                                        'Tenant table prefix',
                                    )
                                }}:
                                <span class="font-mono text-slate-900">{{
                                    site.tenant_table_prefix
                                }}</span>
                            </div>
                            <div class="mt-2 text-xs text-slate-500">
                                {{
                                    t(
                                        'platform.sites.form.tenant_database_mode',
                                        'Database mode',
                                    )
                                }}:
                                {{ site.tenant_database_mode }}
                            </div>
                            <div class="mt-2 text-xs text-slate-500">
                                {{
                                    t(
                                        'platform.sites.form.tenant_provisioning_mode',
                                        'Provisioning mode',
                                    )
                                }}:
                                {{ site.tenant_provisioning_mode }}
                            </div>
                            <div class="mt-2 text-xs text-slate-500">
                                {{ t('platform.columns.status', 'Status') }}:
                                {{ site.status }}
                            </div>
                            <div
                                v-if="site.provisioning_error"
                                class="mt-2 text-sm text-red-700"
                            >
                                {{ site.provisioning_error }}
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <Button as-child type="button" variant="outline">
                                <Link :href="route('platform.sites.index')">{{
                                    t('actions.back', 'Back')
                                }}</Link>
                            </Button>
                            <Button
                                type="submit"
                                :disabled="siteForm.processing"
                            >
                                {{ t('actions.save', 'Save') }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div v-if="isEditMode" class="space-y-6">
                <Card class="border-slate-200 bg-white shadow-sm">
                    <CardHeader>
                        <CardTitle>{{
                            t(
                                'platform.sites.provisioning.title',
                                'Provisioning',
                            )
                        }}</CardTitle>
                        <CardDescription>
                            {{ provisioningDescription }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <div
                            v-if="isProvisioned"
                            class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800"
                        >
                            {{
                                t(
                                    'platform.sites.provisioning.success',
                                    'Provisioning succesvol uitgevoerd. De tenant database is actief.',
                                )
                            }}
                        </div>
                        <div
                            v-else-if="site.provisioning_error"
                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
                        >
                            {{
                                t(
                                    'platform.sites.provisioning.failed',
                                    'Provisioning failed. Check the error message and try again.',
                                )
                            }}
                        </div>
                        <div
                            class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-slate-600">{{
                                    t('platform.columns.status', 'Status')
                                }}</span>
                                <span :class="provisioningStatusClass">
                                    {{ provisioningStatusLabel }}
                                </span>
                            </div>
                            <div
                                v-if="site.provisioned_at"
                                class="mt-2 flex items-center justify-between gap-3"
                            >
                                <span class="text-slate-600">{{
                                    t(
                                        'platform.sites.provisioning.executed_at',
                                        'Executed at',
                                    )
                                }}</span>
                                <span class="text-slate-900">{{
                                    site.provisioned_at
                                }}</span>
                            </div>
                        </div>
                        <Button
                            type="button"
                            :disabled="
                                provisionForm.processing || isProvisioned
                            "
                            @click="provisionSite"
                        >
                            {{
                                isProvisioned
                                    ? t(
                                          'platform.sites.provisioning.provisioned',
                                          'Database provisioned',
                                      )
                                    : t(
                                          'platform.sites.provisioning.provision',
                                          'Database provisionen',
                                      )
                            }}
                        </Button>
                    </CardContent>
                </Card>

                <Card class="border-slate-200 bg-white shadow-sm">
                    <CardHeader>
                        <CardTitle>{{
                            t('platform.sites.domains.title', 'Domains')
                        }}</CardTitle>
                        <CardDescription>
                            {{
                                t(
                                    'platform.sites.domains.description',
                                    'Domains are centrally linked to this site.',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <form class="grid gap-3" @submit.prevent="submitDomain">
                            <div class="grid gap-2">
                                <Label for="host">{{
                                    t('platform.columns.host', 'Host')
                                }}</Label>
                                <Input
                                    id="host"
                                    v-model="domainForm.host"
                                    :placeholder="
                                        t(
                                            'platform.sites.form.domain_placeholder',
                                            'example.com',
                                        )
                                    "
                                />
                                <p
                                    v-if="domainForm.errors.host"
                                    class="text-sm text-red-600"
                                >
                                    {{ domainForm.errors.host }}
                                </p>
                            </div>
                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="domainForm.is_primary"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                />
                                {{
                                    t(
                                        'platform.sites.form.primary_domain',
                                        'Primary domain',
                                    )
                                }}
                            </label>
                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="domainForm.force_https"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                />
                                {{
                                    t(
                                        'platform.sites.form.force_https',
                                        'Force HTTPS',
                                    )
                                }}
                            </label>
                            <Button
                                type="submit"
                                :disabled="domainForm.processing"
                            >
                                {{
                                    t(
                                        'platform.sites.domains.add',
                                        'Add domain',
                                    )
                                }}
                            </Button>
                        </form>

                        <div
                            class="overflow-hidden rounded-lg border border-slate-200"
                        >
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th
                                            class="px-3 py-2 text-left font-medium"
                                        >
                                            {{
                                                t(
                                                    'platform.columns.host',
                                                    'Host',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left font-medium"
                                        >
                                            {{
                                                t(
                                                    'platform.columns.primary',
                                                    'Primary',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left font-medium"
                                        >
                                            HTTPS
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="domain in site.domains"
                                        :key="domain.id"
                                        class="border-t border-slate-100"
                                    >
                                        <td class="px-3 py-2 text-slate-900">
                                            {{ domain.host }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{
                                                domain.is_primary
                                                    ? t('common.yes', 'Yes')
                                                    : t('common.no', 'No')
                                            }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{
                                                domain.force_https
                                                    ? t('common.yes', 'Yes')
                                                    : t('common.no', 'No')
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </PlatformLayout>
</template>

<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
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
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    site: { type: Object, default: null },
});

const { t } = useAdminTranslations('admin_common_ui');

const isEditMode = computed(() => Boolean(props.site?.id));
const pageTitle = computed(() =>
    isEditMode.value
        ? t('platform.sites.form.edit_title', 'Edit site')
        : t('platform.sites.form.create_title', 'Add site'),
);
const layoutTitle = computed(() =>
    isEditMode.value
        ? t('platform.sites.form.edit_title', 'Edit site')
        : t('platform.actions.new_site', 'New site'),
);
const isProvisioned = computed(
    () => props.site?.status === 'active' && !props.site?.provisioning_error,
);

const provisioningDescription = computed(() => {
    if (isProvisioned.value) {
        return t(
            'platform.sites.provisioning.ready_description',
            'The physical tenant database has been created and is ready for use.',
        );
    }

    return t(
        'platform.sites.provisioning.create_description',
        'Create the physical tenant database and run tenant migrations.',
    );
});

const provisioningStatusLabel = computed(() => {
    const labels = {
        active: t('platform.status.active', 'Active'),
        draft: t('platform.status.draft', 'Draft'),
        failed: t('platform.status.failed', 'Failed'),
        provisioning: t('platform.status.provisioning', 'Provisioning'),
    };

    return (
        labels[props.site?.status] ??
        props.site?.status ??
        t('platform.status.unknown', 'Unknown')
    );
});

const provisioningStatusClass = computed(() => {
    if (isProvisioned.value) {
        return 'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700';
    }

    if (props.site?.status === 'failed' || props.site?.provisioning_error) {
        return 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700';
    }

    if (props.site?.status === 'provisioning') {
        return 'rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700';
    }

    return 'rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700';
});

const siteForm = useForm({
    name: props.site?.name ?? '',
    slug: props.site?.slug ?? '',
    primary_domain: '',
    first_admin_email: '',
    tenant_storage_option: 'create_database',
    tenant_database: '',
    tenant_table_prefix: '',
    tenant_database_url: '',
    tenant_database_host: '',
    tenant_database_port: '',
    tenant_database_username: '',
    tenant_database_password: '',
});

const tenantStorageOptions = computed(() => [
    {
        value: 'create_database',
        label: t(
            'platform.sites.form.tenant_storage.create_database.label',
            'Create a new tenant database',
        ),
        description: t(
            'platform.sites.form.tenant_storage.create_database.description',
            'Current default behavior. RwSoft creates a separate database for this site and runs tenant migrations there.',
        ),
    },
    {
        value: 'existing_database',
        label: t(
            'platform.sites.form.tenant_storage.existing_database.label',
            'Use an existing separate tenant database',
        ),
        description: t(
            'platform.sites.form.tenant_storage.existing_database.description',
            'RwSoft will not create a database. It will use the configured tenant database connection or the connection details below and add tenant tables through migrations.',
        ),
    },
    {
        value: 'shared_prefixed',
        label: t(
            'platform.sites.form.tenant_storage.shared_prefixed.label',
            'Use the same database with a table prefix',
        ),
        description: t(
            'platform.sites.form.tenant_storage.shared_prefixed.description',
            'For single-database hosting. Tenant tables are created in the shared database with a unique prefix.',
        ),
    },
]);

const usesSharedPrefixedTenantDatabase = computed(
    () => siteForm.tenant_storage_option === 'shared_prefixed',
);
const usesExistingTenantDatabase = computed(
    () => siteForm.tenant_storage_option === 'existing_database',
);
const usesNamedTenantDatabase = computed(
    () =>
        usesSharedPrefixedTenantDatabase.value ||
        usesExistingTenantDatabase.value,
);
const tenantDatabasePlaceholder = computed(() =>
    usesSharedPrefixedTenantDatabase.value
        ? t('platform.sites.form.shared_database_placeholder', 'rwsoft')
        : t(
              'platform.sites.form.existing_database_placeholder',
              'existing_tenant_db',
          ),
);
const tenantDatabaseHelp = computed(() =>
    usesSharedPrefixedTenantDatabase.value
        ? t(
              'platform.sites.form.shared_database_help',
              'Optional. Leave empty to use the configured shared database.',
          )
        : t(
              'platform.sites.form.existing_database_help',
              'Required. The database must already exist on the configured tenant connection or on the connection details below.',
          ),
);

const domainForm = useForm({
    host: '',
    is_primary: false,
    force_https: true,
});

const provisionForm = useForm({});

const connectionTest = reactive({
    processing: false,
    type: '',
    message: '',
});

function submitSite() {
    siteForm.post(route('platform.sites.store', { id: props.site?.id ?? 0 }));
}

async function testTenantConnection() {
    connectionTest.processing = true;
    connectionTest.type = '';
    connectionTest.message = '';
    siteForm.clearErrors(
        'tenant_database',
        'tenant_database_url',
        'tenant_database_host',
        'tenant_database_port',
        'tenant_database_username',
        'tenant_database_password',
    );

    try {
        const response = await window.axios.post(
            route('platform.sites.test-tenant-connection'),
            {
                tenant_storage_option: siteForm.tenant_storage_option,
                tenant_database: siteForm.tenant_database,
                tenant_database_url: siteForm.tenant_database_url,
                tenant_database_host: siteForm.tenant_database_host,
                tenant_database_port: siteForm.tenant_database_port,
                tenant_database_username: siteForm.tenant_database_username,
                tenant_database_password: siteForm.tenant_database_password,
            },
        );

        connectionTest.type = 'success';
        connectionTest.message =
            response.data?.message ||
            t(
                'platform.sites.connection_test.success',
                'The database connection works.',
            );
    } catch (error) {
        const errors = error.response?.data?.errors || {};

        Object.entries(errors).forEach(([field, messages]) => {
            siteForm.setError(
                field,
                Array.isArray(messages) ? messages[0] : String(messages),
            );
        });

        connectionTest.type = 'error';
        connectionTest.message =
            error.response?.data?.message ||
            t(
                'platform.sites.connection_test.failed',
                'The database connection could not be established.',
            );
    } finally {
        connectionTest.processing = false;
    }
}

function submitDomain() {
    domainForm.post(
        route('platform.sites.domains.store', { site: props.site.id }),
        {
            onSuccess: () => domainForm.reset('host', 'is_primary'),
        },
    );
}

function provisionSite() {
    provisionForm.post(
        route('platform.sites.provision', { site: props.site.id }),
    );
}
</script>
