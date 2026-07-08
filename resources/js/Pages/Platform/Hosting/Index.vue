<template>
    <Head :title="t('platform.hosting.meta_title', 'Hosting connections')" />

    <PlatformLayout :title="t('platform.hosting.title', 'Hosting connections')">
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
                            <CardTitle class="text-lg">
                                {{
                                    t(
                                        'platform.hosting.title',
                                        'Hosting connections',
                                    )
                                }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'platform.hosting.description',
                                        'Manage provider connections used to publish local sites to remote hosting environments.',
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
                            <Link :href="route('platform.hosting.create')">
                                <span
                                    class="mdi mdi-plus-circle text-base"
                                    aria-hidden="true"
                                />
                                {{
                                    t(
                                        'platform.hosting.actions.new_connection',
                                        'New connection',
                                    )
                                }}
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <div v-if="connections.length" class="overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th
                                    class="px-4 py-2 text-left font-medium sm:px-5"
                                >
                                    {{ t('platform.columns.name', 'Name') }}
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium sm:px-5"
                                >
                                    {{
                                        t(
                                            'platform.hosting.fields.provider',
                                            'Provider',
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
                                            'platform.hosting.fields.environments',
                                            'Environments',
                                        )
                                    }}
                                </th>
                                <th
                                    class="px-4 py-2 text-left font-medium sm:px-5"
                                >
                                    {{
                                        t(
                                            'platform.hosting.fields.last_checked_at',
                                            'Last check',
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
                                v-for="connection in connections"
                                :key="connection.id"
                                class="border-t border-slate-100"
                            >
                                <td class="px-4 py-3 text-slate-900 sm:px-5">
                                    <div class="font-medium">
                                        {{ connection.name }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{
                                            connection.has_api_token
                                                ? t(
                                                      'platform.hosting.status.token_configured',
                                                      'Token configured',
                                                  )
                                                : t(
                                                      'platform.hosting.status.token_missing',
                                                      'Token missing',
                                                  )
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600 sm:px-5">
                                    {{ providerLabel(connection.provider) }}
                                </td>
                                <td class="px-4 py-3 sm:px-5">
                                    <span
                                        :class="statusClass(connection.status)"
                                    >
                                        {{ statusLabel(connection.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 sm:px-5">
                                    {{ connection.environments_count }}
                                </td>
                                <td class="px-4 py-3 text-slate-600 sm:px-5">
                                    {{
                                        formatDateTime(
                                            connection.last_checked_at,
                                        )
                                    }}
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
                                                route('platform.hosting.edit', {
                                                    id: connection.id,
                                                })
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
                            'platform.hosting.empty',
                            'No hosting connections have been configured yet.',
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
    connections: { type: Array, required: true },
});

const { t } = useAdminTranslations('admin_common_ui');

function providerLabel(provider) {
    return t(`platform.hosting.providers.${provider}`, provider);
}

function statusLabel(status) {
    return t(
        `platform.hosting.statuses.${status || 'unknown'}`,
        status || 'Unknown',
    );
}

function statusClass(status) {
    if (status === 'ready') {
        return 'inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700 ring-1 ring-green-200';
    }

    if (status === 'failed') {
        return 'inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 ring-1 ring-red-200';
    }

    return 'inline-flex rounded-full bg-orange-50 px-2 py-0.5 text-xs font-semibold text-orange-700 ring-1 ring-orange-200';
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
