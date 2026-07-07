<script setup>
import {
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';

defineProps({
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
});
</script>

<template>
    <DialogContent
        :disable-outside-pointer-events="false"
        :class="[
            'flex max-h-[calc(100vh-1.5rem)] flex-col gap-0 overflow-hidden p-0 [&>button.absolute]:hidden',
            maxWidthClass,
        ]"
    >
        <div class="px-4 py-4 sm:px-5">
            <DialogTitle class="text-lg font-semibold text-slate-900">
                {{ title }}
            </DialogTitle>
            <DialogDescription
                v-if="subtitle"
                class="mt-1 text-sm text-slate-400"
            >
                {{ subtitle }}
            </DialogDescription>
            <DialogDescription
                v-else
                id="rw-dialog-description-empty"
                class="sr-only"
            >
                Dialoog zonder extra beschrijving.
            </DialogDescription>
        </div>

        <div class="border-t border-slate-200" />

        <div class="flex items-center justify-between gap-2 px-4 py-3 sm:px-5">
            <div class="flex items-center gap-2">
                <slot name="back" />
            </div>
            <div class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <div class="px-4 pb-3 sm:px-5">
                <slot name="flash" />
            </div>

            <div class="px-4 pb-5 sm:px-5">
                <slot />
            </div>
        </div>
    </DialogContent>
</template>
