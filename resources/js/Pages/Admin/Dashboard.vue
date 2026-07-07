<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RwActionButton from '@/Components/RwActionButton.vue';
import RwFormTemplate from '@/Components/RwFormTemplate.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Head, router } from '@inertiajs/vue3';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineProps({
    stats: {
        type: Object,
        required: true,
    },
    recentUsers: {
        type: Array,
        required: true,
    },
});

const { t } = useAdminTranslations('admin_security_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');

function goToUsers() {
    router.visit(route('admin.users'));
}

function goToQueryBuilder() {
    router.visit(route('admin.queries.builder.index'));
}
</script>

<template>
    <Head :title="t('dashboard.meta_title', 'Admin Dashboard')" />

    <AdminLayout>
        <RwFormTemplate
            :title="t('dashboard.title', 'Dashboard')"
            :subtitle="
                t(
                    'dashboard.subtitle',
                    'Overview of the current backoffice, security and CMS data.',
                )
            "
        >
            <template #actions>
                <RwActionButton
                    :label="t('users.meta_title', 'Users')"
                    icon="mdi mdi-account-group"
                    @click="goToUsers"
                />
                <RwActionButton
                    :label="
                        commonT('navigation.query_builder', 'Query Builder')
                    "
                    icon="mdi mdi-database-search"
                    @click="goToQueryBuilder"
                />
            </template>

            <div class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardDescription>{{
                                t('dashboard.total_users', 'Total users')
                            }}</CardDescription>
                            <CardTitle class="text-3xl">{{
                                stats.users
                            }}</CardTitle>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>{{
                                t('dashboard.active_roles', 'Active roles')
                            }}</CardDescription>
                            <CardTitle class="text-3xl">{{
                                stats.roles
                            }}</CardTitle>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>{{
                                t('dashboard.acl_permissions', 'ACL rights')
                            }}</CardDescription>
                            <CardTitle class="text-3xl">{{
                                stats.permissions
                            }}</CardTitle>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>{{
                                t(
                                    'dashboard.published_pages',
                                    'Published pages',
                                )
                            }}</CardDescription>
                            <CardTitle class="text-3xl">{{
                                stats.published_pages
                            }}</CardTitle>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>{{
                                t(
                                    'dashboard.published_posts',
                                    'Published posts',
                                )
                            }}</CardDescription>
                            <CardTitle class="text-3xl">{{
                                stats.published_posts
                            }}</CardTitle>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>{{
                                t('dashboard.active_forms', 'Active forms')
                            }}</CardDescription>
                            <CardTitle class="text-3xl">{{
                                stats.active_forms
                            }}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <div>
                            <CardTitle>{{
                                t(
                                    'dashboard.recent_users',
                                    'Recently created users',
                                )
                            }}</CardTitle>
                            <CardDescription>{{
                                t(
                                    'dashboard.recent_users_subtitle',
                                    'Quick check on new accounts.',
                                )
                            }}</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            class="overflow-hidden rounded-lg border border-slate-200"
                        >
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th
                                            class="px-3 py-2 text-left font-medium"
                                        >
                                            {{ t('columns.name', 'Name') }}
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left font-medium"
                                        >
                                            {{ t('columns.email', 'E-mail') }}
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left font-medium"
                                        >
                                            {{
                                                t(
                                                    'dashboard.created_at',
                                                    'Created',
                                                )
                                            }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="user in recentUsers"
                                        :key="user.id"
                                        class="border-t border-slate-100"
                                    >
                                        <td class="px-3 py-2 text-slate-900">
                                            {{ user.name }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{ user.email }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{ user.created_at }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </RwFormTemplate>
    </AdminLayout>
</template>
