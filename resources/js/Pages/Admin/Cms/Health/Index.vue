<template>
    <Head :title="t('health.page_title', 'CMS kwaliteit')" />

    <AdminLayout :title="t('health.page_title', 'CMS kwaliteit')">
        <div class="grid gap-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">
                        {{ t('health.title', 'CMS kwaliteit') }}
                    </h1>
                    <p class="text-sm text-slate-500">
                        {{
                            t(
                                'health.description',
                                'Controleer publicatierisico’s, kapotte verwijzingen en ontbrekende contentkwaliteit.',
                            )
                        }}
                    </p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="route('admin.cms.pages.index')">
                        {{ t('actions.back', 'Back') }}
                    </Link>
                </Button>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader>
                        <CardDescription>{{
                            t('health.summary.total', 'Totaal')
                        }}</CardDescription>
                        <CardTitle class="text-3xl">{{
                            report.summary.total
                        }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card class="border-red-200 bg-red-50">
                    <CardHeader>
                        <CardDescription class="text-red-700">{{
                            t('health.summary.errors', 'Fouten')
                        }}</CardDescription>
                        <CardTitle class="text-3xl text-red-700">{{
                            report.summary.error
                        }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card class="border-orange-200 bg-orange-50">
                    <CardHeader>
                        <CardDescription class="text-orange-700">{{
                            t('health.summary.warnings', 'Waarschuwingen')
                        }}</CardDescription>
                        <CardTitle class="text-3xl text-orange-700">{{
                            report.summary.warning
                        }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card class="border-blue-200 bg-blue-50">
                    <CardHeader>
                        <CardDescription class="text-blue-700">{{
                            t('health.summary.info', 'Info')
                        }}</CardDescription>
                        <CardTitle class="text-3xl text-blue-700">{{
                            report.summary.info
                        }}</CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{
                        t('health.issues_title', 'Aandachtspunten')
                    }}</CardTitle>
                    <CardDescription>
                        {{
                            t(
                                'health.issues_description',
                                'Filter op ernst of module en open direct het gekoppelde record.',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div
                        v-if="publicAccountIssueCount > 0"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800"
                    >
                        <span>
                            {{
                                t(
                                    'health.public_account.repair_help',
                                    'Public Account setup is incomplete. Run repair to restore required templates, pages and blocks.',
                                )
                            }}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            class="border-orange-300 bg-white shadow-none hover:bg-orange-100"
                            @click="repairPublicAccount"
                        >
                            {{
                                t(
                                    'health.public_account.repair_button',
                                    'Repair Public Account',
                                )
                            }}
                        </Button>
                    </div>

                    <div class="flex flex-wrap gap-4 border-b border-slate-200">
                        <button
                            v-for="tab in categoryTabs"
                            :key="tab.key"
                            type="button"
                            class="-mb-px border-b-2 px-1 py-2 text-sm font-medium transition"
                            :class="
                                activeCategory === tab.key
                                    ? 'border-blue-600 text-blue-700'
                                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'
                            "
                            @click="activeCategory = tab.key"
                        >
                            {{ tab.label }}
                            <span class="ml-1 text-xs opacity-80"
                                >({{ tab.count }})</span
                            >
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="severity-filter">{{
                                t('health.filters.severity', 'Ernst')
                            }}</Label>
                            <select
                                id="severity-filter"
                                v-model="severityFilter"
                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm"
                            >
                                <option value="all">
                                    {{ t('common.all', 'Alle') }}
                                </option>
                                <option value="error">
                                    {{ t('health.severity.error', 'Fout') }}
                                </option>
                                <option value="warning">
                                    {{
                                        t(
                                            'health.severity.warning',
                                            'Waarschuwing',
                                        )
                                    }}
                                </option>
                                <option value="info">
                                    {{ t('health.severity.info', 'Info') }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="module-filter">{{
                                t('health.filters.module', 'Module')
                            }}</Label>
                            <select
                                id="module-filter"
                                v-model="moduleFilter"
                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm"
                            >
                                <option value="all">
                                    {{ t('common.all', 'Alle') }}
                                </option>
                                <option
                                    v-for="module in modules"
                                    :key="module"
                                    :value="module"
                                >
                                    {{ moduleLabel(module) }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="search-filter">{{
                                t('health.filters.search', 'Zoeken')
                            }}</Label>
                            <Input id="search-filter" v-model="search" />
                        </div>
                    </div>

                    <div
                        v-if="filteredIssues.length === 0"
                        class="rounded-md border border-dashed border-slate-300 p-6 text-sm text-slate-500"
                    >
                        {{
                            t('health.empty', 'Geen aandachtspunten gevonden.')
                        }}
                    </div>

                    <div
                        v-else
                        class="overflow-hidden rounded-lg border border-slate-200"
                    >
                        <div
                            class="grid grid-cols-[120px_140px_minmax(0,1fr)_120px] gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            <span>{{
                                t('health.columns.severity', 'Ernst')
                            }}</span>
                            <span>{{
                                t('health.columns.module', 'Module')
                            }}</span>
                            <span>{{
                                t('health.columns.issue', 'Aandachtspunt')
                            }}</span>
                            <span>{{
                                t('health.columns.action', 'Actie')
                            }}</span>
                        </div>
                        <div
                            v-for="issue in filteredIssues"
                            :key="issueKey(issue)"
                            class="grid grid-cols-[120px_140px_minmax(0,1fr)_120px] gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-b-0"
                        >
                            <span :class="severityClass(issue.severity)">{{
                                severityLabel(issue.severity)
                            }}</span>
                            <span class="text-slate-600">{{
                                moduleLabel(issue.module)
                            }}</span>
                            <span class="min-w-0">
                                <span
                                    class="block font-medium text-slate-900"
                                    >{{ issue.title }}</span
                                >
                                <span class="block text-slate-600">{{
                                    issue.message
                                }}</span>
                            </span>
                            <Button as-child variant="outline" size="sm">
                                <Link :href="issue.action_url">{{
                                    t('health.open_record', 'Open')
                                }}</Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>

<script setup>
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
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
});

const { t } = useAdminTranslations('cms_admin_ui');
const activeCategory = ref('overview');
const severityFilter = ref('all');
const moduleFilter = ref('all');
const search = ref('');

const allIssues = computed(() => props.report.issues ?? []);
const publicAccountIssueCount = computed(() => categoryCount('public_account'));

const categoryTabs = computed(() => [
    {
        key: 'overview',
        label: t('health.tabs.overview', 'Overzicht'),
        count: allIssues.value.length,
    },
    {
        key: 'seo',
        label: t('health.tabs.seo', 'SEO'),
        count: categoryCount('seo'),
    },
    {
        key: 'technical',
        label: t('health.tabs.technical', 'Technisch'),
        count: categoryCount('technical'),
    },
    {
        key: 'translations',
        label: t('health.tabs.translations', 'Vertalingen'),
        count: categoryCount('translations'),
    },
    {
        key: 'forms',
        label: t('health.tabs.forms', 'Formulieren'),
        count: categoryCount('forms'),
    },
    {
        key: 'media',
        label: t('health.tabs.media', 'Media'),
        count: categoryCount('media'),
    },
    {
        key: 'public_account',
        label: t('health.tabs.public_account', 'Public Account'),
        count: publicAccountIssueCount.value,
    },
]);

const modules = computed(() =>
    [...new Set(categoryIssues.value.map((issue) => issue.module))].sort(),
);

const categoryIssues = computed(() => {
    if (activeCategory.value === 'overview') {
        return allIssues.value;
    }

    return allIssues.value.filter(
        (issue) => issue.category === activeCategory.value,
    );
});

const filteredIssues = computed(() => {
    const term = search.value.trim().toLowerCase();

    return categoryIssues.value.filter((issue) => {
        if (
            severityFilter.value !== 'all' &&
            issue.severity !== severityFilter.value
        ) {
            return false;
        }

        if (
            moduleFilter.value !== 'all' &&
            issue.module !== moduleFilter.value
        ) {
            return false;
        }

        if (!term) {
            return true;
        }

        return `${issue.title} ${issue.message} ${issue.module}`
            .toLowerCase()
            .includes(term);
    });
});

watch(activeCategory, () => {
    moduleFilter.value = 'all';
});

function categoryCount(category) {
    return allIssues.value.filter((issue) => issue.category === category)
        .length;
}

function issueKey(issue) {
    return `${issue.severity}-${issue.module}-${issue.record_type}-${issue.record_id}-${issue.message}`;
}

function severityLabel(severity) {
    return (
        {
            error: t('health.severity.error', 'Fout'),
            warning: t('health.severity.warning', 'Waarschuwing'),
            info: t('health.severity.info', 'Info'),
        }[severity] ?? severity
    );
}

function severityClass(severity) {
    return (
        {
            error: 'inline-flex h-7 items-center justify-center rounded-full bg-red-100 px-2 text-xs font-medium text-red-700',
            warning:
                'inline-flex h-7 items-center justify-center rounded-full bg-orange-100 px-2 text-xs font-medium text-orange-700',
            info: 'inline-flex h-7 items-center justify-center rounded-full bg-blue-100 px-2 text-xs font-medium text-blue-700',
        }[severity] ??
        'inline-flex h-7 items-center justify-center rounded-full bg-slate-100 px-2 text-xs font-medium text-slate-700'
    );
}

function moduleLabel(module) {
    return (
        {
            pages: t('pages.title', "Pagina's"),
            posts: t('posts.title', 'Berichten'),
            menus: t('menus.title', "Menu's"),
            forms: t('forms.title', 'Formulieren'),
            categories: t('categories.title', 'Categorieen'),
            tags: t('tags.title', 'Tags'),
            public_account: t('public_account.index_title', 'Website accounts'),
        }[module] ?? module
    );
}

function repairPublicAccount() {
    const repairUrl = props.report.repairs?.public_account;

    if (!repairUrl) {
        return;
    }

    router.post(repairUrl, {}, { preserveScroll: true });
}
</script>
