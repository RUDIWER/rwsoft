<template>
    <Button
        type="button"
        variant="outline"
        size="icon"
        class="text-slate-950 shadow-none hover:bg-slate-50 hover:text-slate-950"
        :aria-label="label || commonT('actions.back', 'Terug')"
        :title="label || commonT('actions.back', 'Terug')"
        @click="handleBackClick"
    >
        <span class="mdi mdi-arrow-left-circle text-lg" aria-hidden="true" />
    </Button>

    <Dialog v-model:open="confirmOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader class="border-b border-slate-200 pb-4">
                <DialogTitle>
                    {{ commonT('dirty_form.title', 'Onbewaarde wijzigingen') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        commonT(
                            'dirty_form.description',
                            'Je hebt wijzigingen die nog niet bewaard zijn.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-5 py-2">
                <p class="text-sm text-slate-600">
                    {{
                        commonT(
                            'dirty_form.question',
                            'Wat wil je met deze wijzigingen doen?',
                        )
                    }}
                </p>

                <DialogFooter
                    class="flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="shadow-none"
                        @click="confirmOpen = false"
                    >
                        {{
                            commonT(
                                'dirty_form.keep_editing',
                                'Verder bewerken',
                            )
                        }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="gap-2 border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                        @click="discardAndLeave"
                    >
                        <span
                            class="mdi mdi-exit-to-app text-base"
                            aria-hidden="true"
                        />
                        {{ commonT('dirty_form.discard', 'Niet bewaren') }}
                    </Button>
                    <Button
                        v-if="showSaveAction"
                        type="button"
                        variant="outline"
                        class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                        :disabled="processing"
                        @click="saveChanges"
                    >
                        <span
                            :class="
                                processing
                                    ? 'mdi mdi-loading animate-spin text-base text-green-700'
                                    : 'mdi mdi-content-save text-base text-red-600'
                            "
                            aria-hidden="true"
                        />
                        {{ commonT('actions.save', 'Bewaren') }}
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>

<script setup>
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    href: { type: String, required: true },
    dirty: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
    label: { type: String, default: '' },
    showSaveAction: { type: Boolean, default: true },
});

const emit = defineEmits(['save']);
const { t: commonT } = useAdminTranslations('admin_common_ui');
const confirmOpen = ref(false);

function handleBackClick() {
    if (!props.dirty) {
        navigateAway();

        return;
    }

    confirmOpen.value = true;
}

function discardAndLeave() {
    confirmOpen.value = false;
    navigateAway();
}

function saveChanges() {
    confirmOpen.value = false;
    emit('save');
}

function navigateAway() {
    router.visit(props.href);
}
</script>
