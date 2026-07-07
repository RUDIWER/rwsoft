<template>
    <Button
        :type="type"
        :variant="variant"
        :disabled="disabled || loading"
        :class="cn(buttonClass, layoutClass)"
        :data-shortcut-intent="resolvedShortcutIntent || null"
        @click="emit('click', $event)"
    >
        <i class="mdi text-lg leading-none" :class="iconClass" />
        <span :class="contentClass">{{ label }}</span>
    </Button>
</template>

<script setup>
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { computed } from 'vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    icon: {
        type: String,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    variant: {
        type: String,
        default: 'outline',
    },
    type: {
        type: String,
        default: 'button',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    tone: {
        type: String,
        default: 'neutral',
    },
    iconOnly: {
        type: Boolean,
        default: false,
    },
    shortcutIntent: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['click']);

const iconClass = computed(() => {
    if (props.loading) {
        return 'mdi-loading mdi-spin';
    }

    return props.icon;
});

const buttonClass = computed(() => {
    if (props.tone === 'back') {
        return 'border-slate-300 bg-white text-slate-900 shadow-none transition-colors hover:bg-slate-50';
    }

    if (props.tone === 'new') {
        return 'border-slate-300 bg-white text-blue-700 shadow-none transition-colors hover:bg-blue-50';
    }

    if (props.tone === 'save') {
        return 'border-slate-300 bg-white text-emerald-800 shadow-none transition-colors hover:bg-emerald-50';
    }

    if (props.tone === 'delete') {
        return 'border-slate-300 bg-white text-red-700 shadow-none transition-colors hover:bg-red-50';
    }

    if (props.tone === 'warning') {
        return 'border-amber-300 bg-amber-50 text-amber-700 shadow-none transition-colors hover:border-amber-400 hover:bg-amber-100';
    }

    if (props.tone === 'ai') {
        return 'border-violet-300 bg-violet-50 text-violet-700 shadow-none transition-colors hover:border-violet-400 hover:bg-violet-100';
    }

    return 'border-slate-300 bg-white text-slate-700 shadow-none transition-colors hover:bg-slate-50';
});

const contentClass = computed(() => {
    return props.iconOnly ? 'sr-only' : 'hidden sm:inline';
});

const layoutClass = computed(() => {
    return props.iconOnly ? 'h-9 w-9 px-0' : '';
});

const resolvedShortcutIntent = computed(() => {
    const explicit = String(props.shortcutIntent || '')
        .trim()
        .toLowerCase();

    if (explicit !== '') {
        return explicit;
    }

    if (props.disabled || props.loading) {
        return '';
    }

    if (props.tone === 'save') {
        return 'save';
    }

    const label = String(props.label || '')
        .trim()
        .toLowerCase();

    if (label === '') {
        return '';
    }

    if (/\b(bewaar|bewaren|opslaan|save)\b/.test(label)) {
        return 'save';
    }

    if (/\b(submit|verzend|indienen)\b/.test(label)) {
        return 'submit';
    }

    return '';
});
</script>
