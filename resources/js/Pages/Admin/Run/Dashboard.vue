<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RwFormTemplate from '@/Components/RwFormTemplate.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Head } from '@inertiajs/vue3';

const { t } = useAdminTranslations('admin_common_ui');
</script>

<template>
    <Head :title="t('run.meta_title', 'Run')" />

    <AdminLayout>
        <RwFormTemplate
            :title="t('run.title', 'Run modus')"
            :subtitle="
                t(
                    'run.subtitle',
                    'Hier wordt de actieve applicatie getoond zodra een basisroute of menu is gedefinieerd.',
                )
            "
        >
            <div
                class="rounded-md border border-dashed border-slate-300 bg-white px-4 py-6 text-sm text-slate-500"
            >
                {{
                    t(
                        'run.empty_state',
                        'Er is nog geen runtime startscherm beschikbaar voor deze applicatie.',
                    )
                }}
            </div>
        </RwFormTemplate>
    </AdminLayout>
</template>
