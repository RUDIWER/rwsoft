<template>
    <button
        type="button"
        class="inline-flex h-5 w-5 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
        :title="resolvedTooltip"
        :aria-label="resolvedAriaLabel"
        @click="isOpen = true"
    >
        <i class="mdi mdi-help-circle-outline text-base" />
    </button>

    <Dialog v-model:open="isOpen">
        <RwDialogTemplate
            :title="title"
            :subtitle="subtitle"
            :max-width-class="maxWidthClass"
        >
            <template #back>
                <RwActionButton
                    :label="resolvedBackLabel"
                    icon="mdi mdi-arrow-left-circle"
                    tone="back"
                    @click="closeDialog"
                />
            </template>

            <div class="space-y-3 text-sm text-slate-700">
                <slot />
            </div>
        </RwDialogTemplate>
    </Dialog>
</template>

<script setup>
import RwActionButton from '@/Components/RwActionButton.vue';
import RwDialogTemplate from '@/Components/RwDialogTemplate.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Dialog } from '@/components/ui/dialog';
import { computed, ref } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    maxWidthClass: {
        type: String,
        default: 'sm:max-w-2xl',
    },
    tooltip: {
        type: String,
        default: '',
    },
    ariaLabel: {
        type: String,
        default: '',
    },
    backLabel: {
        type: String,
        default: '',
    },
});

const isOpen = ref(false);
const { t } = useAdminTranslations('admin_common_ui');

const resolvedTooltip = computed(() => {
    return props.tooltip || t('help.show', 'Toon help');
});

const resolvedAriaLabel = computed(() => {
    return props.ariaLabel || t('help.open_dialog', 'Open help dialoog');
});

const resolvedBackLabel = computed(() => {
    return props.backLabel || t('actions.back', 'Terug');
});

function closeDialog() {
    isOpen.value = false;
}
</script>
