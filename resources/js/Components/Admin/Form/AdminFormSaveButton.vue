<template>
    <Button
        :type="type"
        :form="form"
        variant="outline"
        :disabled="disabled || processing"
        class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
    >
        <span
            v-if="processing"
            class="mdi mdi-loading animate-spin text-base text-green-700"
            aria-hidden="true"
        />
        <span
            v-else
            class="mdi mdi-content-save text-base"
            :class="dirty ? 'text-red-600' : 'text-green-700'"
            aria-hidden="true"
        />
        {{ label || commonT('actions.save', 'Bewaren') }}
    </Button>
</template>

<script setup>
import { Button } from '@/components/ui/button';
import { useAdminTranslations } from '@/composables/useAdminTranslations';

defineProps({
    dirty: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    label: { type: String, default: '' },
    type: { type: String, default: 'submit' },
    form: { type: String, default: undefined },
});

const { t: commonT } = useAdminTranslations('admin_common_ui');
</script>
