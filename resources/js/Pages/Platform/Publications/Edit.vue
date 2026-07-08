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
                            <span class="mdi mdi-cloud-upload text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">{{
                                pageTitle
                            }}</CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'platform.publications.form.description',
                                        'Configure where a local site will be published. This only stores the target mapping.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <AdminFormBackButton
                            :href="route('platform.publications.index')"
                            :dirty="form.isDirty"
                            :processing="form.processing"
                            @save="savePublication"
                        />
                        <Button
                            v-if="isEditMode"
                            type="button"
                            variant="outline"
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800 disabled:opacity-60"
                            :disabled="form.isDirty || prepareForm.processing"
                            @click="preparePublish"
                        >
                            <span
                                :class="[
                                    'mdi text-base',
                                    prepareForm.processing
                                        ? 'mdi-loading animate-spin'
                                        : 'mdi-clipboard-check-outline',
                                ]"
                                aria-hidden="true"
                            />
                            {{
                                t(
                                    'platform.publications.actions.prepare_publish',
                                    'Prepare publish',
                                )
                            }}
                        </Button>
                        <Button
                            v-if="isEditMode"
                            type="button"
                            variant="outline"
                            class="gap-2 border-orange-200 text-orange-700 shadow-none hover:bg-orange-50 hover:text-orange-800 disabled:opacity-60"
                            :disabled="
                                form.isDirty ||
                                createDatabaseForm.processing ||
                                !latestPreflightRun ||
                                hasProvisionedDatabase
                            "
                            @click="createDatabase"
                        >
                            <span
                                :class="[
                                    'mdi text-base',
                                    createDatabaseForm.processing
                                        ? 'mdi-loading animate-spin'
                                        : 'mdi-database-plus-outline',
                                ]"
                                aria-hidden="true"
                            />
                            {{
                                t(
                                    'platform.publications.actions.create_database',
                                    'Create database',
                                )
                            }}
                        </Button>
                        <Button
                            v-if="isEditMode"
                            type="button"
                            variant="outline"
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800 disabled:opacity-60"
                            :disabled="
                                form.isDirty ||
                                databaseForm.processing ||
                                !latestPreflightRun
                            "
                            @click="provisionDatabase"
                        >
                            <span
                                :class="[
                                    'mdi text-base',
                                    databaseForm.processing
                                        ? 'mdi-loading animate-spin'
                                        : 'mdi-database-check-outline',
                                ]"
                                aria-hidden="true"
                            />
                            {{
                                t(
                                    'platform.publications.actions.provision_database',
                                    'Check database',
                                )
                            }}
                        </Button>
                        <Button
                            v-if="isEditMode"
                            type="button"
                            variant="outline"
                            class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800 disabled:opacity-60"
                            :disabled="
                                form.isDirty ||
                                applyEnvVarsForm.processing ||
                                !hasProvisionedDatabase
                            "
                            @click="applyEnvVars"
                        >
                            <span
                                :class="[
                                    'mdi text-base',
                                    applyEnvVarsForm.processing
                                        ? 'mdi-loading animate-spin'
                                        : 'mdi-cloud-upload-outline',
                                ]"
                                aria-hidden="true"
                            />
                            {{
                                t(
                                    'platform.publications.actions.apply_env_vars',
                                    'Apply env vars',
                                )
                            }}
                        </Button>
                        <Button
                            v-if="isEditMode"
                            type="button"
                            variant="outline"
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800 disabled:opacity-60"
                            :disabled="
                                form.isDirty ||
                                applyDomainForm.processing ||
                                !hasPlannedDomain
                            "
                            @click="applyDomain"
                        >
                            <span
                                :class="[
                                    'mdi text-base',
                                    applyDomainForm.processing
                                        ? 'mdi-loading animate-spin'
                                        : 'mdi-web-sync',
                                ]"
                                aria-hidden="true"
                            />
                            {{
                                t(
                                    'platform.publications.actions.apply_domain',
                                    'Apply domain',
                                )
                            }}
                        </Button>
                        <Button
                            v-if="isEditMode"
                            type="button"
                            variant="outline"
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800 disabled:opacity-60"
                            :disabled="
                                form.isDirty ||
                                deploymentForm.processing ||
                                !hasAppliedEnvVars
                            "
                            @click="startDeployment"
                        >
                            <span
                                :class="[
                                    'mdi text-base',
                                    deploymentForm.processing
                                        ? 'mdi-loading animate-spin'
                                        : 'mdi-rocket-launch-outline',
                                ]"
                                aria-hidden="true"
                            />
                            {{
                                t(
                                    'platform.publications.actions.start_deployment',
                                    'Start deployment',
                                )
                            }}
                        </Button>
                        <Button
                            v-if="isEditMode"
                            type="button"
                            variant="outline"
                            class="gap-2 border-purple-200 text-purple-700 shadow-none hover:bg-purple-50 hover:text-purple-800 disabled:opacity-60"
                            :disabled="
                                form.isDirty ||
                                remoteSetupForm.processing ||
                                !hasStartedDeployment
                            "
                            @click="runRemoteSetup"
                        >
                            <span
                                :class="[
                                    'mdi text-base',
                                    remoteSetupForm.processing
                                        ? 'mdi-loading animate-spin'
                                        : 'mdi-console-line',
                                ]"
                                aria-hidden="true"
                            />
                            {{
                                t(
                                    'platform.publications.actions.remote_setup',
                                    'Run remote setup',
                                )
                            }}
                        </Button>
                        <AdminFormSaveButton
                            type="button"
                            :dirty="form.isDirty"
                            :processing="form.processing"
                            @click="savePublication"
                        />
                    </div>
                </div>
            </CardHeader>

            <div
                v-if="isEditMode"
                class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 sm:px-5"
            >
                <div class="font-medium text-slate-700">
                    {{ t('record_meta.id', 'ID') }}:
                    <span class="ml-1 text-base font-bold text-slate-950">
                        {{ publication?.id || '-' }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                    <div class="font-medium text-slate-700">
                        {{ t('record_meta.updated_at', 'Updated') }}:
                        <span class="ml-1 text-base font-bold text-slate-950">
                            {{ formatDate(publication?.updated_at) }}
                        </span>
                    </div>
                    <div class="font-medium text-slate-700">
                        {{ t('record_meta.created_at', 'Created') }}:
                        <span class="ml-1 text-base font-bold text-slate-950">
                            {{ formatDate(publication?.created_at) }}
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-if="Object.keys(form.errors ?? {}).length"
                class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 sm:px-5"
            >
                <div class="font-semibold">
                    {{ t('validation.summary_title', 'Saving is blocked') }}
                </div>
                <div class="mt-1">
                    {{
                        t(
                            'validation.summary_description',
                            'Resolve the fields below and try again.',
                        )
                    }}
                </div>
            </div>

            <CardContent class="p-4 sm:p-5">
                <form class="grid gap-5" @submit.prevent="savePublication">
                    <section class="grid gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'platform.publications.sections.target',
                                        'Publication target',
                                    )
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{
                                    t(
                                        'platform.publications.sections.target_help',
                                        'Choose the local site and the synced remote hosting environment.',
                                    )
                                }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="site_id"
                                    :label="
                                        t(
                                            'platform.publications.fields.site',
                                            'Site',
                                        )
                                    "
                                />
                                <select
                                    id="site_id"
                                    v-model="form.site_id"
                                    class="h-10 rounded-md border border-slate-300 bg-yellow-50 px-3 text-sm text-slate-900"
                                    @change="applySiteDefaults"
                                >
                                    <option value="">
                                        {{ t('select.none', 'No selection') }}
                                    </option>
                                    <option
                                        v-for="site in siteOptions"
                                        :key="site.value"
                                        :value="site.value"
                                    >
                                        {{ site.label }}
                                    </option>
                                </select>
                                <FieldError :message="form.errors.site_id" />
                            </div>

                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="hosting_environment_id"
                                    :label="
                                        t(
                                            'platform.publications.fields.environment',
                                            'Environment',
                                        )
                                    "
                                />
                                <select
                                    id="hosting_environment_id"
                                    v-model="form.hosting_environment_id"
                                    class="h-10 rounded-md border border-slate-300 bg-yellow-50 px-3 text-sm text-slate-900"
                                    @change="applyEnvironmentDefaults"
                                >
                                    <option value="">
                                        {{ t('select.none', 'No selection') }}
                                    </option>
                                    <option
                                        v-for="environment in environmentOptions"
                                        :key="environment.value"
                                        :value="environment.value"
                                    >
                                        {{ environment.label }}
                                    </option>
                                </select>
                                <FieldError
                                    :message="
                                        form.errors.hosting_environment_id
                                    "
                                />
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-4 border-t border-slate-200 pt-5">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'platform.publications.sections.remote_site',
                                        'Remote site identity',
                                    )
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{
                                    t(
                                        'platform.publications.sections.remote_site_help',
                                        'These values identify the site inside the remote RwSoft platform.',
                                    )
                                }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="remote_site_slug"
                                    :label="
                                        t(
                                            'platform.publications.fields.remote_site_slug',
                                            'Remote site slug',
                                        )
                                    "
                                />
                                <Input
                                    id="remote_site_slug"
                                    v-model="form.remote_site_slug"
                                    class="bg-yellow-50"
                                    autocomplete="off"
                                />
                                <FieldError
                                    :message="form.errors.remote_site_slug"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="remote_domain">
                                    {{
                                        t(
                                            'platform.publications.fields.remote_domain',
                                            'Remote domain',
                                        )
                                    }}
                                </Label>
                                <Input
                                    id="remote_domain"
                                    v-model="form.remote_domain"
                                    autocomplete="off"
                                    :placeholder="
                                        t(
                                            'platform.publications.fields.remote_domain_placeholder',
                                            'www.example.com',
                                        )
                                    "
                                />
                                <FieldError
                                    :message="form.errors.remote_domain"
                                />
                            </div>
                        </div>
                    </section>

                    <section class="grid gap-4 border-t border-slate-200 pt-5">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'platform.publications.sections.database',
                                        'Remote tenant database',
                                    )
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{
                                    t(
                                        'platform.publications.sections.database_help',
                                        'Choose how this site will store tenant tables on the remote hosting environment.',
                                    )
                                }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <RequiredLabel
                                    for-id="remote_tenant_database_mode"
                                    :label="
                                        t(
                                            'platform.publications.fields.database_mode',
                                            'Database mode',
                                        )
                                    "
                                />
                                <select
                                    id="remote_tenant_database_mode"
                                    v-model="form.remote_tenant_database_mode"
                                    class="h-10 rounded-md border border-slate-300 bg-yellow-50 px-3 text-sm text-slate-900"
                                >
                                    <option
                                        v-for="mode in databaseModeOptions"
                                        :key="mode.value"
                                        :value="mode.value"
                                    >
                                        {{ mode.label }}
                                    </option>
                                </select>
                                <FieldError
                                    :message="
                                        form.errors.remote_tenant_database_mode
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="remote_tenant_database">
                                    {{
                                        t(
                                            'platform.publications.fields.remote_database',
                                            'Remote database',
                                        )
                                    }}
                                </Label>
                                <Input
                                    id="remote_tenant_database"
                                    v-model="form.remote_tenant_database"
                                    autocomplete="off"
                                    :class="databaseRequiredClass"
                                />
                                <FieldError
                                    :message="
                                        form.errors.remote_tenant_database
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="remote_tenant_table_prefix">
                                    {{
                                        t(
                                            'platform.publications.fields.remote_table_prefix',
                                            'Remote table prefix',
                                        )
                                    }}
                                </Label>
                                <Input
                                    id="remote_tenant_table_prefix"
                                    v-model="form.remote_tenant_table_prefix"
                                    autocomplete="off"
                                    :class="prefixRequiredClass"
                                />
                                <FieldError
                                    :message="
                                        form.errors.remote_tenant_table_prefix
                                    "
                                />
                            </div>
                        </div>
                    </section>
                </form>

                <section
                    v-if="isEditMode"
                    class="mt-6 grid gap-4 border-t border-slate-200 pt-5"
                >
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">
                            {{
                                t(
                                    'platform.publications.preflight.title',
                                    'Latest preflight',
                                )
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{
                                t(
                                    'platform.publications.preflight.description',
                                    'A prepare run validates the local mapping and records what a future publish would need.',
                                )
                            }}
                        </p>
                    </div>

                    <div
                        v-if="latestRun"
                        class="overflow-hidden rounded-lg border border-slate-200"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                        >
                            <div class="font-semibold text-slate-800">
                                {{ runStatusLabel(latestRun.status) }}
                            </div>
                            <div class="text-xs text-slate-500">
                                {{ formatDateTime(latestRun.finished_at) }}
                            </div>
                        </div>

                        <div class="divide-y divide-slate-100">
                            <div
                                v-for="step in latestRun.steps || []"
                                :key="step.key"
                                class="grid gap-1 px-3 py-3 text-sm sm:grid-cols-[12rem_minmax(0,1fr)] sm:items-start"
                            >
                                <div
                                    class="flex items-center gap-2 font-medium text-slate-800"
                                >
                                    <span :class="stepStatusClass(step.status)">
                                        {{ stepStatusLabel(step.status) }}
                                    </span>
                                    <span>{{ step.label }}</span>
                                </div>
                                <div class="text-slate-600">
                                    {{ step.message }}
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="latestRun.error_message"
                            class="border-t border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                        >
                            {{ latestRun.error_message }}
                        </div>

                        <div
                            v-if="latestRun.options?.site_package_filename"
                            class="border-t border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                        >
                            <span class="font-semibold">
                                {{
                                    t(
                                        'platform.publications.preflight.artifact',
                                        'Artifact',
                                    )
                                }}:
                            </span>
                            <span class="ml-1 font-mono text-xs">
                                {{ latestRun.options.site_package_filename }}
                            </span>
                            <span
                                v-if="latestRun.options.site_package_key"
                                class="ml-2 text-xs text-slate-500"
                            >
                                {{ latestRun.options.site_package_key }}
                            </span>
                        </div>

                        <div
                            v-if="providerPlan"
                            class="grid gap-4 border-t border-slate-200 px-3 py-4 text-sm"
                        >
                            <div>
                                <h3 class="font-semibold text-slate-900">
                                    {{
                                        t(
                                            'platform.publications.provider_plan.title',
                                            'Provider dry-run plan',
                                        )
                                    }}
                                </h3>
                                <p class="mt-1 text-slate-600">
                                    {{
                                        t(
                                            'platform.publications.provider_plan.description',
                                            'These actions are planned only. Nothing has been changed on the remote provider.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <div class="font-medium text-slate-800">
                                    {{
                                        t(
                                            'platform.publications.provider_plan.env_vars',
                                            'Environment variables',
                                        )
                                    }}
                                </div>
                                <div
                                    class="overflow-hidden rounded border border-slate-200"
                                >
                                    <table class="w-full text-xs">
                                        <tbody>
                                            <tr
                                                v-for="variable in providerPlan.env_vars ||
                                                []"
                                                :key="variable.key"
                                                class="border-t border-slate-100 first:border-t-0"
                                            >
                                                <td
                                                    class="w-56 px-3 py-2 font-mono text-slate-700"
                                                >
                                                    {{ variable.key }}
                                                </td>
                                                <td
                                                    class="px-3 py-2 font-mono text-slate-500"
                                                >
                                                    {{ variable.value || '-' }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <div class="font-medium text-slate-800">
                                    {{
                                        t(
                                            'platform.publications.provider_plan.domains',
                                            'Domains',
                                        )
                                    }}
                                </div>
                                <div
                                    v-if="(providerPlan.domains || []).length"
                                    class="grid gap-2"
                                >
                                    <div
                                        v-for="domain in providerPlan.domains"
                                        :key="domain.domain"
                                        class="rounded border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-600"
                                    >
                                        {{ domain.action }}: {{ domain.domain }}
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="rounded border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-slate-600"
                                >
                                    {{
                                        t(
                                            'platform.publications.provider_plan.no_domains',
                                            'No remote domain action is planned.',
                                        )
                                    }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <div class="font-medium text-slate-800">
                                    {{
                                        t(
                                            'platform.publications.provider_plan.commands',
                                            'Remote commands',
                                        )
                                    }}
                                </div>
                                <div class="grid gap-2">
                                    <div
                                        v-for="command in providerPlan.commands ||
                                        []"
                                        :key="command.key"
                                        class="rounded border border-slate-200 bg-slate-50 px-3 py-2"
                                    >
                                        <div
                                            class="text-xs font-semibold text-slate-700"
                                        >
                                            {{ command.key }}
                                        </div>
                                        <div
                                            class="mt-1 font-mono text-xs text-slate-600"
                                        >
                                            {{ command.command }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-600"
                    >
                        {{
                            t(
                                'platform.publications.preflight.empty',
                                'No preflight run has been executed yet.',
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
import { computed, defineComponent, h } from 'vue';

const props = defineProps({
    publication: { type: Object, default: null },
    siteOptions: { type: Array, default: () => [] },
    environmentOptions: { type: Array, default: () => [] },
    databaseModeOptions: { type: Array, default: () => [] },
});

const { t } = useAdminTranslations('admin_common_ui');

const isEditMode = computed(() => Boolean(props.publication?.id));
const pageTitle = computed(() =>
    isEditMode.value
        ? t('platform.publications.form.edit_title', 'Edit publication')
        : t('platform.publications.form.create_title', 'Add publication'),
);

const form = useForm({
    site_id: props.publication?.site_id || '',
    hosting_environment_id: props.publication?.hosting_environment_id || '',
    remote_site_slug: props.publication?.remote_site_slug || '',
    remote_domain: props.publication?.remote_domain || '',
    remote_tenant_database_mode:
        props.publication?.remote_tenant_database_mode || 'shared_prefixed',
    remote_tenant_database: props.publication?.remote_tenant_database || '',
    remote_tenant_table_prefix:
        props.publication?.remote_tenant_table_prefix || '',
});
const prepareForm = useForm({});
const databaseForm = useForm({});
const createDatabaseForm = useForm({});
const applyEnvVarsForm = useForm({});
const applyDomainForm = useForm({});
const deploymentForm = useForm({});
const remoteSetupForm = useForm({});

const selectedSite = computed(() =>
    props.siteOptions.find(
        (site) => String(site.value) === String(form.site_id),
    ),
);
const selectedEnvironment = computed(() =>
    props.environmentOptions.find(
        (environment) =>
            String(environment.value) === String(form.hosting_environment_id),
    ),
);
const databaseRequiredClass = computed(() =>
    ['separate', 'existing_database'].includes(form.remote_tenant_database_mode)
        ? 'bg-yellow-50'
        : '',
);
const prefixRequiredClass = computed(() =>
    form.remote_tenant_database_mode === 'shared_prefixed'
        ? 'bg-yellow-50'
        : '',
);
const latestRun = computed(() => props.publication?.latest_run || null);
const latestPreflightRun = computed(
    () => props.publication?.latest_preflight_run || null,
);
const providerPlan = computed(
    () =>
        latestPreflightRun.value?.options?.provider_plan ||
        latestRun.value?.options?.provider_plan ||
        null,
);
const hasPlannedDomain = computed(
    () =>
        Boolean(latestPreflightRun.value) &&
        (providerPlan.value?.domains || []).length > 0,
);
const hasProvisionedDatabase = computed(
    () => props.publication?.metadata?.last_database_status === 'completed',
);
const hasAppliedEnvVars = computed(
    () => props.publication?.metadata?.last_env_var_status === 'completed',
);
const hasStartedDeployment = computed(
    () => props.publication?.metadata?.last_deployment_status === 'completed',
);

function savePublication() {
    form.post(
        route('platform.publications.store', {
            id: props.publication?.id || 0,
        }),
        {
            preserveScroll: true,
        },
    );
}

function preparePublish() {
    if (!props.publication?.id || form.isDirty) {
        return;
    }

    prepareForm.post(
        route('platform.publications.prepare-publish', {
            publication: props.publication.id,
        }),
        {
            preserveScroll: true,
        },
    );
}

function provisionDatabase() {
    if (!props.publication?.id || form.isDirty || !latestPreflightRun.value) {
        return;
    }

    databaseForm.post(
        route('platform.publications.provision-database', {
            publication: props.publication.id,
        }),
        {
            preserveScroll: true,
        },
    );
}

function createDatabase() {
    if (
        !props.publication?.id ||
        form.isDirty ||
        !latestPreflightRun.value ||
        hasProvisionedDatabase.value
    ) {
        return;
    }

    createDatabaseForm.post(
        route('platform.publications.create-database', {
            publication: props.publication.id,
        }),
        {
            preserveScroll: true,
        },
    );
}

function applyEnvVars() {
    if (
        !props.publication?.id ||
        form.isDirty ||
        !hasProvisionedDatabase.value
    ) {
        return;
    }

    applyEnvVarsForm.post(
        route('platform.publications.apply-env-vars', {
            publication: props.publication.id,
        }),
        {
            preserveScroll: true,
        },
    );
}

function applyDomain() {
    if (!props.publication?.id || form.isDirty || !hasPlannedDomain.value) {
        return;
    }

    applyDomainForm.post(
        route('platform.publications.apply-domain', {
            publication: props.publication.id,
        }),
        {
            preserveScroll: true,
        },
    );
}

function startDeployment() {
    if (!props.publication?.id || form.isDirty || !hasAppliedEnvVars.value) {
        return;
    }

    deploymentForm.post(
        route('platform.publications.start-deployment', {
            publication: props.publication.id,
        }),
        {
            preserveScroll: true,
        },
    );
}

function runRemoteSetup() {
    if (!props.publication?.id || form.isDirty || !hasStartedDeployment.value) {
        return;
    }

    remoteSetupForm.post(
        route('platform.publications.remote-setup', {
            publication: props.publication.id,
        }),
        {
            preserveScroll: true,
        },
    );
}

function applySiteDefaults() {
    if (!selectedSite.value || isEditMode.value) {
        return;
    }

    if (!form.remote_site_slug) {
        form.remote_site_slug = selectedSite.value.slug || '';
    }

    if (!form.remote_tenant_table_prefix) {
        form.remote_tenant_table_prefix = `t_${String(selectedSite.value.slug || 'site').replace(/-/g, '_')}_`;
    }
}

function applyEnvironmentDefaults() {
    if (!selectedEnvironment.value || isEditMode.value) {
        return;
    }

    form.remote_tenant_database_mode =
        selectedEnvironment.value.default_tenant_database_mode ||
        form.remote_tenant_database_mode;

    if (!form.remote_tenant_database) {
        form.remote_tenant_database =
            selectedEnvironment.value.default_database_name || '';
    }
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(undefined, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
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

function runStatusLabel(status) {
    return t(
        `platform.publications.run.statuses.${status || 'pending'}`,
        status || 'Pending',
    );
}

function stepStatusLabel(status) {
    return t(
        `platform.publications.preflight.step_statuses.${status || 'pending'}`,
        status || 'Pending',
    );
}

function stepStatusClass(status) {
    if (status === 'passed') {
        return 'inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700 ring-1 ring-green-200';
    }

    if (status === 'failed') {
        return 'inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 ring-1 ring-red-200';
    }

    return 'inline-flex rounded-full bg-orange-50 px-2 py-0.5 text-xs font-semibold text-orange-700 ring-1 ring-orange-200';
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
