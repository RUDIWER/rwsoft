<template>
    <div
        class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 sm:px-5"
    >
        <div class="font-medium text-slate-700">
            {{ commonT('record_meta.id', 'ID') }}:
            <span class="ml-1 text-base font-bold text-slate-950">{{
                id ?? '-'
            }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
            <div class="font-medium text-slate-700">
                {{ commonT('record_meta.updated_at', 'Updated') }}:
                <span class="ml-1 text-base font-bold text-slate-950">{{
                    formatDate(updatedAt)
                }}</span>
            </div>
            <div class="font-medium text-slate-700">
                {{ commonT('record_meta.created_at', 'Created') }}:
                <span class="ml-1 text-base font-bold text-slate-950">{{
                    formatDate(createdAt)
                }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useAdminTranslations } from '@/composables/useAdminTranslations';

defineProps({
    id: { type: Number, default: null },
    createdAt: { type: String, default: null },
    updatedAt: { type: String, default: null },
});
const { t: commonT } = useAdminTranslations('admin_common_ui');

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
}
</script>
