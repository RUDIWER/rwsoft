<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent
            class="max-h-[calc(100vh-4rem)] max-w-4xl gap-0 overflow-hidden p-0 shadow-none"
        >
            <DialogHeader
                class="border-b border-slate-200 px-4 py-4 pr-12 sm:px-5"
            >
                <DialogTitle>{{
                    t('revisions.title', 'Versions')
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        t(
                            'revisions.description',
                            'View and restore previously saved versions.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="restoreErrorMessages.length > 0"
                class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 sm:px-5"
            >
                <p v-for="message in restoreErrorMessages" :key="message">
                    {{ message }}
                </p>
            </div>

            <div
                class="grid max-h-[calc(100vh-14rem)] min-h-0 gap-4 overflow-y-auto p-4 sm:p-5 lg:grid-cols-[minmax(0,1fr)_280px] lg:overflow-hidden"
            >
                <div class="flex min-h-0 flex-col">
                    <div
                        class="shrink-0 rounded-md border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800"
                    >
                        {{
                            t(
                                'revisions.restore_audit_explanation',
                                'Restoring automatically creates a backup version before restore and a new version after restore.',
                            )
                        }}
                    </div>

                    <div
                        v-if="revisions.length === 0"
                        class="mt-3 rounded-md border border-dashed border-slate-300 p-5 text-sm text-slate-500"
                    >
                        {{ t('revisions.empty', 'No versions available yet.') }}
                    </div>

                    <div
                        v-else
                        class="mt-3 grid min-h-0 gap-3 overflow-y-auto pr-1"
                    >
                        <button
                            v-for="revision in revisions"
                            :key="revision.id"
                            type="button"
                            class="grid gap-2 rounded-lg border p-3 text-left transition hover:border-blue-300 hover:bg-blue-50"
                            :class="
                                selectedRevision?.id === revision.id
                                    ? 'border-blue-400 bg-blue-50'
                                    : 'border-slate-200 bg-white'
                            "
                            @click="selectedRevision = revision"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <span class="font-medium text-slate-900">
                                    {{
                                        t(
                                            'revisions.revision_number',
                                            'Version #:number',
                                            {
                                                number: revision.revision_number,
                                            },
                                        )
                                    }}
                                </span>
                                <span
                                    class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
                                >
                                    {{ scopeLabel(revision.scope) }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-500">
                                {{ revision.created_at }}
                                <span v-if="revision.author_name">
                                    · {{ revision.author_name }}</span
                                >
                            </div>
                            <div
                                class="flex flex-wrap gap-2 text-xs text-slate-600"
                            >
                                <span
                                    v-for="badge in metadataBadges(revision)"
                                    :key="badge.key"
                                >
                                    {{ badge.value }} {{ badge.label }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>

                <aside
                    class="grid content-start gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4"
                >
                    <div class="grid gap-1">
                        <h3 class="text-sm font-semibold text-slate-900">
                            {{
                                t(
                                    'revisions.restore_options',
                                    'Restore options',
                                )
                            }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{
                                t(
                                    'revisions.restore_options_help',
                                    'Choose whether only text/media or also the structure is restored.',
                                )
                            }}
                        </p>
                    </div>

                    <label
                        class="flex gap-2 rounded-md border border-slate-200 bg-white p-3 text-sm"
                    >
                        <input
                            v-model="restoreForm.mode"
                            type="radio"
                            value="content"
                            class="accent-blue-600"
                        />
                        <span>
                            <span class="block font-medium text-slate-900">{{
                                t('revisions.content_only', 'Only content')
                            }}</span>
                            <span class="block text-xs text-slate-500">{{
                                t(
                                    'revisions.content_only_help',
                                    'Keeps the current sections, order, and grid.',
                                )
                            }}</span>
                        </span>
                    </label>

                    <label
                        class="flex gap-2 rounded-md border border-slate-200 bg-white p-3 text-sm"
                    >
                        <input
                            v-model="restoreForm.mode"
                            type="radio"
                            value="full"
                            class="accent-blue-600"
                        />
                        <span>
                            <span class="block font-medium text-slate-900">{{
                                t(
                                    'revisions.content_and_structure',
                                    'Content + structure',
                                )
                            }}</span>
                            <span class="block text-xs text-slate-500">{{
                                t(
                                    'revisions.content_and_structure_help',
                                    'Also restores sections, blocks, order, and layout settings.',
                                )
                            }}</span>
                        </span>
                    </label>

                    <label
                        v-if="requiresLayoutConfirmation"
                        class="flex gap-2 rounded-md border border-orange-200 bg-orange-50 p-3 text-sm text-orange-900"
                    >
                        <input
                            type="checkbox"
                            :checked="impactConfirmed"
                            class="accent-blue-600"
                            @change="updateImpactConfirmation"
                        />
                        <span>
                            {{
                                t(
                                    impactConfirmationKey,
                                    impactConfirmationFallback,
                                    { count: impactCount },
                                )
                            }}
                        </span>
                    </label>
                </aside>
            </div>

            <DialogFooter
                class="border-t border-slate-200 bg-slate-50 px-4 py-3 sm:px-5"
            >
                <Button
                    type="button"
                    variant="ghost"
                    class="border border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                    :disabled="
                        !selectedRevision ||
                        restoreForm.processing ||
                        requiresLayoutConfirmation
                    "
                    @click="restoreSelectedRevision"
                >
                    <span
                        v-if="restoreForm.processing"
                        class="mdi mdi-loading animate-spin text-base text-green-700"
                        aria-hidden="true"
                    />
                    <span
                        v-else
                        class="mdi mdi-backup-restore text-base text-green-700"
                        aria-hidden="true"
                    />
                    {{ t('revisions.restore', 'Restore') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup>
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    subjectType: {
        type: String,
        required: true,
    },
    restoreRouteName: {
        type: String,
        required: true,
    },
    restoreRouteParams: {
        type: Object,
        required: true,
    },
    revisions: {
        type: Array,
        default: () => [],
    },
    impactPagesCount: {
        type: Number,
        default: 0,
    },
    impactItemsCount: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['update:open', 'restored']);
const { t } = useAdminTranslations('cms_admin_ui');

const selectedRevision = ref(props.revisions[0] ?? null);
const restoreForm = useForm({
    mode: 'content',
    confirm_layout_impact: false,
    confirm_template_impact: false,
});

const dialogOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const impactCount = computed(
    () => props.impactItemsCount || props.impactPagesCount,
);

const requiresLayoutConfirmation = computed(
    () =>
        ['layout', 'template', 'mail_template'].includes(props.subjectType) &&
        restoreForm.mode === 'full' &&
        impactCount.value > 0 &&
        !impactConfirmed.value,
);

const impactConfirmed = computed(() =>
    ['template', 'mail_template'].includes(props.subjectType)
        ? restoreForm.confirm_template_impact
        : restoreForm.confirm_layout_impact,
);

const impactConfirmationKey = computed(() => {
    if (props.subjectType === 'mail_template') {
        return 'revisions.confirm_mail_template_impact';
    }

    return props.subjectType === 'template'
        ? 'revisions.confirm_template_impact'
        : 'revisions.confirm_layout_impact';
});

const impactConfirmationFallback = computed(() => {
    if (props.subjectType === 'mail_template') {
        return 'I understand that this mail template is used by :count emails.';
    }

    return props.subjectType === 'template'
        ? 'I understand that this template is used by :count content items.'
        : 'I understand that this layout is used by :count pages.';
});

const restoreErrorMessages = computed(() =>
    Object.values(restoreForm.errors ?? {}).filter(Boolean),
);

watch(
    () => props.revisions,
    (revisions) => {
        selectedRevision.value = revisions[0] ?? null;
    },
);

watch(
    () => restoreForm.mode,
    () => {
        restoreForm.confirm_layout_impact = false;
        restoreForm.confirm_template_impact = false;
    },
);

function scopeLabel(scope) {
    const labels = {
        full: t('revisions.scopes.full', 'Full'),
        restore: t('revisions.scopes.restore', 'Restore'),
        restore_backup: t('revisions.scopes.restore_backup', 'Restore backup'),
    };

    return labels[scope] ?? scope;
}

function metadataBadges(revision) {
    const metadata = revision.metadata ?? {};
    const badges = [
        {
            key: 'sections_count',
            label: t('revisions.sections', 'sections'),
        },
        {
            key: 'blocks_count',
            label: t('revisions.blocks', 'blocks'),
        },
        {
            key: 'content_blocks_count',
            label: t('revisions.content_blocks', 'content blocks'),
        },
        {
            key: 'menu_items_count',
            label: t('revisions.menu_items', 'menu-items'),
        },
        {
            key: 'fields_count',
            label: t('revisions.fields', 'fields'),
        },
        {
            key: 'taxonomy_relations_count',
            label: t('revisions.taxonomy_relations', 'relations'),
        },
        {
            key: 'template_usage_count',
            label: t('revisions.template_usage', 'content items'),
        },
    ];

    return badges
        .filter((badge) => Number(metadata[badge.key] ?? 0) > 0)
        .map((badge) => ({
            ...badge,
            value: Number(metadata[badge.key] ?? 0),
        }));
}

function restoreSelectedRevision() {
    if (!selectedRevision.value) {
        return;
    }

    restoreForm.post(
        route(props.restoreRouteName, {
            ...props.restoreRouteParams,
            revision: selectedRevision.value.id,
        }),
        {
            preserveScroll: true,
            preserveState: 'errors',
            onError: () => {
                dialogOpen.value = true;
            },
            onSuccess: () => {
                dialogOpen.value = false;
                emit('restored');
            },
        },
    );
}

function updateImpactConfirmation(event) {
    const checked = Boolean(event.target?.checked);

    if (['template', 'mail_template'].includes(props.subjectType)) {
        restoreForm.confirm_template_impact = checked;

        return;
    }

    restoreForm.confirm_layout_impact = checked;
}
</script>
