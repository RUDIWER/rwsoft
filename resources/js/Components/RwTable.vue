<script setup>
import RwTable from './RwTable/RwTableCore.vue';
import { computed, useAttrs } from 'vue';

const attrs = useAttrs();

const mergedAttrs = computed(() => {
    const hasInitialHeight =
        Object.prototype.hasOwnProperty.call(attrs, 'initialHeight') ||
        Object.prototype.hasOwnProperty.call(attrs, 'initial-height');

    if (hasInitialHeight) {
        return attrs;
    }

    return {
        ...attrs,
        initialHeight: 'calc(100vh - 420px)',
    };
});
</script>

<template>
    <RwTable v-bind="mergedAttrs" />
</template>
