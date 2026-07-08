<template>
    <Head :title="pageTitle" />

    <PlatformLayout :title="pageTitle">
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
                            <span class="mdi mdi-cloud-sync text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">{{
                                pageTitle
                            }}</CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'platform.hosting.form.description',
                                        'Connect RwSoft to a remote hosting provider. Laravel Cloud is the first supported provider.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <AdminFormBackButton
                            :href="route('platform.hosting.index')"
                            :dirty="form.isDirty"
                            :processing="form.processing"
                            @save="saveConnection"
                        />
                        <Button
                            v-if="isEditMode"
                            type="button"
                            variant="outline"
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                            :disabled="testForm.processing"
                            @click="testConnection"
                        >
                            <span
                                :class="[
                                    'mdi text-base',
                                    testForm.processing
                                        ? 'mdi-loading animate-spin'
                                        : 'mdi-cloud-check',
                                ]"
                                aria-hidden="true"
                            />
                            {{
                                t(
                                    'platform.hosting.connection_test.button',
                                    'Test connection',
                                )
                            }}
                        </Button>
                        <Button
                            v-if="isEditMode"
                            type="button"
                            variant="outline"
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                            :disabled="syncForm.processing"
                            @click="syncEnvironments"
                        >
                            <span
                                :class="[
                                    'mdi text-base',
                                    syncForm.processing
                                        ? 'mdi-loading animate-spin'
                                        : 'mdi-cloud-download',
                                ]"
                                aria-hidden="true"
                            />
                            {{
                                t(
                                    'platform.hosting.actions.sync_environments',
                                    'Sync environments',
                                )
                            }}
                        </Button>
                        <AdminFormSaveButton
                            type="button"
                            :dirty="form.isDirty || hasBlockingClientIssues"
                            :processing="form.processing"
                            @click="saveConnection"
                        />
                    </div>
                </div>
            </CardHeader>

            <div
                v-if="isEditMode"
                class="grid gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:px-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]"
            >
                <div :class="statusCardClass">
                    <div class="text-sm font-semibold">{{ statusLabel }}</div>
                    <div class="mt-1 text-xs">{{ statusHelp }}</div>
                </div>

                <dl class="grid gap-2 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="font-medium text-slate-600">
                            {{
                                t(
                                    'platform.hosting.fields.last_checked_at',
                                    'Last check',
                                )
                            }}:
                        </dt>
                        <dd class="font-bold text-slate-950">
                            {{ formatDateTime(connection?.last_checked_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-600">
                            {{
                                t(
                                    'platform.hosting.fields.api_token',
                                    'API token',
                                )
                            }}:
                        </dt>
                        <dd class="font-bold text-slate-950">
                            {{ connection?.has_api_token ? '••••••••' : '-' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-600">
                            {{
                                t(
                                    'platform.hosting.fields.environments',
                                    'Environments',
                                )
                            }}:
                        </dt>
                        <dd class="font-bold text-slate-950">
                            {{ connection?.environments_count || 0 }}
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="connection?.last_error"
                    class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700 lg:col-span-2"
                >
                    {{ connection.last_error }}
                </div>
            </div>

            <div
                v-if="hasClientErrors || hasServerErrors"
                class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 sm:px-5"
            >
                <div class="font-semibold">
                    {{
                        t(
                            'platform.hosting.validation.summary_title',
                            'Saving is blocked',
                        )
                    }}
                </div>
                <div class="mt-1">
                    {{
                        t(
                            'platform.hosting.validation.summary_description',
                            'Resolve the fields below and try again.',
                        )
                    }}
                </div>
            </div>

            <CardContent class="p-4 sm:p-5">
                <form
                    id="platform-hosting-connection-form"
                    class="grid gap-5"
                    @submit.prevent="saveConnection"
                >
                    <section class="grid gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'platform.hosting.sections.connection',
                                        'Connection',
                                    )
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{
                                    t(
                                        'platform.hosting.sections.connection_help',
                                        'Name this connection and add the provider API credentials.',
                                    )
                                }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="name"
                                    :label="
                                        t(
                                            'platform.hosting.fields.name',
                                            'Name',
                                        )
                                    "
                                />
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    autocomplete="off"
                                    class="bg-yellow-50"
                                    @blur="touchField('name')"
                                />
                                <FieldError :message="fieldError('name')" />
                            </div>

                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="provider"
                                    :label="
                                        t(
                                            'platform.hosting.fields.provider',
                                            'Provider',
                                        )
                                    "
                                />
                                <select
                                    id="provider"
                                    v-model="form.provider"
                                    class="h-10 rounded-md border border-slate-300 bg-yellow-50 px-3 text-sm text-slate-900"
                                    @blur="touchField('provider')"
                                >
                                    <option
                                        v-for="providerOption in providerOptions"
                                        :key="providerOption.value"
                                        :value="providerOption.value"
                                    >
                                        {{ providerOption.label }}
                                    </option>
                                </select>
                                <FieldError :message="fieldError('provider')" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="api_base_url">
                                    {{
                                        t(
                                            'platform.hosting.fields.api_base_url',
                                            'API base URL',
                                        )
                                    }}
                                </Label>
                                <Input
                                    id="api_base_url"
                                    v-model="form.api_base_url"
                                    :placeholder="
                                        t(
                                            'platform.hosting.fields.api_base_url_placeholder',
                                            'https://cloud.laravel.com/api',
                                        )
                                    "
                                    @blur="touchField('api_base_url')"
                                />
                                <p class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'platform.hosting.fields.api_base_url_help',
                                            'Leave empty to use the official Laravel Cloud API endpoint.',
                                        )
                                    }}
                                </p>
                                <FieldError
                                    :message="fieldError('api_base_url')"
                                />
                            </div>

                            <div class="grid gap-2">
                                <RequiredLabel
                                    v-if="!form.has_api_token"
                                    for-id="api_token"
                                    :label="
                                        t(
                                            'platform.hosting.fields.api_token',
                                            'API token',
                                        )
                                    "
                                />
                                <Label v-else for="api_token">
                                    {{
                                        t(
                                            'platform.hosting.fields.api_token',
                                            'API token',
                                        )
                                    }}
                                </Label>
                                <Input
                                    id="api_token"
                                    v-model="form.api_token"
                                    type="password"
                                    autocomplete="off"
                                    :class="apiTokenClass"
                                    :placeholder="apiTokenPlaceholder"
                                    @blur="touchField('api_token')"
                                />
                                <p class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'platform.hosting.fields.api_token_help',
                                            'Stored encrypted. Leave empty on edit to keep the current token.',
                                        )
                                    }}
                                </p>
                                <FieldError
                                    :message="fieldError('api_token')"
                                />
                            </div>
                        </div>
                    </section>
                </form>

                <section
                    v-if="isEditMode"
                    class="mt-6 grid gap-4 border-t border-slate-200 pt-5"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'platform.hosting.environments.title',
                                        'Hosting environments',
                                    )
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{
                                    t(
                                        'platform.hosting.environments.description',
                                        'Remote applications and environments discovered through the provider API.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="environments.length"
                        class="overflow-hidden rounded-lg border border-slate-200"
                    >
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">
                                        {{
                                            t(
                                                'platform.hosting.fields.environment',
                                                'Environment',
                                            )
                                        }}
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        {{
                                            t(
                                                'platform.hosting.fields.application',
                                                'Application',
                                            )
                                        }}
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        {{
                                            t(
                                                'platform.hosting.fields.region',
                                                'Region',
                                            )
                                        }}
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        {{
                                            t(
                                                'platform.hosting.fields.cloud_status',
                                                'Cloud status',
                                            )
                                        }}
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        {{
                                            t(
                                                'platform.hosting.fields.last_synced_at',
                                                'Last sync',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="environment in environments"
                                    :key="environment.id"
                                    class="border-t border-slate-100"
                                >
                                    <td class="px-3 py-2 text-slate-900">
                                        <div class="font-medium">
                                            {{ environment.name }}
                                        </div>
                                        <div
                                            class="font-mono text-xs text-slate-500"
                                        >
                                            {{
                                                environment.provider_environment_id
                                            }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        <div>
                                            {{
                                                environment.metadata
                                                    ?.application?.name || '-'
                                            }}
                                        </div>
                                        <div
                                            class="font-mono text-xs text-slate-500"
                                        >
                                            {{
                                                environment.provider_application_id
                                            }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{ environment.provider_region || '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{
                                            environment.metadata?.environment
                                                ?.status || '-'
                                        }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{
                                            formatDateTime(
                                                environment.last_synced_at,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-else
                        class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-600"
                    >
                        {{
                            t(
                                'platform.hosting.environments.empty',
                                'No environments have been synced yet.',
                            )
                        }}
                    </div>
                </section>
            </CardContent>
        </Card>
    </PlatformLayout>
</template>

<script setup>
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
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
import { Head, useForm } from '@inertiajs/vue3';
import { computed, defineComponent, h, ref, watch } from 'vue';

const props = defineProps({
    connection: { type: Object, default: null },
    providerOptions: { type: Array, default: () => [] },
});

const { t } = useAdminTranslations('admin_common_ui');

const isEditMode = computed(() => Boolean(props.connection?.id));
const form = useForm({
    name: props.connection?.name || '',
    provider: props.connection?.provider || 'laravel_cloud',
    api_base_url: props.connection?.api_base_url || '',
    api_token: '',
    has_api_token: Boolean(props.connection?.has_api_token),
});
const testForm = useForm({});
const syncForm = useForm({});
const clientErrors = ref({});
const touchedFields = ref({});

const pageTitle = computed(() =>
    isEditMode.value
        ? t('platform.hosting.form.edit_title', 'Edit hosting connection')
        : t('platform.hosting.form.create_title', 'Add hosting connection'),
);

const requiredFields = computed(() => {
    const fields = ['name', 'provider'];

    if (!form.has_api_token) {
        fields.push('api_token');
    }

    return fields;
});

const hasClientErrors = computed(
    () => Object.keys(clientErrors.value).length > 0,
);
const hasServerErrors = computed(
    () => Object.keys(form.errors ?? {}).length > 0,
);
const hasBlockingClientIssues = computed(() => clientIssueFields().length > 0);
const environments = computed(() => props.connection?.environments || []);
const apiTokenPlaceholder = computed(() =>
    form.has_api_token
        ? '••••••••'
        : t(
              'platform.hosting.fields.api_token_placeholder',
              'Paste Laravel Cloud API token',
          ),
);
const apiTokenClass = computed(() =>
    form.has_api_token ? '' : 'bg-yellow-50',
);
const statusLabel = computed(() =>
    t(
        `platform.hosting.statuses.${props.connection?.status || 'not_tested'}`,
        props.connection?.status || 'Not tested',
    ),
);
const statusHelp = computed(() =>
    t(
        `platform.hosting.status_help.${props.connection?.status || 'not_tested'}`,
        'Save and test this hosting connection.',
    ),
);
const statusCardClass = computed(() => {
    if (props.connection?.status === 'ready') {
        return 'rounded-lg border border-green-200 bg-green-50 p-3 text-green-800';
    }

    if (props.connection?.status === 'failed') {
        return 'rounded-lg border border-red-200 bg-red-50 p-3 text-red-800';
    }

    return 'rounded-lg border border-orange-200 bg-orange-50 p-3 text-orange-800';
});

watch(
    () => ({ ...form.data() }),
    () => validateTouchedFields(),
    { deep: true },
);

function saveConnection() {
    if (!validateBeforeSubmit()) {
        return;
    }

    form.post(
        route('platform.hosting.store', { id: props.connection?.id || 0 }),
        {
            preserveScroll: true,
        },
    );
}

function testConnection() {
    if (!props.connection?.id) {
        return;
    }

    testForm.post(
        route('platform.hosting.test', { connection: props.connection.id }),
        {
            preserveScroll: true,
        },
    );
}

function syncEnvironments() {
    if (!props.connection?.id) {
        return;
    }

    syncForm.post(
        route('platform.hosting.environments.sync', {
            connection: props.connection.id,
        }),
        {
            preserveScroll: true,
        },
    );
}

function validateBeforeSubmit() {
    touchedFields.value = Object.fromEntries(
        [...requiredFields.value, 'api_base_url'].map((field) => [field, true]),
    );
    validateTouchedFields();

    return Object.keys(clientErrors.value).length === 0;
}

function touchField(field) {
    touchedFields.value = { ...touchedFields.value, [field]: true };
    validateTouchedFields();
}

function validateTouchedFields() {
    const errors = {};

    requiredFields.value.forEach((field) => {
        if (!touchedFields.value[field]) {
            return;
        }

        if (String(form[field] ?? '').trim() === '') {
            errors[field] = t(
                'platform.hosting.validation.required',
                'This field is required.',
            );
        }
    });

    if (
        touchedFields.value.api_base_url &&
        String(form.api_base_url || '').trim() !== ''
    ) {
        try {
            new URL(form.api_base_url);
        } catch {
            errors.api_base_url = t(
                'platform.hosting.validation.url',
                'Enter a valid URL.',
            );
        }
    }

    clientErrors.value = errors;
}

function clientIssueFields() {
    const fields = [];

    requiredFields.value.forEach((field) => {
        if (String(form[field] ?? '').trim() === '') {
            fields.push(field);
        }
    });

    if (String(form.api_base_url || '').trim() !== '') {
        try {
            new URL(form.api_base_url);
        } catch {
            fields.push('api_base_url');
        }
    }

    return [...new Set(fields)];
}

function fieldError(field) {
    return clientErrors.value[field] || form.errors[field] || '';
}

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(undefined, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

const RequiredLabel = defineComponent({
    props: {
        forId: { type: String, required: true },
        label: { type: String, required: true },
    },
    setup(componentProps) {
        return () =>
            h(
                Label,
                { for: componentProps.forId, class: 'flex items-center gap-1' },
                () => [
                    h(
                        'span',
                        { class: 'text-red-600', 'aria-hidden': 'true' },
                        '*',
                    ),
                    h('span', componentProps.label),
                ],
            );
    },
});

const FieldError = defineComponent({
    props: {
        message: { type: String, default: '' },
    },
    setup(componentProps) {
        return () =>
            componentProps.message
                ? h(
                      'p',
                      { class: 'text-sm text-red-600' },
                      componentProps.message,
                  )
                : null;
    },
});
</script>
