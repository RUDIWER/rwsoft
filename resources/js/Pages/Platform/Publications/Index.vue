<template>
    <Head :title="t('platform.publications.meta_title', 'Site publications')" />

    <PlatformLayout
        :title="t('platform.publications.title', 'Site publications')"
    >
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
                            <CardTitle class="text-lg">
                                {{
                                    t(
                                        'platform.publications.title',
                                        'Site publications',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'platform.publications.description',
                                        'Map local sites to remote hosting environments before any publish or sync run.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            as-child
                            variant="outline"
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                        >
                            <Link :href="route('platform.publications.create')">
                                <span
                                    class="mdi mdi-plus-circle text-base"
                                    aria-hidden="true"
                                />
                                {{
                                    t(
                                        'platform.publications.actions.new_publication',
                                        'New publication',
                                    )
                                }}
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <div v-if="publications.length" class="overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th
                                    class="px-4 py-2 text-left font-medium sm:px-5"
                                >
                                    {{
                                        t(
                                            'platform.publications.fields.site',
                                            'Site',
                                        )
                                    }}
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium sm:px-5"
                                >
                                    {{
                                        t(
                                            'platform.publications.fields.environment',
                                            'Environment',
                                        )
                                    }}
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium sm:px-5"
                                >
                                    {{
                                        t(
                                            'platform.publications.fields.remote_target',
                                            'Remote target',
                                        )
                                    }}
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium sm:px-5"
                                >
                                    {{
                                        t(
                                            'platform.publications.fields.database_mode',
                                            'Database mode',
                                        )
                                    }}
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium sm:px-5"
                                >
                                    {{ t('platform.columns.status', 'Status') }}
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium sm:px-5"
                                >
                                    {{
                                        t(
                                            'platform.publications.fields.latest_run',
                                            'Latest run',
                                        )
                                    }}
                                </th>
                                <th
                                    class="px-4 py-2 text-right font-medium sm:px-5"
                                >
                                    {{ t('platform.columns.action', 'Action') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="publication in publications"
                                :key="publication.id"
                                class="border-t border-slate-100"
                            >
                                <td class="px-4 py-3 text-slate-900 sm:px-5">
                                    <div class="font-medium">
                                        {{ publication.site?.name || '-' }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ publication.site?.slug || '-' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600 sm:px-5">
                                    <div class="font-medium text-slate-800">
                                        {{
                                            publication.hosting_environment
                                                ?.name || '-'
                                        }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{
                                            publication.hosting_connection
                                                ?.name || '-'
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600 sm:px-5">
                                    <div class="font-medium text-slate-800">
                                        {{ publication.remote_domain || '-' }}
                                    </div>
                                    <div
                                        class="font-mono text-xs text-slate-500"
                                    >
                                        {{ publication.remote_site_slug }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600 sm:px-5">
                                    {{
                                        databaseModeLabel(
                                            publication.remote_tenant_database_mode,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3 sm:px-5">
                                    <span
                                        :class="statusClass(publication.status)"
                                    >
                                        {{ statusLabel(publication.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 sm:px-5">
                                    <div>
                                        {{
                                            latestRunLabel(
                                                publication.latest_run,
                                            )
                                        }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{
                                            formatDateTime(
                                                publication.latest_run
                                                    ?.finished_at,
                                            )
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right sm:px-5">
                                    <Button
                                        as-child
                                        size="sm"
                                        variant="outline"
                                        class="shadow-none"
                                    >
                                        <Link
                                            :href="
                                                route(
                                                    'platform.publications.edit',
                                                    {
                                                        id: publication.id,
                                                    },
                                                )
                                            "
                                        >
                                            {{ t('actions.edit', 'Edit') }}
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="px-4 py-10 text-center text-sm text-slate-600 sm:px-5"
                >
                    {{
                        t(
                            'platform.publications.empty',
                            'No site publications have been configured yet.',
                        )
                    }}
                </div>
            </CardContent>
        </Card>
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
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    publications: { type: Array, required: true },
});

const { t } = useAdminTranslations('admin_common_ui');

function databaseModeLabel(mode) {
    return t(
        `platform.publications.database_modes.${mode || 'shared_prefixed'}`,
        mode || 'Shared prefixed',
    );
}

function statusLabel(status) {
    return t(
        `platform.publications.statuses.${status || 'draft'}`,
        status || 'Draft',
    );
}

function statusClass(status) {
    if (status === 'ready' || status === 'synced') {
        return 'inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700 ring-1 ring-green-200';
    }

    if (status === 'failed') {
        return 'inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 ring-1 ring-red-200';
    }

    return 'inline-flex rounded-full bg-orange-50 px-2 py-0.5 text-xs font-semibold text-orange-700 ring-1 ring-orange-200';
}

function latestRunLabel(run) {
    if (!run) {
        return t('platform.publications.run.none', 'No run yet');
    }

    return t(
        `platform.publications.run.statuses.${run.status || 'pending'}`,
        run.status || 'Pending',
    );
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
</script>
