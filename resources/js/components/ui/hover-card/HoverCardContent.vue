<script setup>
import { reactiveOmit } from '@vueuse/core';
import {
    HoverCardContent,
    HoverCardPortal,
    useForwardPropsEmits,
} from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
    forceMount: { type: Boolean, required: false },
    side: { type: null, required: false, default: 'bottom' },
    sideOffset: { type: Number, required: false, default: 6 },
    sideFlip: { type: Boolean, required: false },
    align: { type: null, required: false, default: 'start' },
    alignOffset: { type: Number, required: false },
    alignFlip: { type: Boolean, required: false },
    avoidCollisions: { type: Boolean, required: false, default: true },
    collisionBoundary: { type: null, required: false },
    collisionPadding: { type: [Number, Object], required: false, default: 12 },
    arrowPadding: { type: Number, required: false },
    hideShiftedArrow: { type: Boolean, required: false },
    sticky: { type: String, required: false },
    hideWhenDetached: { type: Boolean, required: false, default: true },
    positionStrategy: { type: String, required: false, default: 'fixed' },
    updatePositionStrategy: { type: String, required: false },
    disableUpdateOnLayoutShift: { type: Boolean, required: false },
    prioritizePosition: { type: Boolean, required: false },
    reference: { type: null, required: false },
    asChild: { type: Boolean, required: false },
    as: { type: null, required: false },
    class: {
        type: [Boolean, null, String, Object, Array],
        required: false,
        skipCheck: true,
    },
});
const emits = defineEmits([
    'escapeKeyDown',
    'pointerDownOutside',
    'focusOutside',
    'interactOutside',
]);

const delegatedProps = reactiveOmit(props, 'class');
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <HoverCardPortal>
        <HoverCardContent
            v-bind="forwarded"
            :class="
                cn(
                    'z-[2147483647] w-72 max-w-[calc(100vw-2rem)] rounded-md border border-slate-700 bg-slate-950 p-2 text-left text-[11px] leading-4 text-slate-100 ring-1 ring-slate-800 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
                    props.class,
                )
            "
        >
            <slot />
        </HoverCardContent>
    </HoverCardPortal>
</template>
