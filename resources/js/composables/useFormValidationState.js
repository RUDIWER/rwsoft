import { computed, nextTick, ref } from 'vue';

export function useFormValidationState(validation, options = {}) {
    const showSummary = ref(false);

    const status = computed(() => {
        if (validation.errors.value.length > 0) {
            return 'error';
        }

        if (validation.warnings.value.length > 0) {
            return 'warning';
        }

        return 'success';
    });

    async function validateBeforeSubmit() {
        if (validation.touchAll()) {
            showSummary.value = false;
            return true;
        }

        showSummary.value = true;
        await nextTick();
        scrollToIssue(validation.errors.value[0]);

        return false;
    }

    function scrollToIssue(issue) {
        if (!issue) {
            return;
        }

        if (issue.tab && typeof options.activateTab === 'function') {
            options.activateTab(issue.tab);
        }

        nextTick(() => {
            const element = document.getElementById(issue.elementId || issue.name);

            if (!element) {
                return;
            }

            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            element.focus?.({ preventScroll: true });
        });
    }

    return {
        showSummary,
        status,
        validateBeforeSubmit,
        scrollToIssue,
    };
}
