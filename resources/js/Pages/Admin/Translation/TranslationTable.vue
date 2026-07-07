<template>
    <Head :title="t('meta.page_title', 'Translations')" />

    <component
        :is="layoutComponent"
        :title="t('page.title', 'Translations')"
        :suppress-flash="true"
    >
        <Card class="rounded-none shadow-none">
            <CardHeader class="gap-0 border-b border-slate-200 p-0">
                <div
                    class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-5"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                        >
                            <span class="mdi mdi-translate text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('page.title', 'Translations') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'page.subtitle',
                                        'Manage dynamic prompts and RWTable translations centrally with quick missing filters.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            class="text-slate-950 shadow-none hover:bg-slate-50 hover:text-slate-950"
                            :aria-label="t('actions.back', 'Back')"
                            :title="t('actions.back', 'Back')"
                            @click="goBack"
                        >
                            <span class="mdi mdi-arrow-left-circle text-lg" />
                        </Button>

                        <Button
                            v-if="activeTab !== 'languages'"
                            variant="outline"
                            size="icon"
                            class="border-slate-200 text-slate-700 shadow-none hover:bg-slate-50 hover:text-slate-900"
                            :aria-label="t('actions.reload', 'Reload')"
                            :title="t('actions.reload', 'Reload')"
                            :disabled="loadingRows || syncing || addingLocale"
                            @click="reloadRows"
                        >
                            <span
                                class="mdi text-base"
                                :class="
                                    loadingRows
                                        ? 'mdi-loading animate-spin'
                                        : 'mdi-refresh'
                                "
                            />
                        </Button>

                        <Button
                            v-if="activeTab !== 'content' && activeTab !== 'languages'"
                            variant="outline"
                            class="gap-2 border-emerald-200 text-emerald-700 shadow-none hover:bg-emerald-50 hover:text-emerald-800"
                            :title="syncButtonTooltip"
                            :disabled="syncing || addingLocale || aiFilling"
                            @click="openSyncWarningDialog"
                        >
                            <span
                                class="mdi text-base"
                                :class="
                                    syncing
                                        ? 'mdi-loading animate-spin'
                                        : 'mdi-sync'
                                "
                            />
                            {{ syncButtonLabel }}
                        </Button>

                        <Button
                            v-if="activeTab !== 'content' && activeTab !== 'languages'"
                            variant="outline"
                            class="gap-2 border-purple-200 text-purple-700 shadow-none hover:bg-purple-50 hover:text-purple-800"
                            :disabled="
                                loadingRows || syncing || addingLocale || aiFilling
                            "
                            @click="openAiFillDialog"
                        >
                            <span
                                class="mdi text-base"
                                :class="
                                    aiFilling
                                        ? 'mdi-loading animate-spin'
                                        : 'mdi-robot-outline'
                                "
                            />
                            {{ t('actions.ai_fill', 'Fill with AI') }}
                        </Button>

                        <Button
                            v-if="activeTab === 'content'"
                            variant="outline"
                            class="gap-2 border-purple-200 text-purple-700 shadow-none hover:bg-purple-50 hover:text-purple-800"
                            :disabled="
                                loadingRows ||
                                addingLocale ||
                                contentBulkAiRunning ||
                                creatingContentTranslation
                            "
                            @click="openContentBulkAiDialog"
                        >
                            <span
                                class="mdi text-base"
                                :class="
                                    contentBulkAiRunning
                                        ? 'mdi-loading animate-spin'
                                        : 'mdi-robot-outline'
                                "
                            />
                            {{ t('actions.content_bulk_ai', 'Bulk AI content') }}
                        </Button>

                        <Button
                            v-if="isPlatformMode && activeTab === 'admin'"
                            variant="outline"
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                            :disabled="
                                loadingRows || syncing || addingLocale || aiFilling
                            "
                            @click="openAddLocaleDialog"
                        >
                            <span class="mdi mdi-plus-circle text-base text-blue-700" />
                            {{ t('actions.add_locale', 'Add language') }}
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <div v-if="feedback.message" class="px-4 pt-4 sm:px-5">
                <RwFlashMessage
                    :type="feedback.type"
                    :message="feedback.message"
                />
            </div>

            <CardContent class="p-0">
                <div
                    v-if="availableTabs.length > 1"
                    class="border-b border-slate-200"
                >
                    <div
                        class="flex flex-wrap gap-4 px-4 sm:px-5"
                    >
                        <button
                            v-if="availableTabs.includes('public')"
                            type="button"
                            class="-mb-px border-b-2 px-1 py-2 text-sm font-medium transition"
                            :class="
                                activeTab === 'public'
                                    ? 'border-blue-600 text-blue-700'
                                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'
                            "
                            @click="setActiveTab('public')"
                        >
                            {{ t('tabs.public_site', 'Openbare site') }}
                        </button>
                        <button
                            v-if="availableTabs.includes('admin')"
                            type="button"
                            class="-mb-px border-b-2 px-1 py-2 text-sm font-medium transition"
                            :class="
                                activeTab === 'admin'
                                    ? 'border-blue-600 text-blue-700'
                                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'
                            "
                            @click="setActiveTab('admin')"
                        >
                            {{ t('tabs.admin', 'Admin') }}
                        </button>
                        <button
                            v-if="availableTabs.includes('content')"
                            type="button"
                            class="-mb-px border-b-2 px-1 py-2 text-sm font-medium transition"
                            :class="
                                activeTab === 'content'
                                    ? 'border-blue-600 text-blue-700'
                                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'
                            "
                            @click="setActiveTab('content')"
                        >
                            {{ t('tabs.content', 'Content') }}
                        </button>
                    </div>
                </div>

                <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                    <div
                        class="grid w-full gap-2 rounded border border-slate-200 bg-slate-50 p-3"
                    >
                        <div class="grid w-full gap-1 sm:max-w-xs">
                            <label class="text-[11px] text-slate-600">{{
                                t('filters.row_filter', 'Row filter')
                            }}</label>
                            <RwAutoCompleteInput
                                v-model="missingFilterMode"
                                :items="missingFilterOptions"
                                item-title="title"
                                item-value="value"
                                size="compact"
                            />
                        </div>
                    </div>
                </div>

                <RwTable
                    :table-id="tableId"
                    :data="tableData"
                    :columns="columns"
                    :global-search="true"
                    :initial-height="'calc(100vh - 380px)'"
                    :rows-per-page="25"
                    sort-field="id"
                    sort-order="desc"
                    :row-options="[25, 50, 100, 250]"
                    :inline-update-route="inlineUpdateRoute"
                    :cell-class="cellClass"
                    excel="true"
                    @on-cell-click="handleCellClick"
                />
            </CardContent>
        </Card>

        <Dialog v-model:open="addLocaleDialogOpen">
            <RwDialogTemplate
                :title="t('dialog.title', 'Taal toevoegen')"
                :subtitle="
                    t(
                        'dialog.subtitle',
                        'Voeg een nieuwe locale toe en kopieer ontbrekende basisvertalingen. Registratie in config/app.php gebeurt automatisch.',
                    )
                "
                max-width-class="sm:max-w-lg"
            >
                <template #back>
                    <RwActionButton
                        :label="t('actions.back', 'Terug')"
                        icon="mdi mdi-arrow-left-circle"
                        tone="back"
                        @click="closeAddLocaleDialog"
                    />
                </template>

                <template #actions>
                    <RwActionButton
                        :label="t('actions.save', 'Bewaren')"
                        icon="mdi mdi-content-save"
                        tone="save"
                        :loading="addingLocale"
                        :disabled="addingLocale || newLocale.trim() === ''"
                        @click="addLocale"
                    />
                </template>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1">
                        <label class="text-[11px] text-slate-600">{{
                            t('dialog.locale_code', 'Locale code')
                        }}</label>
                        <input
                            v-model="newLocale"
                            type="text"
                            required
                            class="h-8 rounded border border-slate-300 bg-sky-50 px-2 text-xs shadow-none"
                            :placeholder="
                                t(
                                    'dialog.locale_placeholder',
                                    'bv. nl, en, pt_BR',
                                )
                            "
                            @blur="normalizeNewLocaleInput"
                        />
                    </div>

                    <div class="grid gap-1">
                        <label class="text-[11px] text-slate-600">
                            {{
                                t(
                                    'dialog.copy_from_locale',
                                    'Kopieer van locale',
                                )
                            }}
                        </label>
                        <RwAutoCompleteInput
                            v-model="newLocaleSource"
                            :items="localeOptions"
                            item-title="title"
                            item-value="value"
                            size="compact"
                        />
                    </div>
                </div>
            </RwDialogTemplate>
        </Dialog>

        <Dialog v-model:open="syncWarningDialogOpen">
            <DialogContent
                :disable-outside-pointer-events="false"
                class="flex max-h-[calc(100vh-1.5rem)] flex-col gap-0 overflow-hidden p-0 shadow-none sm:max-w-lg [&>button.absolute]:hidden"
            >
                <div class="relative px-4 py-4 pr-12 sm:px-5 sm:pr-12">
                    <DialogTitle class="text-lg font-semibold text-slate-900">
                        {{ syncWarningTitle }}
                    </DialogTitle>
                    <DialogDescription class="mt-1 text-sm text-slate-400">
                        {{ syncWarningSubtitle }}
                    </DialogDescription>

                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="absolute right-3 top-3 text-slate-500 shadow-none hover:bg-slate-100 hover:text-slate-900"
                        :aria-label="t('actions.close', 'Close')"
                        :title="t('actions.close', 'Close')"
                        :disabled="syncing"
                        @click="closeSyncWarningDialog"
                    >
                        <span class="mdi mdi-close text-lg" />
                    </Button>
                </div>

                <div class="border-t border-slate-200" />

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-5">
                    <p class="text-sm text-slate-700">
                        {{ syncWarningMessage }}
                    </p>
                </div>

                <div
                    class="flex items-center justify-end gap-2 border-t border-slate-200 px-4 py-3 sm:px-5"
                >
                    <Button
                        variant="outline"
                        class="gap-2 border-amber-200 text-amber-700 shadow-none hover:bg-amber-50 hover:text-amber-800"
                        :disabled="syncing"
                        @click="confirmSyncMissing"
                    >
                        <span
                            class="mdi text-base"
                            :class="
                                syncing
                                    ? 'mdi-loading animate-spin'
                                    : 'mdi-alert-circle'
                            "
                        />
                        {{ t('actions.continue', 'Continue') }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="aiFillDialogOpen">
            <DialogContent
                :disable-outside-pointer-events="false"
                class="flex max-h-[calc(100vh-1.5rem)] flex-col gap-0 overflow-hidden p-0 shadow-none sm:max-w-lg [&>button.absolute]:hidden"
            >
                <div class="relative px-4 py-4 pr-12 sm:px-5 sm:pr-12">
                    <DialogTitle class="text-lg font-semibold text-slate-900">
                        {{ t('dialog.ai_fill_title', 'Fill translations with AI') }}
                    </DialogTitle>
                    <DialogDescription class="mt-1 text-sm text-slate-400">
                        {{
                            t(
                                'dialog.ai_fill_subtitle',
                                'Automatically fill missing translations from the source locale.',
                            )
                        }}
                    </DialogDescription>

                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="absolute right-3 top-3 text-slate-500 shadow-none hover:bg-slate-100 hover:text-slate-900"
                        :aria-label="t('actions.close', 'Close')"
                        :title="t('actions.close', 'Close')"
                        :disabled="aiFilling"
                        @click="closeAiFillDialog"
                    >
                        <span class="mdi mdi-close text-lg" />
                    </Button>
                </div>

                <div class="border-t border-slate-200" />

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1">
                            <label class="text-[11px] text-slate-600">
                                {{
                                    t('dialog.ai_fill_target_locale', 'Target locale')
                                }}
                            </label>
                            <RwAutoCompleteInput
                                v-model="aiFillTargetLocale"
                                :items="aiFillLocaleOptions"
                                item-title="title"
                                item-value="value"
                                size="compact"
                            />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-[11px] text-slate-600">
                                {{ t('dialog.ai_fill_limit', 'Maximum rows') }}
                            </label>
                            <input
                                v-model.number="aiFillLimit"
                                type="number"
                                min="1"
                                :max="aiFillLimitMax"
                                class="h-8 rounded border border-slate-300 bg-sky-50 px-2 text-xs shadow-none"
                            />
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-slate-600">
                        {{
                            t(
                                'dialog.ai_fill_note',
                                'Source locale: :locale. Only empty fields in the target locale are filled.',
                                { locale: sourceLocaleDisplay },
                            )
                        }}
                    </p>
                </div>

                <div
                    class="flex items-center justify-end gap-2 border-t border-slate-200 px-4 py-3 sm:px-5"
                >
                    <Button
                        variant="outline"
                        class="gap-2 border-purple-200 text-purple-700 shadow-none hover:bg-purple-50 hover:text-purple-800"
                        :disabled="
                            aiFilling ||
                            aiFillTargetLocale.trim() === '' ||
                            aiFillTargetLocale === activeDefaultSourceLocale
                        "
                        @click="runAiFill"
                    >
                        <span
                            class="mdi text-base"
                            :class="
                                aiFilling
                                    ? 'mdi-loading animate-spin'
                                    : 'mdi-robot-outline'
                            "
                        />
                        {{ t('actions.ai_fill_start', 'Run AI') }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="contentTranslationDialogOpen">
            <DialogContent
                :disable-outside-pointer-events="false"
                class="flex max-h-[calc(100vh-1.5rem)] flex-col gap-0 overflow-hidden p-0 shadow-none sm:max-w-lg [&>button.absolute]:hidden"
            >
                <div class="relative px-4 py-4 pr-12 sm:px-5 sm:pr-12">
                    <DialogTitle class="text-lg font-semibold text-slate-900">
                        {{ t('dialog.content_translation_title', 'Create content translation') }}
                    </DialogTitle>
                    <DialogDescription class="mt-1 text-sm text-slate-400">
                        {{
                            t(
                                'dialog.content_translation_subtitle',
                                'Create a missing content translation as a draft.',
                            )
                        }}
                    </DialogDescription>
                    <button
                        type="button"
                        class="absolute right-4 top-4 rounded-sm text-slate-400 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300 disabled:pointer-events-none disabled:opacity-50"
                        :aria-label="t('actions.close', 'Close')"
                        :title="t('actions.close', 'Close')"
                        :disabled="creatingContentTranslation"
                        @click="closeContentTranslationDialog"
                    >
                        <span class="mdi mdi-close text-lg" />
                    </button>
                </div>

                <div class="grid gap-3 px-4 py-4 text-sm text-slate-700 sm:px-5">
                    <p>
                        {{
                            t(
                                'dialog.content_translation_message',
                                'Choose how to create the missing translation for :label in :locale.',
                                {
                                    label:
                                        selectedContentTranslationRequest?.label ||
                                        '',
                                    locale: String(
                                        selectedContentTranslationRequest?.locale ||
                                            '',
                                    ).toUpperCase(),
                                },
                            )
                        }}
                    </p>
                    <p class="rounded border border-blue-200 bg-blue-50 p-3 text-xs text-blue-800">
                        {{
                            t(
                                'dialog.content_translation_copy_note',
                                'Copy source as draft creates a draft in the target locale by copying the source content without AI translation.',
                            )
                        }}
                    </p>
                    <p class="rounded border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                        {{
                            t(
                                'dialog.content_translation_ai_note',
                                'AI translations are always created as drafts and must be reviewed and published manually.',
                            )
                        }}
                    </p>
                </div>

                <div
                    class="flex flex-wrap justify-end gap-2 border-t border-slate-200 px-4 py-3 sm:px-5"
                >
                    <Button
                        variant="outline"
                        class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                        :disabled="creatingContentTranslation"
                        @click="createSelectedContentTranslation(false)"
                    >
                        <span
                            class="mdi text-base"
                            :class="
                                creatingContentTranslation
                                    ? 'mdi-loading animate-spin'
                                    : 'mdi-content-copy'
                            "
                        />
                        {{ t('actions.create_draft_copy', 'Copy source as draft') }}
                    </Button>
                    <Button
                        variant="outline"
                        class="gap-2 border-purple-200 text-purple-700 shadow-none hover:bg-purple-50 hover:text-purple-800"
                        :disabled="creatingContentTranslation"
                        @click="createSelectedContentTranslation(true)"
                    >
                        <span
                            class="mdi text-base"
                            :class="
                                creatingContentTranslation
                                    ? 'mdi-loading animate-spin'
                                    : 'mdi-robot-outline'
                            "
                        />
                        {{ t('actions.create_ai_draft', 'Create AI draft') }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="contentBulkAiDialogOpen">
            <DialogContent
                :disable-outside-pointer-events="false"
                class="flex max-h-[calc(100vh-1.5rem)] flex-col gap-0 overflow-hidden p-0 shadow-none sm:max-w-lg [&>button.absolute]:hidden"
            >
                <div class="relative px-4 py-4 pr-12 sm:px-5 sm:pr-12">
                    <DialogTitle class="text-lg font-semibold text-slate-900">
                        {{ t('dialog.content_bulk_ai_title', 'Bulk AI content') }}
                    </DialogTitle>
                    <DialogDescription class="mt-1 text-sm text-slate-400">
                        {{
                            t(
                                'dialog.content_bulk_ai_subtitle',
                                'Maak ontbrekende contentvertalingen in batch aan als concept.',
                            )
                        }}
                    </DialogDescription>

                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="absolute right-3 top-3 text-slate-500 shadow-none hover:bg-slate-100 hover:text-slate-900"
                        :aria-label="t('actions.close', 'Close')"
                        :title="t('actions.close', 'Close')"
                        :disabled="contentBulkAiRunning"
                        @click="closeContentBulkAiDialog"
                    >
                        <span class="mdi mdi-close text-lg" />
                    </Button>
                </div>

                <div class="border-t border-slate-200" />

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1">
                            <label class="text-[11px] text-slate-600">
                                {{ t('dialog.ai_fill_target_locale', 'Doellocale') }}
                            </label>
                            <RwAutoCompleteInput
                                v-model="contentBulkAiTargetLocale"
                                :items="contentBulkAiLocaleOptions"
                                item-title="title"
                                item-value="value"
                                size="compact"
                            />
                        </div>

                        <div class="grid gap-1">
                            <label class="text-[11px] text-slate-600">
                                {{ t('dialog.ai_fill_limit', 'Maximum rijen') }}
                            </label>
                            <input
                                v-model.number="contentBulkAiLimit"
                                type="number"
                                min="1"
                                max="50"
                                class="h-8 rounded border border-slate-300 bg-sky-50 px-2 text-xs shadow-none"
                            />
                        </div>
                    </div>

                    <div class="mt-3 grid gap-2 text-xs text-slate-600">
                        <p>
                            {{
                                t(
                                    'dialog.content_bulk_ai_count',
                                    ':count ontbrekende vertaling(en) klaar voor AI-aanmaak.',
                                    { count: contentBulkAiItems.length },
                                )
                            }}
                        </p>
                        <p class="rounded border border-amber-200 bg-amber-50 p-3 text-amber-800">
                            {{
                                t(
                                    'dialog.content_translation_ai_note',
                                    'AI-vertalingen worden altijd als concept aangemaakt en moeten manueel nagekeken en gepubliceerd worden.',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div
                    class="flex items-center justify-end gap-2 border-t border-slate-200 px-4 py-3 sm:px-5"
                >
                    <Button
                        variant="outline"
                        class="gap-2 border-purple-200 text-purple-700 shadow-none hover:bg-purple-50 hover:text-purple-800"
                        :disabled="
                            contentBulkAiRunning ||
                            contentBulkAiTargetLocale.trim() === '' ||
                            contentBulkAiItems.length === 0
                        "
                        @click="runContentBulkAi"
                    >
                        <span
                            class="mdi text-base"
                            :class="
                                contentBulkAiRunning
                                    ? 'mdi-loading animate-spin'
                                    : 'mdi-robot-outline'
                            "
                        />
                        {{ t('actions.ai_fill_start', 'AI uitvoeren') }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </component>
</template>

<script setup>
import RwActionButton from '@/Components/RwActionButton.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import RwDialogTemplate from '@/Components/RwDialogTemplate.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwTable from '@/Components/RwTable.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, markRaw, ref, shallowRef, watch } from 'vue';

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    locales: {
        type: Array,
        default: () => [],
    },
    public_rows: {
        type: Array,
        default: () => [],
    },
    public_locales: {
        type: Array,
        default: () => [],
    },
    content_rows: {
        type: Array,
        default: () => [],
    },
    content_locales: {
        type: Array,
        default: () => [],
    },
    editable_content_locales: {
        type: Array,
        default: () => [],
    },
    default_source_locale: {
        type: String,
        default: 'nl',
    },
    ai_defaults: {
        type: Object,
        default: () => ({
            fill_limit_default: 100,
            fill_limit_max: 500,
        }),
    },
    mode: {
        type: String,
        default: 'site',
    },
});

const rowsState = ref(Array.isArray(props.rows) ? [...props.rows] : []);
const publicRowsState = ref(
    Array.isArray(props.public_rows) ? [...props.public_rows] : [],
);
const contentRowsState = ref(
    Array.isArray(props.content_rows) ? [...props.content_rows] : [],
);
const localesState = ref(
    Array.isArray(props.locales) ? [...props.locales] : [],
);
const publicLocalesState = ref(
    Array.isArray(props.public_locales) ? [...props.public_locales] : [],
);
const contentLocalesState = ref(
    Array.isArray(props.content_locales) ? [...props.content_locales] : [],
);
const isPlatformMode = computed(() => props.mode === 'platform');
const layoutComponent = computed(() =>
    isPlatformMode.value ? PlatformLayout : AdminLayout,
);
const availableTabs = computed(() =>
    isPlatformMode.value ? ['admin'] : ['public', 'content'],
);
const activeTab = ref(resolveInitialActiveTab());
const defaultSourceLocale = ref(
    localesState.value.includes(String(props.default_source_locale || 'nl'))
        ? String(props.default_source_locale || 'nl')
        : String(localesState.value[0] || 'nl'),
);
const translationFiltersStorageKey = 'admin-translations-table:filters';

function loadPersistedTranslationFilters() {
    if (typeof window === 'undefined') {
        return {};
    }

    try {
        const raw = window.localStorage.getItem(translationFiltersStorageKey);

        if (!raw) {
            return {};
        }

        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        return {};
    }
}

const persistedFilters = loadPersistedTranslationFilters();

function resolvePersistedMissingFilterMode() {
    const explicitMode = String(
        persistedFilters.missingFilterMode || '',
    ).trim();

    if (
        explicitMode === 'all' ||
        explicitMode === 'missing_any' ||
        explicitMode === 'ai_review' ||
        explicitMode.startsWith('locale:')
    ) {
        return explicitMode;
    }

    if (persistedFilters.missingOnly === false) {
        return 'all';
    }

    const legacyLocaleFilter = String(
        persistedFilters.missingLocaleFilter || '',
    ).trim();

    if (legacyLocaleFilter !== '') {
        return `locale:${legacyLocaleFilter}`;
    }

    return 'missing_any';
}

const missingFilterMode = ref(resolvePersistedMissingFilterMode());
const newLocale = ref('');
const newLocaleSource = ref(String(defaultSourceLocale.value || 'nl'));
const addLocaleDialogOpen = ref(false);
const syncWarningDialogOpen = ref(false);
const aiFillDialogOpen = ref(false);
const contentTranslationDialogOpen = ref(false);
const contentBulkAiDialogOpen = ref(false);
const loadingRows = ref(false);
const syncing = ref(false);
const addingLocale = ref(false);
const aiFilling = ref(false);
const creatingContentTranslation = ref(false);
const contentBulkAiRunning = ref(false);
const selectedContentTranslationRequest = ref(null);
const aiFillLimitMax = computed(() => {
    const value = Number(props.ai_defaults?.fill_limit_max ?? 500);

    if (!Number.isFinite(value) || value < 1) {
        return 500;
    }

    return Math.floor(value);
});
const aiFillLimit = ref(
    Math.min(
        Math.max(1, Number(props.ai_defaults?.fill_limit_default ?? 100)),
        aiFillLimitMax.value,
    ),
);
const aiFillTargetLocale = ref('');
const contentBulkAiTargetLocale = ref('');
const contentBulkAiLimit = ref(10);
const feedback = ref({ type: '', message: '' });
const inlineUpdateRoute = (id) =>
    route(routeName('update'), { row: id });

const tableId = computed(() =>
    activeTab.value === 'content'
        ? 'admin-content-translations-table'
        : activeTab.value === 'public'
          ? 'admin-public-translations-table'
          : 'admin-translations-table',
);

const activeRowsState = computed(() => {
    if (activeTab.value === 'content') {
        return contentRowsState.value;
    }

    return activeTab.value === 'public' ? publicRowsState.value : rowsState.value;
});

const activeLocalesState = computed(() =>
    activeTab.value === 'content'
        ? contentLocalesState.value
        : activeTab.value === 'public'
        ? publicLocalesState.value
        : localesState.value,
);

const activeDefaultSourceLocale = computed(() => {
    const locales = activeLocalesState.value;
    const configuredDefault = String(defaultSourceLocale.value || 'nl');

    return locales.includes(configuredDefault)
        ? configuredDefault
        : String(locales[0] || configuredDefault);
});

const page = usePage();

const uiMessages = computed(() => {
    const translations =
        page.props?.app?.translations?.translation_editor_ui ?? {};

    return translations && typeof translations === 'object' ? translations : {};
});

function getNestedTranslation(source, key) {
    if (!source || typeof source !== 'object') {
        return null;
    }

    return String(key || '')
        .split('.')
        .filter((segment) => segment !== '')
        .reduce((carry, segment) => {
            if (!carry || typeof carry !== 'object') {
                return null;
            }

            if (!Object.prototype.hasOwnProperty.call(carry, segment)) {
                return null;
            }

            return carry[segment];
        }, source);
}

function interpolateTranslation(template, replacements = {}) {
    return Object.entries(replacements).reduce(
        (carry, [token, replacement]) => {
            return carry.replaceAll(`:${token}`, String(replacement ?? ''));
        },
        String(template || ''),
    );
}

function t(key, fallback = '', replacements = {}) {
    const translated = getNestedTranslation(uiMessages.value, key);
    const resolved =
        typeof translated === 'string' && translated.trim() !== ''
            ? translated
            : fallback || key;

    return interpolateTranslation(resolved, replacements);
}

const localeOptions = computed(() => {
    return [
        {
            value: '',
            title: t('dialog.no_copy_option', 'Geen kopie (leeg starten)'),
        },
        ...localesState.value.map((locale) => ({
            value: String(locale),
            title: String(locale).toUpperCase(),
        })),
    ];
});

const missingFilterOptions = computed(() => {
    const options = [
        {
            value: 'missing_any',
            title: t('filters.empty_any', 'Empty in any language'),
        },
        {
            value: 'all',
            title: t('filters.all_rows', 'All rows'),
        },
    ];

    if (activeTab.value === 'content') {
        options.push({
            value: 'ai_review',
            title: t('filters.ai_review', 'Only AI drafts'),
        });
    }

    activeLocalesState.value.forEach((locale) => {
        const localeValue = String(locale);

        options.push({
            value: `locale:${localeValue}`,
            title: t('filters.empty_in_locale', 'Empty in :locale', {
                locale: localeValue.toUpperCase(),
            }),
        });
    });

    return options;
});

const sourceLocaleDisplay = computed(() => {
    return String(activeDefaultSourceLocale.value || 'nl').toUpperCase();
});

const syncWarningTitle = computed(() => {
    if (activeTab.value === 'public') {
        return t(
            'dialog.sync_warning_public_title',
            'Warning before syncing public text keys',
        );
    }

    return t(
        'dialog.sync_warning_admin_title',
        'Warning before filling missing admin translations',
    );
});

const syncWarningSubtitle = computed(() => {
    if (activeTab.value === 'public') {
        return t(
            'dialog.sync_warning_public_subtitle',
            'This scans the public text keys from code and creates missing records.',
        );
    }

    return t(
        'dialog.sync_warning_admin_subtitle',
        'This copies source-locale values into empty admin translation fields.',
    );
});

const syncWarningMessage = computed(() => {
    if (activeTab.value === 'public') {
        return t(
            'dialog.sync_warning_public_message',
            'This action creates missing public text records and translation rows. Only the source locale receives the default value; other locales remain empty for translation.',
        );
    }

    return t(
        'dialog.sync_warning_admin_message',
        'This action fills empty admin translation values with values from source locale :locale. New languages can therefore temporarily receive the same content as the source.',
        { locale: sourceLocaleDisplay.value },
    );
});

const syncButtonLabel = computed(() => {
    if (activeTab.value === 'public') {
        return t('actions.sync', 'Sync');
    }

    return t('actions.sync_missing', 'Sync missing');
});

const syncButtonTooltip = computed(() => {
    if (activeTab.value === 'public') {
        return t(
            'tooltips.sync_public',
            'Scan public text keys from code and create missing public text records and translation rows.',
        );
    }

    return t(
        'tooltips.sync_admin',
        'Fill empty admin translation values from the configured source locale.',
    );
});

const aiFillLocaleOptions = computed(() => {
    return activeLocalesState.value
        .filter(
            (locale) =>
                String(locale) !== String(activeDefaultSourceLocale.value),
        )
        .filter((locale) => activeTab.value !== 'public' || canEditContentLocale(locale))
        .map((locale) => ({
            value: String(locale),
            title: String(locale).toUpperCase(),
        }));
});

const contentBulkAiLocaleOptions = computed(() => {
    return contentLocalesState.value
        .filter(
            (locale) =>
                String(locale) !== String(activeDefaultSourceLocale.value),
        )
        .filter((locale) => canEditContentLocale(locale))
        .map((locale) => ({
            value: String(locale),
            title: String(locale).toUpperCase(),
        }));
});

const contentBulkAiItems = computed(() => {
    const targetLocale = String(contentBulkAiTargetLocale.value || '').trim();
    const limit = Math.min(
        50,
        Math.max(1, Number(contentBulkAiLimit.value || 10)),
    );

    if (targetLocale === '' || !canEditContentLocale(targetLocale)) {
        return [];
    }

    return filteredRows.value
        .filter((row) => {
            const missingLocales = Array.isArray(row?.missing_locales)
                ? row.missing_locales.map((locale) => String(locale || ''))
                : [];

            return (
                row?.source_id &&
                row?.type &&
                missingLocales.includes(targetLocale)
            );
        })
        .slice(0, limit)
        .map((row) => ({
            type: row.type,
            source_id: row.source_id,
        }));
});

watch(
    [aiFillLocaleOptions, activeDefaultSourceLocale],
    () => {
        const allowedLocales = aiFillLocaleOptions.value.map((item) =>
            String(item.value || ''),
        );

        if (allowedLocales.includes(String(aiFillTargetLocale.value || ''))) {
            return;
        }

        aiFillTargetLocale.value = allowedLocales[0] || '';
    },
    { immediate: true },
);

watch(
    [contentBulkAiLocaleOptions, activeDefaultSourceLocale],
    () => {
        const allowedLocales = contentBulkAiLocaleOptions.value.map((item) =>
            String(item.value || ''),
        );

        if (allowedLocales.includes(String(contentBulkAiTargetLocale.value || ''))) {
            return;
        }

        contentBulkAiTargetLocale.value = allowedLocales[0] || '';
    },
    { immediate: true },
);

watch(aiFillLimitMax, (maxValue) => {
    const normalizedLimit = Math.min(
        maxValue,
        Math.max(1, Number(aiFillLimit.value || 1)),
    );

    if (normalizedLimit !== Number(aiFillLimit.value || 0)) {
        aiFillLimit.value = normalizedLimit;
    }
});

const columns = computed(() => {
    if (activeTab.value === 'content') {
        return contentColumns.value;
    }

    const baseColumns = [
        {
            key: 'source_label',
            label: t('table.source', 'Bron'),
            type: 'chip',
            colorKey: 'source_color',
            sortable: true,
            filterable: false,
            selected: true,
            width: 130,
        },
        {
            key: 'key',
            label: t('table.key', 'Key'),
            type: 'text',
            sortable: true,
            filterable: false,
            selected: true,
            minWidth: 260,
        },
    ];

    const localeColumns = activeLocalesState.value.map((locale) => ({
        key: `value_${locale}`,
        label: locale.toUpperCase(),
        type: 'text',
        editable: activeTab.value !== 'public' || canEditContentLocale(locale),
        validationType: 'client',
        validationRules: 'nullable|string|max:20000',
        sortable: true,
        filterable: true,
        selected: true,
        minWidth: 180,
    }));

    return [
        ...baseColumns,
        ...localeColumns,
        {
            key: 'status_label',
            label: t('table.status', 'Status'),
            type: 'chip',
            colorKey: 'status_color',
            sortable: true,
            filterable: false,
            selected: true,
            width: 120,
        },
        {
            key: 'missing_locales_display',
            label: t('table.missing', 'Ontbreekt'),
            type: 'text',
            sortable: false,
            filterable: false,
            selected: true,
            minWidth: 160,
        },
    ];
});

const contentColumns = computed(() => {
    const localeColumns = activeLocalesState.value.map((locale) => ({
        key: `value_${locale}`,
        label: locale.toUpperCase(),
        type: 'text',
        clickable: canEditContentLocale(locale),
        sortable: true,
        filterable: true,
        selected: true,
        minWidth: 130,
    }));

    return [
        {
            key: 'type_label',
            label: t('table.content_type', 'Type'),
            type: 'chip',
            sortable: true,
            filterable: false,
            selected: true,
            width: 130,
        },
        {
            key: 'source_label',
            label: t('table.content_source', 'Bron'),
            type: 'text',
            sortable: true,
            filterable: false,
            selected: true,
            minWidth: 220,
        },
        ...localeColumns,
        {
            key: 'missing_locales_display',
            label: t('table.missing', 'Ontbreekt'),
            type: 'text',
            sortable: false,
            filterable: false,
            selected: true,
            minWidth: 160,
        },
    ];
});

const filteredRows = computed(() => {
    return activeRowsState.value.filter((row) => {
        const mode = String(missingFilterMode.value || 'missing_any');

        if (mode === 'all') {
            return true;
        }

    if (mode === 'missing_any') {
        return Number(row?.missing_count || 0) > 0;
    }

    if (mode === 'ai_review') {
        return Number(row?.ai_review_count || 0) > 0;
    }

    if (mode.startsWith('locale:')) {
            const targetLocale = mode.slice(7);
            const missingLocales = Array.isArray(row?.missing_locales)
                ? row.missing_locales.map((locale) => String(locale || ''))
                : [];

            return missingLocales.includes(targetLocale);
        }

        return Number(row?.missing_count || 0) > 0;
    });
});

function cloneTableRow(row) {
    const clonedRow = {
        ...row,
    };

    if (Array.isArray(row?.missing_locales)) {
        clonedRow.missing_locales = [...row.missing_locales];
    }

    return clonedRow;
}

const tableData = shallowRef({
    data: [],
    total: 0,
});

watch(
    filteredRows,
    (rows) => {
        const snapshotRows = rows.map((row) => markRaw(cloneTableRow(row)));

        tableData.value = markRaw({
            data: markRaw(snapshotRows),
            total: snapshotRows.length,
        });
    },
    { immediate: true },
);

function goBack() {
    router.visit(route(isPlatformMode.value ? 'platform.dashboard' : 'admin'));
}

function resolveInitialActiveTab() {
    if (isPlatformMode.value) {
        return 'admin';
    }

    if (typeof window === 'undefined') {
        return 'public';
    }

    const requestedTab = new URL(window.location.href).searchParams.get('tab');

    return availableTabs.value.includes(requestedTab) ? requestedTab : 'public';
}

function currentReturnUrl() {
    const target = new URL(route('admin.translations.index'), window.location.origin);
    target.searchParams.set('tab', activeTab.value);

    return `${target.pathname}${target.search}`;
}

function withReturnTo(targetUrl) {
    if (typeof window === 'undefined') {
        return targetUrl;
    }

    const target = new URL(targetUrl, window.location.origin);
    target.searchParams.set('returnTo', currentReturnUrl());

    return `${target.pathname}${target.search}${target.hash}`;
}

function cellClass({ col }) {
    return col?.clickable ? 'cursor-pointer' : null;
}

function setActiveTab(tab) {
    const requested = String(tab || '');
    const requestedTab = ['admin', 'content'].includes(requested)
        ? requested
        : 'public';
    activeTab.value = availableTabs.value.includes(requestedTab)
        ? requestedTab
        : availableTabs.value[0];

    if (String(missingFilterMode.value || '').startsWith('locale:')) {
        const selectedLocale = String(missingFilterMode.value || '').slice(7);

        if (!activeLocalesState.value.includes(selectedLocale)) {
            missingFilterMode.value = 'missing_any';
        }
    }

    if (activeTab.value !== 'content' && missingFilterMode.value === 'ai_review') {
        missingFilterMode.value = 'missing_any';
    }

    if (activeTab.value === 'content' && contentRowsState.value.length === 0) {
        void reloadRows({ silent: true });
    }
}

function openAddLocaleDialog() {
    if (
        newLocaleSource.value !== '' &&
        !localesState.value.includes(newLocaleSource.value)
    ) {
        newLocaleSource.value = String(
            defaultSourceLocale.value || localesState.value[0] || 'nl',
        );
    }

    addLocaleDialogOpen.value = true;
}

function openAiFillDialog() {
    if (syncing.value || addingLocale.value || aiFilling.value) {
        return;
    }

    if (
        !aiFillTargetLocale.value ||
        aiFillTargetLocale.value === activeDefaultSourceLocale.value
    ) {
        aiFillTargetLocale.value =
            aiFillLocaleOptions.value[0]?.value ||
            String(
                activeLocalesState.value[0] ||
                    activeDefaultSourceLocale.value ||
                    '',
            );
    }

    aiFillDialogOpen.value = true;
}

function closeAiFillDialog() {
    if (aiFilling.value) {
        return;
    }

    aiFillDialogOpen.value = false;
}

function openContentBulkAiDialog() {
    if (contentBulkAiRunning.value || activeTab.value !== 'content') {
        return;
    }

    if (!contentBulkAiTargetLocale.value) {
        contentBulkAiTargetLocale.value =
            contentBulkAiLocaleOptions.value[0]?.value || '';
    }

    contentBulkAiDialogOpen.value = true;
}

function closeContentBulkAiDialog() {
    if (contentBulkAiRunning.value) {
        return;
    }

    contentBulkAiDialogOpen.value = false;
}

function closeAddLocaleDialog() {
    if (addingLocale.value) {
        return;
    }

    addLocaleDialogOpen.value = false;
}

function openSyncWarningDialog() {
    if (syncing.value || addingLocale.value) {
        return;
    }

    syncWarningDialogOpen.value = true;
}

function closeSyncWarningDialog() {
    if (syncing.value) {
        return;
    }

    syncWarningDialogOpen.value = false;
}

async function confirmSyncMissing() {
    syncWarningDialogOpen.value = false;
    await syncMissing();
}

function setFeedback(type, message) {
    feedback.value = {
        type: String(type || ''),
        message: String(message || ''),
    };
}

function persistTranslationFilters() {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(
            translationFiltersStorageKey,
            JSON.stringify({
                missingFilterMode: String(
                    missingFilterMode.value || 'missing_any',
                ),
            }),
        );
    } catch {
        return;
    }
}

function normalizeLocaleCode(value) {
    const normalized = String(value || '')
        .trim()
        .replace('-', '_');

    if (normalized === '') {
        return '';
    }

    const [languagePart = '', countryPart = ''] = normalized.split('_', 2);

    if (countryPart === '') {
        return languagePart.toLowerCase();
    }

    return `${languagePart.toLowerCase()}_${countryPart.toUpperCase()}`;
}

function normalizeNewLocaleInput() {
    newLocale.value = normalizeLocaleCode(newLocale.value);
}

async function reloadRows(options = {}) {
    const silent = Boolean(options?.silent);

    if (loadingRows.value) {
        return;
    }

    loadingRows.value = true;

    try {
        const response = await window.axios.get(
            route(routeName('rows')),
            {},
        );

        if (activeTab.value === 'public') {
            publicRowsState.value = Array.isArray(response?.data?.rows)
                ? [...response.data.rows]
                : [];
            publicLocalesState.value = Array.isArray(response?.data?.locales)
                ? [...response.data.locales]
                : [];
        } else if (activeTab.value === 'content') {
            contentRowsState.value = Array.isArray(response?.data?.rows)
                ? [...response.data.rows]
                : [];
            contentLocalesState.value = Array.isArray(response?.data?.locales)
                ? [...response.data.locales]
                : contentLocalesState.value;
        } else {
            rowsState.value = Array.isArray(response?.data?.rows)
                ? [...response.data.rows]
                : [];
            localesState.value = Array.isArray(response?.data?.locales)
                ? [...response.data.locales]
                : [];
        }

        if (!localesState.value.includes(defaultSourceLocale.value)) {
            defaultSourceLocale.value = String(localesState.value[0] || 'nl');
        }

        if (
            newLocaleSource.value !== '' &&
            !localesState.value.includes(newLocaleSource.value)
        ) {
            newLocaleSource.value = String(localesState.value[0] || 'nl');
        }

        if (String(missingFilterMode.value || '').startsWith('locale:')) {
            const selectedLocale = String(missingFilterMode.value || '').slice(
                7,
            );

            if (!activeLocalesState.value.includes(selectedLocale)) {
                missingFilterMode.value = 'missing_any';
            }
        }

        if (!silent) {
            setFeedback(
                'success',
                t('feedback.rows_reloaded', 'Vertaalrijen herladen.'),
            );
        }
    } catch (error) {
        const message =
            error?.response?.data?.message ||
            t('feedback.rows_reload_failed', 'Kon vertaalrijen niet herladen.');

        if (!silent) {
            setFeedback('danger', message);
        }
    } finally {
        loadingRows.value = false;
    }
}

async function syncMissing() {
    if (syncing.value) {
        return;
    }

    syncing.value = true;

    try {
        const response = await window.axios.post(
            route(routeName('sync')),
            {},
        );

        if (activeTab.value === 'public') {
            publicRowsState.value = Array.isArray(response?.data?.rows)
                ? [...response.data.rows]
                : publicRowsState.value;
            publicLocalesState.value = Array.isArray(response?.data?.locales)
                ? [...response.data.locales]
                : publicLocalesState.value;
        } else {
            rowsState.value = Array.isArray(response?.data?.rows)
                ? [...response.data.rows]
                : rowsState.value;
            localesState.value = Array.isArray(response?.data?.locales)
                ? [...response.data.locales]
                : localesState.value;
        }

        const result = response?.data?.result || {};
        if (activeTab.value === 'public') {
            const hardcodedCount = Array.isArray(result.hardcoded_warnings)
                ? result.hardcoded_warnings.length
                : 0;
            const unusedCount = Array.isArray(result.unused_warnings)
                ? result.unused_warnings.length
                : 0;
            const changedDefaultCount = Array.isArray(
                result.changed_default_warnings,
            )
                ? result.changed_default_warnings.length
                : 0;

            setFeedback(
                hardcodedCount > 0 || changedDefaultCount > 0 ? 'warning' : 'success',
                response?.data?.message ||
                    t(
                        'feedback.public_sync_success',
                        'Public text sync completed. :keys_found keys found, :texts_created texts and :translations_created translation rows created.',
                        {
                            keys_found: Number(result.keys_found || 0),
                            texts_created: Number(result.texts_created || 0),
                            translations_created: Number(
                                result.translations_created || 0,
                            ),
                        },
                    ),
            );

            if (hardcodedCount > 0 || unusedCount > 0 || changedDefaultCount > 0) {
                setFeedback(
                    'warning',
                    response?.data?.warning_message ||
                        t(
                            'feedback.public_sync_warnings',
                            'Sync completed with warnings: :hardcoded hardcoded, :unused unused, :changed changed defaults.',
                            {
                                hardcoded: hardcodedCount,
                                unused: unusedCount,
                                changed: changedDefaultCount,
                            },
                        ),
                );
            }
        } else {
            setFeedback(
                'success',
                t(
                    'feedback.sync_success',
                    'Sync afgerond. :updated_keys keys bijgewerkt in :updated_locales locale-bestanden. :acl_keys_created ACL keys toegevoegd.',
                    {
                        updated_keys: Number(result.updated_keys || 0),
                        updated_locales: Number(result.updated_locales || 0),
                        acl_keys_created: Number(result.acl_keys_created || 0),
                    },
                ),
            );
        }
    } catch (error) {
        const message =
            error?.response?.data?.message ||
            t(
                'feedback.sync_failed',
                'Synchroniseren van vertalingen is mislukt.',
            );
        setFeedback('danger', message);
    } finally {
        syncing.value = false;
    }
}

async function addLocale() {
    if (addingLocale.value) {
        return;
    }

    const locale = normalizeLocaleCode(newLocale.value);
    const sourceLocale = String(newLocaleSource.value || '').trim();

    newLocale.value = locale;

    if (locale === '') {
        setFeedback(
            'warning',
            t('feedback.locale_required', 'Geef eerst een locale op.'),
        );
        return;
    }

    addingLocale.value = true;

    try {
        const response = await window.axios.post(
            route('platform.translations.add-locale'),
            {
                locale,
                source_locale: sourceLocale !== '' ? sourceLocale : null,
            },
        );

        rowsState.value = Array.isArray(response?.data?.rows)
            ? [...response.data.rows]
            : rowsState.value;
        localesState.value = Array.isArray(response?.data?.locales)
            ? [...response.data.locales]
            : localesState.value;

        newLocale.value = '';
        addLocaleDialogOpen.value = false;
        setFeedback(
            'success',
            response?.data?.message ||
                t('feedback.locale_added', 'Taal toegevoegd.'),
        );
    } catch (error) {
        const message =
            error?.response?.data?.errors?.locale?.[0] ||
            error?.response?.data?.message ||
            t('feedback.locale_add_failed', 'Taal toevoegen is mislukt.');
        setFeedback('danger', message);
    } finally {
        addingLocale.value = false;
    }
}

function routeName(action) {
    if (activeTab.value === 'public') {
        return `admin.translations.public.${action}`;
    }

    if (activeTab.value === 'content') {
        return `admin.translations.content.${action}`;
    }

    return `platform.translations.${action}`;
}

function canEditContentLocale(locale) {
    if (isPlatformMode.value) {
        return true;
    }

    return props.editable_content_locales
        .map((value) => String(value || ''))
        .includes(String(locale || ''));
}

async function handleCellClick(field, id) {
    if (activeTab.value !== 'content') {
        return;
    }

    const row = contentRowsState.value.find((item) => item?.id === id);
    const locale = String(field || '').startsWith('value_')
        ? String(field).slice(6)
        : '';

    if (!canEditContentLocale(locale)) {
        return;
    }

    const url = row?.[`url_${locale}`];

    if (typeof url === 'string' && url.trim() !== '') {
        router.visit(withReturnTo(url));

        return;
    }

    if (creatingContentTranslation.value || !row?.source_id) {
        return;
    }

    selectedContentTranslationRequest.value = {
        type: row.type,
        source_id: row.source_id,
        locale,
        label: row.source_label,
    };
    contentTranslationDialogOpen.value = true;
}

function closeContentTranslationDialog() {
    if (creatingContentTranslation.value) {
        return;
    }

    contentTranslationDialogOpen.value = false;
    selectedContentTranslationRequest.value = null;
}

async function createSelectedContentTranslation(useAi) {
    const request = selectedContentTranslationRequest.value;

    if (!request || creatingContentTranslation.value) {
        return;
    }

    creatingContentTranslation.value = true;

    try {
        const response = await window.axios.post(
            route(routeName('store')),
            {
                type: request.type,
                source_id: request.source_id,
                target_locale: request.locale,
                use_ai: Boolean(useAi),
            },
        );
        const createdUrl = response?.data?.url;

        if (typeof createdUrl === 'string' && createdUrl.trim() !== '') {
            router.visit(withReturnTo(createdUrl));

            return;
        }

        await reloadRows({ silent: true });
    } catch (error) {
        const message =
            error?.response?.data?.message ||
            t(
                'feedback.content_translation_create_failed',
                'De contentvertaling kon niet aangemaakt worden.',
            );
        setFeedback('danger', message);
    } finally {
        creatingContentTranslation.value = false;
        contentTranslationDialogOpen.value = false;
        selectedContentTranslationRequest.value = null;
    }
}

async function runAiFill() {
    if (aiFilling.value) {
        return;
    }

    const targetLocale = String(aiFillTargetLocale.value || '').trim();

    if (
        targetLocale === '' ||
        targetLocale === String(activeDefaultSourceLocale.value)
    ) {
        setFeedback(
            'warning',
            t(
                'feedback.ai_fill_target_required',
                'Kies een geldige doellocale voor AI aanvullen.',
            ),
        );
        return;
    }

    aiFilling.value = true;

    try {
        const response = await window.axios.post(
            route(routeName('ai-fill')),
            {
                target_locale: targetLocale,
                source_locale: String(activeDefaultSourceLocale.value || ''),
                limit: Math.min(
                    aiFillLimitMax.value,
                    Math.max(1, Number(aiFillLimit.value || 1)),
                ),
            },
        );

        if (activeTab.value === 'public') {
            publicRowsState.value = Array.isArray(response?.data?.rows)
                ? [...response.data.rows]
                : publicRowsState.value;
            publicLocalesState.value = Array.isArray(response?.data?.locales)
                ? [...response.data.locales]
                : publicLocalesState.value;
        } else {
            rowsState.value = Array.isArray(response?.data?.rows)
                ? [...response.data.rows]
                : rowsState.value;
            localesState.value = Array.isArray(response?.data?.locales)
                ? [...response.data.locales]
                : localesState.value;
        }

        aiFillDialogOpen.value = false;

        await reloadRows({ silent: true });

        setFeedback(
            'success',
            response?.data?.message ||
                t('feedback.ai_fill_success', 'AI aanvullen afgerond.'),
        );
    } catch (error) {
        const message =
            error?.response?.data?.errors?.target_locale?.[0] ||
            error?.response?.data?.message ||
            t(
                'feedback.ai_fill_failed',
                'AI aanvullen van vertalingen is mislukt.',
            );
        setFeedback('danger', message);
    } finally {
        aiFilling.value = false;
    }
}

async function runContentBulkAi() {
    if (contentBulkAiRunning.value) {
        return;
    }

    const targetLocale = String(contentBulkAiTargetLocale.value || '').trim();
    const items = contentBulkAiItems.value;

    if (targetLocale === '' || items.length === 0) {
        setFeedback(
            'warning',
            t(
                'feedback.content_bulk_ai_empty',
                'Geen ontbrekende contentvertalingen gevonden voor deze doeltaal.',
            ),
        );
        return;
    }

    contentBulkAiRunning.value = true;
    contentBulkAiDialogOpen.value = false;

    try {
        const response = await window.axios.post(
            route(routeName('bulk-ai')),
            {
                target_locale: targetLocale,
                limit: Math.min(
                    50,
                    Math.max(1, Number(contentBulkAiLimit.value || 10)),
                ),
                items,
            },
        );

        await reloadRows({ silent: true });
        setFeedback(
            Number(response?.data?.failed || 0) > 0 ? 'warning' : 'success',
            response?.data?.message ||
                t(
                    'feedback.content_bulk_ai_success',
                    'Bulk AI contentvertalingen aangemaakt.',
                ),
        );
    } catch (error) {
        const failedCount = Number(error?.response?.data?.failed || 0);
        const message =
            error?.response?.data?.errors?.target_locale?.[0] ||
            error?.response?.data?.error_message ||
            error?.response?.data?.message ||
            (failedCount > 0
                ? t(
                      'feedback.content_bulk_ai_failed_count',
                      'Bulk AI contentvertalingen konden niet aangemaakt worden. :failed mislukt.',
                      { failed: failedCount },
                  )
                : '') ||
            t(
                'feedback.content_bulk_ai_failed',
                'Bulk AI contentvertalingen konden niet aangemaakt worden.',
            );
        setFeedback('danger', message);
    } finally {
        contentBulkAiRunning.value = false;
    }
}

watch(missingFilterMode, () => {
    persistTranslationFilters();
});
</script>

<style scoped></style>
