<template>
    <Head :title="t('languages.page_title', 'CMS languages')" />

    <AdminLayout :suppress-flash="true">
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
                            <span class="mdi mdi-translate text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('languages.title', 'Languages') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'languages.description',
                                        'Manage active languages for public CMS pages and translations.',
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

                        <Button
                            as-child
                            variant="outline"
                            class="border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                        >
                            <Link
                                :href="route('admin.cms.languages.create')"
                                class="gap-2"
                            >
                                <span
                                    class="mdi mdi-plus-circle text-base text-blue-700"
                                    aria-hidden="true"
                                />
                                {{ commonT('actions.new', 'New') }}
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <div
                v-if="pageFlash.message"
                class="border-b border-slate-200 px-4 py-3 sm:px-5"
            >
                <RwFlashMessage
                    :type="pageFlash.type"
                    :message="pageFlash.message"
                />
            </div>

            <CardContent class="p-0">
                <div class="border-b border-slate-200">
                    <div class="flex flex-wrap gap-4 px-4 sm:px-5">
                        <button
                            v-for="tab in tabs"
                            :key="tab.value"
                            type="button"
                            :class="[
                                '-mb-px border-b-2 px-1 py-2 text-sm font-medium transition',
                                activeTab === tab.value
                                    ? 'border-blue-600 text-blue-700'
                                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900',
                            ]"
                            @click="activeTab = tab.value"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <RwTable
                    v-if="activeTab === 'languages'"
                    table-id="admin-cms-languages-table"
                    :data="tableData"
                    :columns="columns"
                    :initial-height="'calc(100vh - 300px)'"
                    :rows-per-page="25"
                    sort-field="id"
                    sort-order="desc"
                    :row-options="[25, 50, 100, 250]"
                    :cell-class="cellClass"
                    excel="true"
                    @on-cell-click="onCellClick"
                />

                <section v-else class="grid gap-4 px-4 py-4 sm:px-5">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3 rounded border border-slate-200 bg-slate-50 p-3"
                    >
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t('languages.order.title', 'Language order')
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{
                                    t(
                                        'languages.order.subtitle',
                                        'Choose the order in which languages appear in language selectors, translation flows and public language navigation.',
                                    )
                                }}
                            </p>
                            <p class="mt-2 text-xs text-slate-500">
                                {{
                                    t(
                                        'languages.order.help',
                                        'Drag languages into the desired order. The first active language is also used as fallback when the configured default language is unavailable.',
                                    )
                                }}
                            </p>
                            <p
                                v-if="orderForm.errors.languages"
                                class="mt-2 text-sm font-medium text-red-600"
                            >
                                {{ orderForm.errors.languages }}
                            </p>
                        </div>

                        <div
                            class="flex items-center gap-2 rounded border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600"
                        >
                            <span
                                v-if="orderForm.processing"
                                class="mdi mdi-loading animate-spin text-base text-blue-700"
                                aria-hidden="true"
                            />
                            <span
                                v-else-if="orderForm.recentlySuccessful"
                                class="mdi mdi-check-circle text-base text-green-700"
                                aria-hidden="true"
                            />
                            <span
                                v-else
                                class="mdi mdi-content-save-sync text-base text-slate-500"
                                aria-hidden="true"
                            />
                            <span v-if="orderForm.processing">
                                {{
                                    t(
                                        'languages.order.saving',
                                        'Saving order...',
                                    )
                                }}
                            </span>
                            <span v-else-if="orderForm.recentlySuccessful">
                                {{ t('languages.order.saved', 'Order saved.') }}
                            </span>
                            <span v-else>
                                {{
                                    t(
                                        'languages.order.autosave',
                                        'Order is saved automatically after moving a language.',
                                    )
                                }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <article
                            v-for="(language, index) in orderRows"
                            :key="language.id"
                            draggable="true"
                            class="flex cursor-move flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-none transition"
                            :class="{
                                'border-blue-300 bg-blue-50':
                                    dragOverLanguageId === language.id,
                            }"
                            @dragstart="onLanguageDragStart(language, $event)"
                            @dragover.prevent="
                                onLanguageDragOver(language, $event)
                            "
                            @dragleave="onLanguageDragLeave(language)"
                            @drop.prevent="onLanguageDrop(language)"
                            @dragend="onLanguageDragEnd"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="mdi mdi-drag text-xl text-slate-400"
                                    aria-hidden="true"
                                />
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700"
                                >
                                    {{ index + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="font-semibold text-slate-950"
                                        >
                                            {{ language.native_name }}
                                        </span>
                                        <span
                                            class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold uppercase text-slate-700"
                                        >
                                            {{ language.locale }}
                                        </span>
                                        <span
                                            class="rounded px-2 py-0.5 text-xs font-semibold"
                                            :class="
                                                language.is_active
                                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                                                    : 'bg-red-50 text-red-700 ring-1 ring-red-200'
                                            "
                                        >
                                            {{ language.order_active_label }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ language.name }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="h-8 w-8 shadow-none"
                                    :disabled="index === 0"
                                    :aria-label="
                                        t(
                                            'languages.order.move_up',
                                            'Move language up',
                                        )
                                    "
                                    :title="
                                        t(
                                            'languages.order.move_up',
                                            'Move language up',
                                        )
                                    "
                                    @click="moveLanguage(index, -1)"
                                >
                                    <span
                                        class="mdi mdi-arrow-up text-base"
                                        aria-hidden="true"
                                    />
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="h-8 w-8 shadow-none"
                                    :disabled="index === orderRows.length - 1"
                                    :aria-label="
                                        t(
                                            'languages.order.move_down',
                                            'Move language down',
                                        )
                                    "
                                    :title="
                                        t(
                                            'languages.order.move_down',
                                            'Move language down',
                                        )
                                    "
                                    @click="moveLanguage(index, 1)"
                                >
                                    <span
                                        class="mdi mdi-arrow-down text-base"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </div>
                        </article>
                    </div>
                </section>
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
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
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    languages: { type: Array, required: true },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const locale = computed(() => page.props?.app?.locale || 'nl-BE');
const activeTab = ref('languages');
const orderRows = ref([]);
const draggedLanguageId = ref(null);
const dragOverLanguageId = ref(null);
const orderSavePending = ref(false);
const orderForm = useForm({
    languages: [],
});
let orderSaveTimerId = null;

const tabs = computed(() => [
    { value: 'languages', label: t('languages.tabs.languages', 'Languages') },
    { value: 'order', label: t('languages.tabs.order', 'Order') },
]);

const pageFlash = computed(() => {
    const flash = page.props?.flash || {};

    if (flash.error) {
        return { type: 'danger', message: flash.error };
    }

    if (flash.warning) {
        return { type: 'warning', message: flash.warning };
    }

    if (flash.status) {
        return { type: 'success', message: flash.status };
    }

    return { type: '', message: '' };
});

const tableRows = computed(() =>
    props.languages.map((language) => ({
        ...language,
        active_label: language.is_active
            ? t('common.yes', 'Yes')
            : t('common.no', 'No'),
        order_active_label: language.is_active
            ? t('languages.order.active', 'Active')
            : t('languages.order.inactive', 'Inactive'),
        active_color: language.is_active ? 'green' : 'red',
        direction_label: language.direction === 'rtl' ? 'RTL' : 'LTR',
        flag_label: language.flag?.url
            ? language.flag?.alt_text || language.locale
            : '-',
        updated_at_display: formatDateTime(language.updated_at),
    })),
);

const tableData = computed(() => ({
    data: tableRows.value,
    total: tableRows.value.length,
}));

const columns = computed(() => [
    {
        key: 'id',
        label: t('common.columns.id', 'ID'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        clickable: true,
        width: 90,
    },
    {
        key: 'locale',
        label: t('common.columns.code', 'Code'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 100,
    },
    {
        key: 'flag_label',
        label: t('languages.columns.flag', 'Flag'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 110,
    },
    {
        key: 'name',
        label: t('languages.columns.name', 'Name'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'native_name',
        label: t('languages.columns.native_name', 'Native name'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'direction_label',
        label: t('languages.columns.direction', 'Direction'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 110,
    },
    {
        key: 'active_label',
        label: t('common.columns.active', 'Active'),
        type: 'chip',
        colorKey: 'active_color',
        selected: true,
        sortable: true,
        filterable: true,
        width: 110,
    },
    {
        key: 'updated_at_display',
        label: t('common.columns.updated_at', 'Updated'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
]);

watch(
    tableRows,
    (rows) => {
        orderRows.value = rows.map((language) => ({ ...language }));
    },
    { immediate: true },
);

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return new Intl.DateTimeFormat(locale.value, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function onCellClick(field, id) {
    if (field !== 'id') {
        return;
    }

    router.visit(route('admin.cms.languages.edit', { id }));
}

function cellClass({ col }) {
    return col.clickable ? 'cursor-pointer' : null;
}

function saveLanguageOrder() {
    if (orderForm.processing) {
        orderSavePending.value = true;
        return;
    }

    orderForm.clearErrors('languages');
    orderSavePending.value = false;

    orderForm
        .transform(() => ({
            languages: orderRows.value.map((language) => language.id),
        }))
        .post(route('admin.cms.languages.reorder'), {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                if (orderSavePending.value) {
                    scheduleLanguageOrderSave();
                }
            },
        });
}

function scheduleLanguageOrderSave() {
    clearOrderSaveTimer();

    orderSaveTimerId = window.setTimeout(() => {
        orderSaveTimerId = null;
        saveLanguageOrder();
    }, 250);
}

function clearOrderSaveTimer() {
    if (orderSaveTimerId) {
        window.clearTimeout(orderSaveTimerId);
        orderSaveTimerId = null;
    }
}

function onLanguageDragStart(language, event) {
    draggedLanguageId.value = language.id;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.dropEffect = 'move';
        event.dataTransfer.setData('text/plain', String(language.id));
    }
}

function onLanguageDragOver(language, event) {
    if (draggedLanguageId.value === language.id) {
        return;
    }

    dragOverLanguageId.value = language.id;

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onLanguageDragLeave(language) {
    if (dragOverLanguageId.value === language.id) {
        dragOverLanguageId.value = null;
    }
}

function onLanguageDrop(targetLanguage) {
    if (
        !draggedLanguageId.value ||
        draggedLanguageId.value === targetLanguage.id
    ) {
        onLanguageDragEnd();
        return;
    }

    const fromIndex = orderRows.value.findIndex(
        (language) => language.id === draggedLanguageId.value,
    );
    const toIndex = orderRows.value.findIndex(
        (language) => language.id === targetLanguage.id,
    );

    if (fromIndex === -1 || toIndex === -1) {
        onLanguageDragEnd();
        return;
    }

    const nextRows = [...orderRows.value];
    const [movedLanguage] = nextRows.splice(fromIndex, 1);
    nextRows.splice(toIndex, 0, movedLanguage);
    orderRows.value = nextRows;
    onLanguageDragEnd();
    scheduleLanguageOrderSave();
}

function onLanguageDragEnd() {
    draggedLanguageId.value = null;
    dragOverLanguageId.value = null;
}

function moveLanguage(index, direction) {
    const targetIndex = index + direction;

    if (targetIndex < 0 || targetIndex >= orderRows.value.length) {
        return;
    }

    const nextRows = [...orderRows.value];
    const [movedLanguage] = nextRows.splice(index, 1);
    nextRows.splice(targetIndex, 0, movedLanguage);
    orderRows.value = nextRows;
    scheduleLanguageOrderSave();
}

onBeforeUnmount(() => {
    clearOrderSaveTimer();
});
</script>
