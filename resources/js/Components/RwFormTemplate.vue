<template>
    <div class="rounded-none border border-slate-200 bg-white">
        <div v-if="showTitle || subtitle" class="px-4 py-4 sm:px-5">
            <h1 v-if="showTitle" class="text-lg font-semibold text-slate-900">
                {{ title }}
            </h1>
            <p v-if="subtitle" class="mt-1 text-sm text-slate-400">
                {{ subtitle }}
            </p>
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

        <div class="px-4 pb-3 sm:px-5">
            <slot name="flash" />
        </div>

        <div class="px-4 pb-5 sm:px-5">
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    showTitle: {
        type: Boolean,
        default: true,
    },
});
</script>
