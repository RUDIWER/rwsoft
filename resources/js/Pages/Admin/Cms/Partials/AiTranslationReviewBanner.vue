<template>
    <div
        v-if="isVisible"
        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"
    >
        <div class="flex min-w-0 items-start gap-2">
            <span class="mdi mdi-robot-outline mt-0.5 text-lg text-amber-700"></span>
            <div class="grid gap-1">
                <strong>{{ t('ai_review.title', 'AI concept') }}</strong>
                <span>
                    {{
                        t(
                            'ai_review.description',
                            'Deze vertaling is met AI aangemaakt en moet nog manueel nagekeken worden.',
                        )
                    }}
                </span>
            </div>
        </div>
        <Button
            type="button"
            variant="outline"
            size="sm"
            :disabled="processing"
            @click="markReviewed"
        >
            {{ t('ai_review.mark_reviewed', 'Markeer als nagekeken') }}
        </Button>
        <p v-if="errorMessage" class="basis-full text-sm text-red-700">
            {{ errorMessage }}
        </p>
    </div>
</template>

<script setup>
import { Button } from '@/components/ui/button';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { computed, ref } from 'vue';

const props = defineProps({
    type: {
        type: String,
        required: true,
    },
    recordId: {
        type: [Number, String],
        required: true,
    },
    review: {
        type: Object,
        default: () => ({}),
    },
});

const { t } = useAdminTranslations('cms_admin_ui');
const processing = ref(false);
const dismissed = ref(false);
const errorMessage = ref('');
const isVisible = computed(
    () => Boolean(props.review?.is_pending) && !dismissed.value,
);

async function markReviewed() {
    if (processing.value) {
        return;
    }

    processing.value = true;
    errorMessage.value = '';

    try {
        await window.axios.post(route('admin.translations.content.mark-reviewed'), {
            type: props.type,
            id: props.recordId,
        });
        dismissed.value = true;
    } catch (error) {
        errorMessage.value =
            error?.response?.data?.message ||
            t('ai_review.mark_failed', 'Markeren als nagekeken is mislukt.');
    } finally {
        processing.value = false;
    }
}
</script>
