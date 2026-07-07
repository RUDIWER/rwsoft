<template>
    <div
        v-if="visible"
        class="grid gap-3 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-900"
    >
        <div v-if="title || description">
            <p v-if="title" class="font-semibold">{{ title }}</p>
            <p v-if="description" class="text-red-800">{{ description }}</p>
        </div>
        <ul v-if="errors.length > 0" class="grid gap-1">
            <li v-for="issue in errors" :key="issue.name">
                <button
                    type="button"
                    class="text-left underline decoration-red-300 underline-offset-2 hover:text-red-700"
                    @click="$emit('select', issue)"
                >
                    {{ issue.label }}: {{ issue.error }}
                </button>
            </li>
        </ul>
    </div>
</template>

<script setup>
defineProps({
    visible: { type: Boolean, default: false },
    errors: { type: Array, default: () => [] },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});

defineEmits(['select']);
</script>
