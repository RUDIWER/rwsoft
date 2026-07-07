<script setup>
import { HoverCardTrigger, useForwardProps } from 'reka-ui';

const props = defineProps({
    asChild: { type: Boolean, required: false },
    as: { type: null, required: false },
});

const forwardedProps = useForwardProps(props);
</script>

<template>
    <HoverCardTrigger v-bind="forwardedProps">
        <slot />
    </HoverCardTrigger>
</template>
