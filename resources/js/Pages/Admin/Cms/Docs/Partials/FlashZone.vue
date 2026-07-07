<template>
    <div
        v-if="pageFlash.message"
        class="shrink-0 border-b border-slate-200 px-4 py-3 sm:px-5"
    >
        <RwFlashMessage :type="pageFlash.type" :message="pageFlash.message" />
    </div>
</template>

<script setup>
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const pageFlash = computed(() => {
    const flash = page.props?.flash || {};

    if (flash.error) {
        return { type: 'danger', message: flash.error };
    }

    if (flash.warning) {
        return { type: 'warning', message: flash.warning };
    }

    if (flash.status) {
        return { type: 'success', message: flash.status };
    }

    return { type: '', message: '' };
});
</script>
