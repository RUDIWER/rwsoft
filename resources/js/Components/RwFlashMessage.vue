<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    type: {
        type: String,
        default: 'info',
    },
    message: {
        type: String,
        default: '',
    },
    details: {
        type: Array,
        default: () => [],
    },
    icon: {
        type: String,
        default: '',
    },
    refreshKey: {
        type: [String, Number],
        default: null,
    },
});

const emit = defineEmits(['select']);

const page = usePage();

const normalizedType = computed(() => {
    if (['error', 'danger'].includes(props.type)) {
        return 'danger';
    }

    if (['warning', 'alert'].includes(props.type)) {
        return 'alert';
    }

    if (props.type === 'success') {
        return 'success';
    }

    return 'info';
});

const isVisible = ref(Boolean(props.message));
let dismissTimer = null;

const styles = computed(() => {
    if (normalizedType.value === 'success') {
        return {
            wrapper: 'border-emerald-200 bg-emerald-50 text-emerald-800',
            icon: 'mdi-check-circle',
        };
    }

    if (normalizedType.value === 'danger') {
        return {
            wrapper: 'border-red-200 bg-red-50 text-red-800',
            icon: 'mdi-alert-circle',
        };
    }

    if (normalizedType.value === 'alert') {
        return {
            wrapper: 'border-amber-200 bg-amber-50 text-amber-800',
            icon: 'mdi-alert-outline',
        };
    }

    return {
        wrapper: 'border-sky-200 bg-sky-50 text-sky-800',
        icon: 'mdi-information-outline',
    };
});

const resolvedIcon = computed(() => props.icon || styles.value.icon);
const resolvedRefreshKey = computed(
    () => props.refreshKey ?? page.props?.flash?._id ?? null,
);
const detailsRefreshKey = computed(() =>
    props.details
        .map(
            (detail) =>
                `${detail?.name || ''}:${detail?.error || ''}:${detail?.message || ''}`,
        )
        .join('|'),
);

function clearDismissTimer() {
    if (dismissTimer) {
        window.clearTimeout(dismissTimer);
        dismissTimer = null;
    }
}

function startDismissTimer() {
    clearDismissTimer();

    if (
        !props.message ||
        props.details.length > 0 ||
        normalizedType.value !== 'success'
    ) {
        return;
    }

    dismissTimer = window.setTimeout(() => {
        isVisible.value = false;
    }, 3000);
}

function dismiss() {
    clearDismissTimer();
    isVisible.value = false;
}

watch(
    () => [
        props.message,
        props.type,
        resolvedRefreshKey.value,
        detailsRefreshKey.value,
    ],
    () => {
        isVisible.value = Boolean(props.message);
        startDismissTimer();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    clearDismissTimer();
});
</script>

<template>
    <div
        v-if="message && isVisible"
        class="flex items-start gap-2 rounded-md border px-3 py-2 text-sm"
        :class="styles.wrapper"
    >
        <i class="mdi text-lg leading-none" :class="resolvedIcon" />
        <div class="min-w-0 flex-1">
            <div>{{ message }}</div>
            <ul v-if="details.length > 0" class="mt-2 grid gap-1">
                <li
                    v-for="detail in details"
                    :key="detail.name || detail.label || detail.message"
                >
                    <button
                        v-if="detail.elementId || detail.tab"
                        type="button"
                        class="decoration-current/40 text-left underline underline-offset-2 hover:text-red-700"
                        @click="emit('select', detail)"
                    >
                        {{
                            detail.message || `${detail.label}: ${detail.error}`
                        }}
                    </button>
                    <span v-else>{{
                        detail.message || `${detail.label}: ${detail.error}`
                    }}</span>
                </li>
            </ul>
        </div>
        <button
            type="button"
            class="text-current/70 inline-flex h-6 w-6 items-center justify-center rounded transition hover:bg-black/5 hover:text-current"
            @click="dismiss"
        >
            <i class="mdi mdi-close text-base leading-none" />
        </button>
    </div>
</template>
