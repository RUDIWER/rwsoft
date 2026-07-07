<template>
    <p v-if="displayText" :class="displayClass">
        {{ displayText }}
    </p>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    message: { type: String, default: '' },
    warning: { type: String, default: '' },
    value: { type: [String, Number, null], default: '' },
    max: { type: [Number, null], default: null },
});

const counter = computed(() => {
    if (!props.max) {
        return '';
    }

    return `${String(props.value ?? '').length} / ${props.max}`;
});

const displayText = computed(() => props.message || props.warning || counter.value);

const displayClass = computed(() => {
    if (props.message) {
        return 'pl-1 text-xs leading-5 text-red-600';
    }

    if (props.warning) {
        return 'pl-1 text-xs leading-5 text-orange-600';
    }

    return String(props.value ?? '').length > Number(props.max)
        ? 'pl-1 text-xs leading-5 text-red-600'
        : 'pl-1 text-xs leading-5 text-slate-500';
});
</script>
