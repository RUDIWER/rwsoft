<template>
    <Head :title="t('platform.dashboard.meta_title', 'Platform Dashboard')" />

    <PlatformLayout
        :title="t('platform.dashboard.title', 'Platform Dashboard')"
    >
        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <Card
                    v-for="item in statItems"
                    :key="item.label"
                    class="border-slate-200 bg-white shadow-sm"
                >
                    <CardHeader>
                        <CardDescription>{{ item.label }}</CardDescription>
                        <CardTitle class="text-3xl">{{ item.value }}</CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <Card class="border-slate-200 bg-white shadow-sm">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-3"
                >
                    <div>
                        <CardTitle>{{
                            t(
                                'platform.dashboard.recent_sites.title',
                                'Recent sites',
                            )
                        }}</CardTitle>
                        <CardDescription>
                            {{
                                t(
                                    'platform.dashboard.recent_sites.description',
                                    'Recently created tenant sites and provisioning status.',
                                )
                            }}
                        </CardDescription>
                    </div>
                    <Button as-child>
                        <Link :href="route('platform.sites.create')">
                            {{ t('platform.actions.new_site', 'New site') }}
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <div
                        class="overflow-hidden rounded-lg border border-slate-200"
                    >
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">
                                        {{ t('platform.columns.name', 'Name') }}
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        {{
                                            t(
                                                'platform.columns.domain',
                                                'Domain',
                                            )
                                        }}
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        {{
                                            t(
                                                'platform.columns.database',
                                                'Database',
                                            )
                                        }}
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium">
                                        {{
                                            t(
                                                'platform.columns.status',
                                                'Status',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="site in recentSites"
                                    :key="site.id"
                                    class="border-t border-slate-100"
                                >
                                    <td class="px-3 py-2 text-slate-900">
                                        <Link
                                            :href="
                                                route('platform.sites.edit', {
                                                    id: site.id,
                                                })
                                            "
                                            class="font-medium text-blue-700 hover:underline"
                                        >
                                            {{ site.name }}
                                        </Link>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{ site.primary_domain?.host || '-' }}
                                    </td>
                                    <td
                                        class="px-3 py-2 font-mono text-xs text-slate-600"
                                    >
                                        {{ site.tenant_database }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{ site.status }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
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
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: { type: Object, required: true },
    recentSites: { type: Array, required: true },
});

const { t } = useAdminTranslations('admin_common_ui');

const statItems = computed(() => [
    { label: t('platform.stats.sites', 'Sites'), value: props.stats.sites },
    {
        label: t('platform.stats.active_sites', 'Active sites'),
        value: props.stats.active_sites,
    },
    {
        label: t('platform.stats.domains', 'Domains'),
        value: props.stats.domains,
    },
    {
        label: t('platform.stats.memberships', 'Memberships'),
        value: props.stats.memberships,
    },
    {
        label: t('platform.stats.platform_admins', 'Platform admins'),
        value: props.stats.platform_admins,
    },
]);
</script>
