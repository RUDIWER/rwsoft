<template>
    <Head :title="t('platform.sites.meta_title', 'Platform Sites')" />

    <PlatformLayout :title="t('platform.sites.title', 'Sites')">
        <Card class="border-slate-200 bg-white shadow-sm">
            <CardHeader
                class="flex flex-row items-center justify-between gap-3"
            >
                <div>
                    <CardTitle>{{
                        t('platform.sites.title', 'Sites')
                    }}</CardTitle>
                    <CardDescription>
                        {{
                            t(
                                'platform.sites.description',
                                'Central configuration of domains and tenant databases.',
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
                <div class="overflow-hidden rounded-lg border border-slate-200">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">
                                    {{ t('platform.columns.name', 'Name') }}
                                </th>
                                <th class="px-3 py-2 text-left font-medium">
                                    {{
                                        t(
                                            'platform.columns.primary_domain',
                                            'Primary domain',
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
                                    {{ t('platform.columns.status', 'Status') }}
                                </th>
                                <th class="px-3 py-2 text-left font-medium">
                                    {{ t('platform.columns.users', 'Users') }}
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    {{ t('platform.columns.action', 'Action') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="site in sites"
                                :key="site.id"
                                class="border-t border-slate-100"
                            >
                                <td class="px-3 py-2 text-slate-900">
                                    <div class="font-medium">
                                        {{ site.name }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ site.slug }}
                                    </div>
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
                                <td class="px-3 py-2 text-slate-600">
                                    {{ site.memberships_count }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <Button
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link
                                            :href="
                                                route('platform.sites.edit', {
                                                    id: site.id,
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
    sites: { type: Array, required: true },
});

const { t } = useAdminTranslations('admin_common_ui');
</script>
