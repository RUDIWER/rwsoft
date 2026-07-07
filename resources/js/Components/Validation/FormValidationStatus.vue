<template>
    <span
        v-if="compact"
        class="inline-flex items-center"
        aria-hidden="true"
        :title="label"
    >
        <span
            class="h-2.5 w-2.5 rounded-full ring-2 ring-white/70"
            :class="dotClass"
        ></span>
    </span>
    <button
        v-else
        type="button"
        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium"
        :class="statusClass"
        @click="$emit('click')"
    >
        <span class="h-2.5 w-2.5 rounded-full" :class="dotClass"></span>
        {{ label }}
    </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    errors: { type: Number, default: 0 },
    warnings: { type: Number, default: 0 },
    labels: { type: Object, required: true },
    compact: { type: Boolean, default: false },
});

defineEmits(['click']);

const label = computed(() => {
    if (props.status === 'error') {
        return props.labels.error?.replace(':count', props.errors) ?? `${props.errors} fouten`;
    }

    if (props.status === 'warning') {
        return props.labels.warning?.replace(':count', props.warnings) ?? `${props.warnings} waarschuwingen`;
    }

    return props.labels.success ?? 'Validatie ok';
});

const statusClass = computed(() => ({
    error: 'border-red-200 bg-red-50 text-red-700',
    warning: 'border-orange-200 bg-orange-50 text-orange-700',
    success: 'border-green-200 bg-green-50 text-green-700',
}[props.status] ?? 'border-slate-200 bg-slate-50 text-slate-700'));

const dotClass = computed(() => ({
    error: 'bg-red-600',
    warning: 'bg-orange-500',
    success: 'bg-green-600',
}[props.status] ?? 'bg-slate-500'));
</script>
