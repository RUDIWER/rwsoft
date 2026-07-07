<template>
    <Head :title="t('client_rules.meta_title', 'Client validatie regels')" />

    <AdminLayout :suppress-flash="true">
        <RwFormTemplate
            :title="t('client_rules.title', 'Client validatie regels')"
            :subtitle="
                t(
                    'client_rules.subtitle',
                    'Beheer globale client-side validatieregels met versiebeheer.',
                )
            "
        >
            <template #back>
                <RwActionButton
                    :label="t('actions.back', 'Terug')"
                    icon="mdi mdi-arrow-left-circle"
                    tone="back"
                    @click="goBack"
                />
            </template>

            <template #actions>
                <RwActionButton
                    :label="t('actions.save', 'Opslaan')"
                    icon="mdi mdi-content-save"
                    tone="save"
                    :loading="saving"
                    :disabled="saving || publishing || editorCode.trim() === ''"
                    @click="saveVersion"
                />
                <RwActionButton
                    :label="t('client_rules.actions.publish', 'Publiceer')"
                    icon="mdi mdi-cloud-upload"
                    tone="new"
                    :loading="publishing"
                    :disabled="!canPublish"
                    @click="publishSelectedVersion"
                />
            </template>

            <template #flash>
                <RwFlashMessage
                    :type="feedback.type"
                    :message="feedback.message"
                />
            </template>

            <div class="grid gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
                <div class="space-y-3">
                    <div
                        class="rounded border border-slate-200 bg-slate-50 p-3"
                    >
                        <p class="text-xs font-semibold text-slate-700">
                            {{ t('client_rules.versions.title', 'Versies') }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            {{
                                t(
                                    'client_rules.versions.publish_help',
                                    'Publiceren is enkel mogelijk nadat de huidige code eerst bewaard is.',
                                )
                            }}
                        </p>
                    </div>

                    <div
                        v-if="versionsState.length === 0"
                        class="rounded border border-dashed border-slate-300 bg-white px-3 py-4 text-sm text-slate-500"
                    >
                        {{
                            t(
                                'client_rules.versions.empty',
                                'Nog geen versie beschikbaar. Bewaar eerst een versie.',
                            )
                        }}
                    </div>

                    <div v-else class="space-y-2">
                        <button
                            v-for="version in versionsState"
                            :key="version.id"
                            type="button"
                            class="w-full rounded border bg-white px-3 py-2 text-left"
                            :class="
                                Number(selectedVersionId) === Number(version.id)
                                    ? 'border-blue-300 bg-blue-50'
                                    : 'border-slate-200 hover:bg-slate-50'
                            "
                            @click="selectVersion(version.id)"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p class="text-xs font-semibold text-slate-800">
                                    {{
                                        t(
                                            'client_rules.versions.version_label',
                                            'Versie :version',
                                            { version: version.version },
                                        )
                                    }}
                                </p>
                                <span
                                    class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase"
                                    :class="stateBadgeClass(version.state)"
                                >
                                    {{ versionStateLabel(version.state) }}
                                </span>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-600">
                                {{
                                    t(
                                        'client_rules.versions.build_label',
                                        'Build: :status',
                                        {
                                            status: buildStatusLabel(
                                                version.build_status,
                                            ),
                                        },
                                    )
                                }}
                            </p>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                {{ formatDate(version.updated_at) }}
                            </p>
                        </button>
                    </div>
                </div>

                <div class="space-y-3">
                    <div
                        class="rounded border border-slate-200 bg-slate-50 p-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-slate-700">
                                {{
                                    t(
                                        'client_rules.editor.title',
                                        'Code editor',
                                    )
                                }}
                            </p>
                            <span
                                v-if="selectedVersion"
                                class="text-[11px] text-slate-500"
                            >
                                {{
                                    t(
                                        'client_rules.editor.selected_version',
                                        'Geselecteerd: versie :version',
                                        { version: selectedVersion.version },
                                    )
                                }}
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-500">
                            {{
                                t(
                                    'client_rules.editor.help_prefix',
                                    'Gebruik volledige JS modulecode voor',
                                )
                            }}
                            <code>extended_rules.js</code>.
                        </p>
                    </div>

                    <RwCodeEditor
                        v-model="editorCode"
                        language="javascript"
                        height="620px"
                        :placeholder="
                            t(
                                'client_rules.editor.placeholder',
                                'Plaats hier volledige JS modulecode voor extended_rules.js',
                            )
                        "
                        :disabled="loadingVersion || saving || publishing"
                    />

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700"
                            :disabled="!selectedVersionId || loadingVersion"
                            @click="reloadSelectedVersion"
                        >
                            <i class="mdi mdi-refresh" />
                            {{
                                t(
                                    'client_rules.actions.reload_version',
                                    'Herlaad versie',
                                )
                            }}
                        </button>

                        <span
                            v-if="isDirty"
                            class="rounded bg-orange-100 px-2 py-1 text-[11px] font-medium text-orange-800"
                        >
                            {{
                                t(
                                    'client_rules.editor.unsaved_changes',
                                    'Niet bewaarde wijzigingen',
                                )
                            }}
                        </span>
                    </div>

                    <div
                        v-if="selectedVersion && selectedVersion.build_log"
                        class="rounded border border-slate-200 bg-white"
                    >
                        <div
                            class="border-b border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700"
                        >
                            {{
                                t(
                                    'client_rules.editor.build_log_title',
                                    'Build log geselecteerde versie',
                                )
                            }}
                        </div>
                        <pre
                            class="max-h-64 overflow-auto px-3 py-2 text-[11px] text-slate-700"
                            >{{ selectedVersion.build_log }}</pre
                        >
                    </div>
                </div>
            </div>
        </RwFormTemplate>
    </AdminLayout>
</template>

<script setup>
import RwActionButton from '@/Components/RwActionButton.vue';
import RwCodeEditor from '@/Components/RwCodeEditor.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwFormTemplate from '@/Components/RwFormTemplate.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    versions: {
        type: Array,
        default: () => [],
    },
    selected_version: {
        type: Object,
        default: null,
    },
    code: {
        type: String,
        default: '',
    },
});

const versionsState = ref(
    Array.isArray(props.versions) ? [...props.versions] : [],
);
const selectedVersionId = ref(Number(props.selected_version?.id || 0));
const editorCode = ref(String(props.code || ''));
const loadedCode = ref(String(props.code || ''));
const feedback = ref({ type: '', message: '' });
const loadingVersion = ref(false);
const saving = ref(false);
const publishing = ref(false);
const { t } = useAdminTranslations('admin_common_ui');

const selectedVersion = computed(() => {
    return (
        versionsState.value.find(
            (version) =>
                Number(version?.id || 0) ===
                Number(selectedVersionId.value || 0),
        ) || null
    );
});

const isDirty = computed(() => {
    return (
        String(editorCode.value || '').trim() !==
        String(loadedCode.value || '').trim()
    );
});

const canPublish = computed(() => {
    return (
        Number(selectedVersionId.value || 0) > 0 &&
        !isDirty.value &&
        !saving.value &&
        !publishing.value
    );
});

function goBack() {
    router.visit(route('admin.db-diagram'));
}

function stateBadgeClass(state) {
    if (state === 'published') {
        return 'bg-green-100 text-green-800';
    }

    if (state === 'draft') {
        return 'bg-slate-200 text-slate-700';
    }

    return 'bg-orange-100 text-orange-800';
}

function versionStateLabel(state) {
    const normalizedState = ['draft', 'published'].includes(state)
        ? state
        : 'unknown';

    return t(
        `client_rules.status.version_${normalizedState}`,
        t('client_rules.status.unknown', 'Onbekend'),
    );
}

function buildStatusLabel(status) {
    const normalizedStatus = [
        'pending',
        'success',
        'skipped',
        'failed',
    ].includes(status)
        ? status
        : 'pending';

    return t(
        `client_rules.status.build_${normalizedStatus}`,
        t('client_rules.status.pending', 'pending'),
    );
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return String(value).replace('T', ' ').slice(0, 19);
}

async function selectVersion(versionId) {
    const normalizedVersionId = Number(versionId || 0);

    if (normalizedVersionId <= 0 || loadingVersion.value) {
        return;
    }

    loadingVersion.value = true;
    feedback.value = { type: '', message: '' };

    try {
        const response = await window.axios.get(
            route('admin.client-rules.code'),
            {
                params: {
                    version_id: normalizedVersionId,
                },
            },
        );

        const version = response?.data?.version || null;

        if (!version || typeof version !== 'object') {
            feedback.value = {
                type: 'danger',
                message: t(
                    'client_rules.feedback.version_load_failed',
                    'Kon de gekozen versie niet laden.',
                ),
            };
            return;
        }

        selectedVersionId.value = Number(version.id || 0);
        editorCode.value = String(version.code || '');
        loadedCode.value = String(version.code || '');
    } catch (error) {
        const message =
            error?.response?.data?.message ||
            error?.response?.data?.errors?.version_id?.[0] ||
            t(
                'client_rules.feedback.version_code_load_failed',
                'Kon versiecode niet laden.',
            );

        feedback.value = {
            type: 'danger',
            message: String(message),
        };
    } finally {
        loadingVersion.value = false;
    }
}

function reloadSelectedVersion() {
    if (Number(selectedVersionId.value || 0) <= 0) {
        return;
    }

    selectVersion(selectedVersionId.value);
}

async function saveVersion() {
    if (saving.value || publishing.value) {
        return;
    }

    saving.value = true;
    feedback.value = { type: '', message: '' };

    try {
        const response = await window.axios.post(
            route('admin.client-rules.save'),
            {
                code: String(editorCode.value || ''),
            },
        );

        const version = response?.data?.version || null;
        const versions = response?.data?.versions;
        const build = response?.data?.build || {};

        if (Array.isArray(versions)) {
            versionsState.value = [...versions];
        }

        if (version && typeof version === 'object') {
            selectedVersionId.value = Number(version.id || 0);
        }

        loadedCode.value = String(editorCode.value || '');

        const baseMessage = String(
            response?.data?.message ||
                t(
                    'client_rules.feedback.saved',
                    'Client validatie regelversie bewaard.',
                ),
        );
        const buildMessage =
            build?.status === 'success'
                ? t('client_rules.feedback.build_success', 'Build geslaagd.')
                : build?.status === 'skipped'
                  ? t(
                        'client_rules.feedback.build_skipped',
                        'Build overgeslagen.',
                    )
                  : build?.status === 'failed'
                    ? t(
                          'client_rules.feedback.build_failed',
                          'Build gefaald. Bekijk de log.',
                      )
                    : '';

        feedback.value = {
            type: build?.status === 'failed' ? 'warning' : 'success',
            message: [baseMessage, buildMessage]
                .filter((entry) => entry !== '')
                .join(' '),
        };
    } catch (error) {
        const message =
            error?.response?.data?.errors?.code?.[0] ||
            error?.response?.data?.message ||
            t(
                'client_rules.feedback.save_failed',
                'Opslaan van de versie is mislukt.',
            );

        feedback.value = {
            type: 'danger',
            message: String(message),
        };
    } finally {
        saving.value = false;
    }
}

async function publishSelectedVersion() {
    if (!canPublish.value) {
        return;
    }

    publishing.value = true;
    feedback.value = { type: '', message: '' };

    try {
        const response = await window.axios.post(
            route('admin.client-rules.publish'),
            {
                version_id: Number(selectedVersionId.value || 0),
            },
        );

        const versions = response?.data?.versions;

        if (Array.isArray(versions)) {
            versionsState.value = [...versions];
        }

        feedback.value = {
            type: 'success',
            message: String(
                response?.data?.message ||
                    t(
                        'client_rules.feedback.published',
                        'Client validatie regelversie gepubliceerd.',
                    ),
            ),
        };

        reloadSelectedVersion();
    } catch (error) {
        const message =
            error?.response?.data?.errors?.version_id?.[0] ||
            error?.response?.data?.message ||
            t(
                'client_rules.feedback.publish_failed',
                'Publiceren van de versie is mislukt.',
            );

        feedback.value = {
            type: 'danger',
            message: String(message),
        };
    } finally {
        publishing.value = false;
    }
}
</script>

<style scoped></style>
