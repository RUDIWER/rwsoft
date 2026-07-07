<script setup>
import { HoverCardRoot, useForwardPropsEmits } from 'reka-ui';

const props = defineProps({
    defaultOpen: { type: Boolean, required: false },
    open: { type: Boolean, required: false },
    openDelay: { type: Number, required: false, default: 150 },
    closeDelay: { type: Number, required: false, default: 100 },
});
const emits = defineEmits(['update:open']);

const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
    <HoverCardRoot v-bind="forwarded">
        <slot />
    </HoverCardRoot>
</template>
