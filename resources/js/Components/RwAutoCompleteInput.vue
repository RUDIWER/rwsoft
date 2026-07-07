<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean, Object, Array],
        default: null,
    },
    items: {
        type: Array,
        default: () => [],
    },
    itemTitle: {
        type: [String, Function],
        default: 'title',
    },
    itemValue: {
        type: [String, Function],
        default: 'value',
    },
    searchFields: {
        type: Array,
        default: () => [],
    },
    displayValue: {
        type: [String, Number, Array],
        default: null,
    },
    allowCustom: {
        type: Boolean,
        default: false,
    },
    customTrim: {
        type: Boolean,
        default: true,
    },
    customMinLength: {
        type: Number,
        default: 0,
    },
    id: {
        type: String,
        default: '',
    },
    name: {
        type: String,
        default: '',
    },
    ariaLabel: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '',
    },
    dataCreateField: {
        type: String,
        default: '',
    },
    invalid: {
        type: Boolean,
        default: false,
    },
    errorMessage: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    requiredMissing: {
        type: Boolean,
        default: false,
    },
    requiredHighlightColor: {
        type: String,
        default: '#fefce8',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    multiple: {
        type: Boolean,
        default: false,
    },
    showCheckboxes: {
        type: Boolean,
        default: false,
    },
    selectionChips: {
        type: Boolean,
        default: true,
    },
    maxSelectionChips: {
        type: Number,
        default: 3,
    },
    closeOnSelect: {
        type: Boolean,
        default: true,
    },
    messages: {
        type: Object,
        default: () => ({}),
    },
    size: {
        type: String,
        default: 'default',
    },
});

const emit = defineEmits([
    'update:modelValue',
    'keydown',
    'blur',
    'focus',
    'resolve',
    'search',
]);

const inputRef = ref(null);
const rootRef = ref(null);
const menuRef = ref(null);
const menuOpen = ref(false);
const hasFocus = ref(false);
const searchTerm = ref('');
const isFilterActive = ref(false);
const highlightedIndex = ref(-1);
const openAbove = ref(false);
const menuMaxHeight = ref(224);
const isPointerDownInMenu = ref(false);
const blurTimeoutId = ref(null);
const errorTooltipRef = ref(null);
const errorTooltipVisible = ref(false);
const errorTooltipOpenAbove = ref(false);
const errorTooltipStyle = ref({
    left: '0px',
    top: '0px',
    maxWidth: '280px',
    transform: 'none',
});
const menuStyle = ref({
    left: '0px',
    top: '0px',
    width: '0px',
    maxHeight: '224px',
    transform: 'none',
});

function getNestedTranslation(source, key) {
    if (!source || typeof source !== 'object') {
        return null;
    }

    return String(key || '')
        .split('.')
        .filter((part) => part !== '')
        .reduce((carry, part) => {
            if (!carry || typeof carry !== 'object') {
                return null;
            }

            if (!Object.prototype.hasOwnProperty.call(carry, part)) {
                return null;
            }

            return carry[part];
        }, source);
}

function t(key, fallback = '', replacements = {}) {
    const translated = getNestedTranslation(props.messages, key);
    const resolved =
        typeof translated === 'string' ? translated : fallback || key;

    return Object.entries(replacements).reduce(
        (carry, [token, replacement]) => {
            return carry.replaceAll(`:${token}`, String(replacement ?? ''));
        },
        resolved,
    );
}

function resolveItemValue(item) {
    if (typeof props.itemValue === 'function') {
        return props.itemValue(item);
    }

    if (item !== null && typeof item === 'object') {
        return item?.[props.itemValue];
    }

    return item;
}

function resolveItemTitle(item) {
    if (typeof props.itemTitle === 'function') {
        const title = props.itemTitle(item);
        return title === undefined || title === null ? '' : String(title);
    }

    if (item !== null && typeof item === 'object') {
        const title = item?.[props.itemTitle];
        return title === undefined || title === null ? '' : String(title);
    }

    return item === undefined || item === null ? '' : String(item);
}

function normalizedSearchText(value) {
    return String(value ?? '').toLowerCase();
}

function normalizeMultipleValues(value) {
    if (Array.isArray(value)) {
        return [...value];
    }

    if (value === null || value === undefined || value === '') {
        return [];
    }

    return [value];
}

function includesByLooseValue(haystack, needle) {
    return haystack.some((entry) => String(entry) === String(needle));
}

function removeByLooseValue(haystack, needle) {
    return haystack.filter((entry) => String(entry) !== String(needle));
}

const selectedValues = computed(() => {
    if (!props.multiple) {
        return [];
    }

    return normalizeMultipleValues(props.modelValue);
});

const selectedItem = computed(() => {
    if (props.multiple) {
        return null;
    }

    return (
        props.items.find(
            (item) =>
                String(resolveItemValue(item)) === String(props.modelValue),
        ) ?? null
    );
});

const selectedItems = computed(() => {
    if (!props.multiple) {
        return [];
    }

    return props.items.filter((item) =>
        includesByLooseValue(selectedValues.value, resolveItemValue(item)),
    );
});

const selectedLabel = computed(() => {
    if (!selectedItem.value) {
        if (
            props.allowCustom &&
            typeof props.modelValue === 'string' &&
            props.modelValue !== null &&
            props.modelValue !== undefined
        ) {
            return String(props.modelValue);
        }

        return '';
    }

    return resolveItemTitle(selectedItem.value);
});

const resolvedDisplayValue = computed(() => {
    if (props.multiple) {
        return '';
    }

    if (props.displayValue !== null && props.displayValue !== undefined) {
        return String(props.displayValue);
    }

    return selectedLabel.value;
});

const filteredItems = computed(() => {
    const term = isFilterActive.value
        ? normalizedSearchText(searchTerm.value).trim()
        : '';

    if (!term) {
        return props.items;
    }

    return props.items.filter((item) => {
        const valuesToSearch = [resolveItemTitle(item)];

        if (item && typeof item === 'object') {
            props.searchFields.forEach((field) => {
                valuesToSearch.push(item?.[field]);
            });
        }

        return valuesToSearch.some((candidate) =>
            normalizedSearchText(candidate).includes(term),
        );
    });
});

const inputValue = computed(() => {
    if (props.multiple) {
        return searchTerm.value;
    }

    return hasFocus.value && isFilterActive.value
        ? searchTerm.value
        : resolvedDisplayValue.value;
});

const normalizedSelectionChipLimit = computed(() => {
    const raw = Number(props.maxSelectionChips || 0);

    if (!Number.isFinite(raw) || raw <= 0) {
        return 3;
    }

    return Math.max(1, Math.floor(raw));
});

const visibleSelectionChips = computed(() => {
    if (!props.multiple || props.selectionChips !== true) {
        return [];
    }

    return selectedItems.value.slice(0, normalizedSelectionChipLimit.value);
});

const hiddenSelectionChipCount = computed(() => {
    if (!props.multiple || props.selectionChips !== true) {
        return 0;
    }

    return Math.max(
        0,
        selectedItems.value.length - normalizedSelectionChipLimit.value,
    );
});

const inputStyle = computed(() => {
    if (props.invalid) {
        return {
            borderColor: 'rgb(239 68 68)',
            boxShadow: '0 0 0 1px rgba(239, 68, 68, 0.2)',
        };
    }

    if (props.disabled) {
        return {
            backgroundColor: 'rgb(241 245 249)',
        };
    }

    if (props.required || props.requiredMissing) {
        return {
            backgroundColor: props.requiredHighlightColor,
        };
    }

    return null;
});

const controlHeightClass = computed(() => {
    return props.size === 'compact' ? 'h-8' : 'h-9';
});

const controlMinHeightClass = computed(() => {
    return props.size === 'compact' ? 'min-h-8' : 'min-h-9';
});

const controlTextClass = computed(() => {
    return props.size === 'compact' ? 'text-xs' : 'text-sm';
});

const toggleIconClass = computed(() => {
    return props.size === 'compact' ? 'text-sm' : 'text-base';
});

const inputClass = computed(() => {
    return props.invalid
        ? `block ${controlHeightClass.value} w-full rounded-md border border-red-500 bg-transparent px-3 pr-8 ${controlTextClass.value} shadow-none transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50`
        : `block ${controlHeightClass.value} w-full rounded-md border border-input bg-transparent px-3 pr-8 ${controlTextClass.value} shadow-none transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50`;
});

const multipleFieldClass = computed(() => {
    return props.invalid
        ? `${controlMinHeightClass.value} w-full rounded-md border border-red-500 bg-transparent px-3 py-1 pr-8 ${controlTextClass.value} shadow-none transition-colors focus-within:outline-none focus-within:ring-1 focus-within:ring-red-500`
        : `${controlMinHeightClass.value} w-full rounded-md border border-input bg-transparent px-3 py-1 pr-8 ${controlTextClass.value} shadow-none transition-colors focus-within:outline-none focus-within:ring-1 focus-within:ring-blue-500`;
});

const multipleInputClass = computed(() => {
    return `basis-full w-full min-w-0 flex-none appearance-none border-0 bg-transparent p-0 ${controlTextClass.value} leading-6 text-slate-700 shadow-none outline-none ring-0 focus:border-0 focus:outline-none focus:ring-0 focus-visible:outline-none focus-visible:ring-0 disabled:cursor-not-allowed disabled:text-slate-400 caret-slate-500`;
});

const multipleInputStyle = computed(() => {
    return {
        border: '0',
        outline: 'none',
        boxShadow: 'none',
        background: 'transparent',
    };
});

const toggleButtonClass = computed(() => {
    return `absolute right-2 top-1/2 z-10 inline-flex h-5 w-5 -translate-y-1/2 cursor-pointer items-center justify-center text-slate-500 transition-colors hover:text-slate-700 disabled:cursor-not-allowed disabled:hover:text-slate-500`;
});

const hasErrorTooltip = computed(() => {
    return (
        props.invalid === true &&
        typeof props.errorMessage === 'string' &&
        props.errorMessage.trim() !== ''
    );
});

function isSingleSelected(item) {
    if (props.multiple) {
        return false;
    }

    return String(resolveItemValue(item)) === String(props.modelValue ?? '');
}

function optionSlotProps(item, index) {
    const value = resolveItemValue(item);
    const title = resolveItemTitle(item);
    const selected = props.multiple
        ? isItemSelected(item)
        : isSingleSelected(item);

    return {
        item,
        index,
        title,
        value,
        selected,
        highlighted: index === highlightedIndex.value,
        multiple: props.multiple,
        checked: props.multiple ? selected : false,
    };
}

function selectionSlotProps(item) {
    if (!item) {
        return null;
    }

    return {
        item,
        title: resolveItemTitle(item),
        value: resolveItemValue(item),
    };
}

function syncHighlightedIndex(items = filteredItems.value) {
    if (items.length === 0) {
        highlightedIndex.value = -1;
        return;
    }

    if (
        props.allowCustom &&
        !props.multiple &&
        isFilterActive.value &&
        normalizeCustomInput(searchTerm.value) !== ''
    ) {
        highlightedIndex.value = -1;
        return;
    }

    if (!props.multiple) {
        const selectedIndex = items.findIndex((item) => isSingleSelected(item));

        if (selectedIndex >= 0) {
            highlightedIndex.value = selectedIndex;
            return;
        }
    }

    if (highlightedIndex.value < 0 || highlightedIndex.value >= items.length) {
        highlightedIndex.value = 0;
    }
}

function optionRowClass(item, index) {
    const isSelected = props.multiple
        ? isItemSelected(item)
        : isSingleSelected(item);
    const isHighlighted = index === highlightedIndex.value;

    if (isHighlighted && isSelected) {
        return 'bg-blue-100 text-slate-900';
    }

    if (isHighlighted) {
        return 'bg-slate-100 text-slate-900';
    }

    if (isSelected) {
        return 'bg-blue-50 text-slate-900 hover:bg-blue-100';
    }

    return 'hover:bg-slate-100';
}

function clearBlurTimeout() {
    if (blurTimeoutId.value === null) {
        return;
    }

    globalThis.clearTimeout(blurTimeoutId.value);
    blurTimeoutId.value = null;
}

function handleMenuPointerDown() {
    isPointerDownInMenu.value = true;
}

function handleMenuPointerUp() {
    requestAnimationFrame(() => {
        isPointerDownInMenu.value = false;
    });
}

function finalizeBlur(event) {
    const normalizedSearch = normalizeCustomInput(searchTerm.value);
    const normalizedSelected = normalizeCustomInput(selectedLabel.value);

    if (
        !props.multiple &&
        props.allowCustom &&
        normalizedSearch !== '' &&
        normalizedSearch !== normalizedSelected
    ) {
        commitCustom(searchTerm.value);
    }

    hasFocus.value = false;
    searchTerm.value = '';
    isFilterActive.value = false;
    closeMenu();
    emit('blur', event);

    nextTick(() => {
        requestAnimationFrame(() => {
            if (hasErrorTooltip.value) {
                showErrorTooltip();
                return;
            }

            hideErrorTooltip();
        });
    });
}

watch(filteredItems, (items) => {
    if (!menuOpen.value) {
        highlightedIndex.value = -1;
        return;
    }

    if (items.length === 0) {
        highlightedIndex.value = -1;
        return;
    }

    syncHighlightedIndex(items);
});

watch(
    () => props.modelValue,
    () => {
        if (!hasFocus.value) {
            searchTerm.value = '';
            isFilterActive.value = false;
        }
    },
);

function openMenu() {
    if (props.disabled) {
        return;
    }

    if (!isFilterActive.value) {
        searchTerm.value = '';
    }

    menuOpen.value = true;
    syncHighlightedIndex();

    nextTick(() => {
        requestAnimationFrame(() => {
            updateMenuPlacement();
        });
    });
}

function closeMenu() {
    menuOpen.value = false;
    highlightedIndex.value = -1;
    isPointerDownInMenu.value = false;
}

function updateMenuPlacement() {
    if (!menuOpen.value || typeof window === 'undefined') {
        return;
    }

    const rect = rootRef.value?.getBoundingClientRect?.();

    if (!rect) {
        return;
    }

    const viewportHeight =
        window.innerHeight || document.documentElement?.clientHeight || 0;
    const viewportWidth =
        window.innerWidth || document.documentElement?.clientWidth || 0;
    const gap = 8;
    const spaceBelow = Math.max(0, viewportHeight - rect.bottom - gap);
    const spaceAbove = Math.max(0, rect.top - gap);
    const preferredHeight = Math.min(260, menuRef.value?.scrollHeight ?? 224);
    const minimumReadableHeight = 120;

    const threshold = Math.min(180, preferredHeight);
    const wouldOverflowBelow =
        rect.bottom + preferredHeight > viewportHeight - gap;
    const targetVisibleHeight = Math.max(
        minimumReadableHeight,
        Math.min(preferredHeight, 220),
    );
    const enoughSpaceAbove = spaceAbove >= targetVisibleHeight;
    const enoughSpaceBelow = spaceBelow >= targetVisibleHeight;
    const preferAboveByPosition =
        rect.top > viewportHeight * 0.6 && spaceAbove > spaceBelow;

    openAbove.value =
        (!enoughSpaceBelow && spaceAbove > 0) ||
        (wouldOverflowBelow && spaceAbove > 0) ||
        (preferAboveByPosition && enoughSpaceAbove) ||
        (spaceBelow < threshold && spaceAbove > spaceBelow) ||
        (spaceBelow <= 0 && spaceAbove > 0);

    const available = openAbove.value ? spaceAbove : spaceBelow;

    menuMaxHeight.value = Math.max(
        minimumReadableHeight,
        Math.min(Math.max(available, minimumReadableHeight), preferredHeight),
    );

    const width = Math.max(220, Math.round(rect.width));
    const left = Math.max(8, Math.min(rect.left, viewportWidth - width - 8));
    const top = openAbove.value
        ? Math.max(8, rect.top - gap)
        : Math.min(viewportHeight - 8, rect.bottom + gap);

    menuStyle.value = {
        left: `${Math.round(left)}px`,
        top: `${Math.round(top)}px`,
        width: `${Math.round(width)}px`,
        maxHeight: `${Math.round(menuMaxHeight.value)}px`,
        transform: openAbove.value ? 'translateY(-100%)' : 'none',
    };
}

function onViewportUpdate() {
    updateMenuPlacement();

    if (errorTooltipVisible.value) {
        updateErrorTooltipPosition();
    }
}

function updateErrorTooltipPosition() {
    if (!errorTooltipVisible.value || typeof window === 'undefined') {
        return;
    }

    const rect = inputRef.value?.getBoundingClientRect?.();

    if (!rect) {
        return;
    }

    const viewportHeight =
        window.innerHeight || document.documentElement?.clientHeight || 0;
    const viewportWidth =
        window.innerWidth || document.documentElement?.clientWidth || 0;
    const gap = 8;
    const tooltipHeight = Math.max(
        36,
        Number(errorTooltipRef.value?.offsetHeight || 44),
    );
    const tooltipWidth = Math.max(
        180,
        Math.min(320, Number(errorTooltipRef.value?.offsetWidth || 240)),
    );

    const spaceBelow = Math.max(0, viewportHeight - rect.bottom - gap);
    const spaceAbove = Math.max(0, rect.top - gap);

    errorTooltipOpenAbove.value =
        (spaceBelow < tooltipHeight + 10 && spaceAbove > 0) ||
        (rect.top > viewportHeight * 0.6 && spaceAbove > spaceBelow);

    const left = Math.max(
        8,
        Math.min(
            rect.left + rect.width / 2 - tooltipWidth / 2,
            viewportWidth - tooltipWidth - 8,
        ),
    );
    const top = errorTooltipOpenAbove.value
        ? Math.max(8, rect.top - gap)
        : Math.min(viewportHeight - 8, rect.bottom + gap);

    errorTooltipStyle.value = {
        left: `${Math.round(left)}px`,
        top: `${Math.round(top)}px`,
        maxWidth: `${Math.round(tooltipWidth)}px`,
        transform: errorTooltipOpenAbove.value ? 'translateY(-100%)' : 'none',
    };
}

function showErrorTooltip() {
    if (!hasErrorTooltip.value) {
        return;
    }

    errorTooltipVisible.value = true;

    nextTick(() => {
        requestAnimationFrame(() => {
            updateErrorTooltipPosition();
        });
    });
}

function hideErrorTooltip() {
    errorTooltipVisible.value = false;
}

function handleRootMouseEnter() {
    showErrorTooltip();
}

function handleRootMouseLeave() {
    hideErrorTooltip();
}

function commitItem(item) {
    if (props.multiple) {
        toggleItemSelection(item);
        return;
    }

    const value = resolveItemValue(item);
    const label = resolveItemTitle(item);
    emit('update:modelValue', value);
    emit('resolve', {
        kind: 'item',
        value,
        label,
        item,
    });
    searchTerm.value = '';
    isFilterActive.value = false;
    closeMenu();
}

function isItemSelected(item) {
    if (!props.multiple) {
        return false;
    }

    return includesByLooseValue(selectedValues.value, resolveItemValue(item));
}

function emitMultipleSelection(nextValues, item = null) {
    const nextItems = props.items.filter((entry) =>
        includesByLooseValue(nextValues, resolveItemValue(entry)),
    );

    emit('update:modelValue', nextValues);
    emit('resolve', {
        kind: 'multiple',
        values: nextValues,
        items: nextItems,
        item,
    });
}

function toggleItemSelection(item) {
    const value = resolveItemValue(item);
    const current = normalizeMultipleValues(props.modelValue);
    const alreadySelected = includesByLooseValue(current, value);
    const nextValues = alreadySelected
        ? removeByLooseValue(current, value)
        : [...current, value];

    emitMultipleSelection(nextValues, item);

    if (props.closeOnSelect) {
        closeMenu();
    } else {
        nextTick(() => {
            requestAnimationFrame(() => {
                updateMenuPlacement();
            });
        });
    }
}

function removeSelectedValue(value) {
    if (!props.multiple) {
        return;
    }

    const current = normalizeMultipleValues(props.modelValue);
    const nextValues = removeByLooseValue(current, value);

    emitMultipleSelection(nextValues);
}

function normalizeCustomInput(value) {
    const raw = value === undefined || value === null ? '' : String(value);

    if (props.customTrim) {
        return raw.trim();
    }

    return raw;
}

function commitCustom(input) {
    if (!props.allowCustom || props.multiple) {
        return false;
    }

    const normalized = normalizeCustomInput(input);

    if (normalized.length < Math.max(0, Number(props.customMinLength || 0))) {
        return false;
    }

    if (normalized === '') {
        return false;
    }

    emit('update:modelValue', normalized);
    emit('resolve', {
        kind: 'custom',
        value: normalized,
        label: normalized,
        input: normalized,
    });
    searchTerm.value = '';
    isFilterActive.value = false;
    closeMenu();

    return true;
}

function moveHighlight(direction) {
    const items = filteredItems.value;

    if (items.length === 0) {
        highlightedIndex.value = -1;
        return;
    }

    if (highlightedIndex.value < 0) {
        highlightedIndex.value = direction > 0 ? 0 : items.length - 1;
        return;
    }

    const nextIndex = highlightedIndex.value + direction;

    if (nextIndex < 0) {
        highlightedIndex.value = items.length - 1;
        return;
    }

    if (nextIndex >= items.length) {
        highlightedIndex.value = 0;
        return;
    }

    highlightedIndex.value = nextIndex;
}

function handleFocus(event) {
    if (props.disabled) {
        return;
    }

    hasFocus.value = true;
    searchTerm.value = '';
    isFilterActive.value = false;
    emit('focus', event);
    showErrorTooltip();

    nextTick(() => {
        if (typeof inputRef.value?.select === 'function') {
            inputRef.value.select();
        }
    });
}

function handleInputClick() {
    if (props.disabled) {
        return;
    }

    openMenu();
}

function handleMultipleFieldClick() {
    if (props.disabled) {
        return;
    }

    focus();
    openMenu();
}

function handleBlur(event) {
    if (isPointerDownInMenu.value) {
        clearBlurTimeout();
        blurTimeoutId.value = window.setTimeout(() => {
            blurTimeoutId.value = null;

            if (document.activeElement === inputRef.value) {
                return;
            }

            finalizeBlur(event);
        }, 0);
        return;
    }

    finalizeBlur(event);
}

function handleInput(event) {
    searchTerm.value = event.target.value;
    isFilterActive.value = true;
    emit('search', searchTerm.value);

    if (props.allowCustom && !props.multiple) {
        highlightedIndex.value = -1;
    }

    openMenu();

    nextTick(() => {
        requestAnimationFrame(() => {
            updateMenuPlacement();
        });
    });
}

function handleKeydown(event) {
    if (props.disabled) {
        emit('keydown', event);
        return;
    }

    if (event.altKey && event.key === 'ArrowDown') {
        event.preventDefault();
        openMenu();
        emit('keydown', event);
        return;
    }

    if (event.altKey && event.key === 'ArrowUp') {
        event.preventDefault();

        if (menuOpen.value) {
            closeMenu();
        }

        emit('keydown', event);
        return;
    }

    if (event.key === 'ArrowDown') {
        if (menuOpen.value) {
            event.preventDefault();
            moveHighlight(1);
        }

        emit('keydown', event);
        return;
    }

    if (event.key === 'ArrowUp') {
        if (menuOpen.value) {
            event.preventDefault();
            moveHighlight(-1);
        }

        emit('keydown', event);
        return;
    }

    if (event.key === 'Enter') {
        if (menuOpen.value) {
            event.preventDefault();

            if (
                props.allowCustom &&
                !props.multiple &&
                searchTerm.value !== '' &&
                highlightedIndex.value < 0
            ) {
                commitCustom(searchTerm.value);
                emit('keydown', event);
                return;
            }

            const item =
                filteredItems.value[highlightedIndex.value] ??
                filteredItems.value[0] ??
                null;

            if (item) {
                commitItem(item);
            }
        }

        emit('keydown', event);
        return;
    }

    if (event.key === 'Escape') {
        if (menuOpen.value) {
            event.preventDefault();
            searchTerm.value = '';
            closeMenu();
            emit('keydown', event);
            return;
        }

        emit('keydown', event);
        return;
    }

    if (event.key === 'Tab') {
        if (menuOpen.value) {
            if (
                props.allowCustom &&
                !props.multiple &&
                searchTerm.value !== '' &&
                highlightedIndex.value < 0
            ) {
                commitCustom(searchTerm.value);
                emit('keydown', event);
                return;
            }

            const item = filteredItems.value[highlightedIndex.value] ?? null;

            if (item) {
                commitItem(item);
            }
        }

        emit('keydown', event);
        return;
    }

    emit('keydown', event);
}

function toggleMenu() {
    if (menuOpen.value) {
        closeMenu();
        return;
    }

    if (!hasFocus.value && typeof inputRef.value?.focus === 'function') {
        inputRef.value.focus();
    }

    openMenu();
}

watch(menuOpen, (open) => {
    if (typeof window === 'undefined') {
        return;
    }

    if (!open) {
        window.removeEventListener('resize', onViewportUpdate);
        window.removeEventListener('scroll', onViewportUpdate, true);
        return;
    }

    window.addEventListener('resize', onViewportUpdate);
    window.addEventListener('scroll', onViewportUpdate, true);

    nextTick(() => {
        requestAnimationFrame(() => {
            updateMenuPlacement();
        });
    });
});

watch(hasErrorTooltip, (enabled) => {
    if (!enabled) {
        hideErrorTooltip();
        return;
    }

    showErrorTooltip();
});

watch(
    () => props.invalid,
    () => {
        if (hasErrorTooltip.value) {
            showErrorTooltip();
            return;
        }

        hideErrorTooltip();
    },
);

watch(errorTooltipVisible, (visible) => {
    if (typeof window === 'undefined') {
        return;
    }

    if (!visible) {
        if (!menuOpen.value) {
            window.removeEventListener('resize', onViewportUpdate);
            window.removeEventListener('scroll', onViewportUpdate, true);
        }

        return;
    }

    window.addEventListener('resize', onViewportUpdate);
    window.addEventListener('scroll', onViewportUpdate, true);

    nextTick(() => {
        requestAnimationFrame(() => {
            updateErrorTooltipPosition();
        });
    });
});

onBeforeUnmount(() => {
    clearBlurTimeout();

    if (typeof window === 'undefined') {
        return;
    }

    window.removeEventListener('resize', onViewportUpdate);
    window.removeEventListener('scroll', onViewportUpdate, true);
});

function focus() {
    inputRef.value?.focus?.();
}

function select() {
    inputRef.value?.select?.();
}

function scrollIntoView(options) {
    inputRef.value?.scrollIntoView?.(options);
}

defineExpose({
    focus,
    select,
    scrollIntoView,
});
</script>

<template>
    <div
        ref="rootRef"
        class="relative w-full min-w-0"
        :style="menuOpen ? { zIndex: 2147482000 } : null"
        @mouseenter="handleRootMouseEnter"
        @mouseleave="handleRootMouseLeave"
    >
        <div
            v-if="multiple"
            :class="multipleFieldClass"
            :style="inputStyle"
            @click="handleMultipleFieldClick"
        >
            <div class="flex w-full flex-wrap items-center gap-1">
                <template v-if="selectionChips && selectedItems.length > 0">
                    <span
                        v-for="(item, index) in visibleSelectionChips"
                        :key="`chip-${resolveItemValue(item)}-${index}`"
                        class="inline-flex max-w-full items-center gap-1 rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 text-xs text-slate-700"
                    >
                        <span class="truncate">{{
                            resolveItemTitle(item)
                        }}</span>
                        <button
                            type="button"
                            class="inline-flex h-4 w-4 items-center justify-center rounded text-slate-500 hover:bg-slate-200"
                            :disabled="disabled"
                            @mousedown.prevent
                            @click.stop="
                                removeSelectedValue(resolveItemValue(item))
                            "
                        >
                            <i class="mdi mdi-close text-[11px] leading-none" />
                        </button>
                    </span>
                    <span
                        v-if="hiddenSelectionChipCount > 0"
                        class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
                    >
                        +{{ hiddenSelectionChipCount }}
                        {{ t('autocomplete.more', 'meer') }}
                    </span>
                </template>

                <input
                    ref="inputRef"
                    :id="id || null"
                    :name="name"
                    :aria-label="ariaLabel"
                    :aria-invalid="invalid ? 'true' : 'false'"
                    :aria-required="required ? 'true' : 'false'"
                    :aria-expanded="menuOpen ? 'true' : 'false'"
                    data-rw-autocomplete-input="true"
                    data-rw-autocomplete-multiple-input="true"
                    :data-create-field="dataCreateField || null"
                    :disabled="disabled"
                    :value="inputValue"
                    type="text"
                    autocomplete="off"
                    :class="multipleInputClass"
                    :style="multipleInputStyle"
                    @focus="handleFocus"
                    @blur="handleBlur"
                    @click="handleInputClick"
                    @input="handleInput"
                    @keydown="handleKeydown"
                />
            </div>
        </div>

        <input
            v-else
            ref="inputRef"
            :id="id || null"
            :name="name"
            :aria-label="ariaLabel"
            :aria-invalid="invalid ? 'true' : 'false'"
            :aria-required="required ? 'true' : 'false'"
            :aria-expanded="menuOpen ? 'true' : 'false'"
            data-rw-autocomplete-input="true"
            :data-create-field="dataCreateField || null"
            :disabled="disabled"
            :placeholder="placeholder"
            :value="inputValue"
            type="text"
            autocomplete="off"
            :class="[
                inputClass,
                $slots.selection && selectedItem && !hasFocus
                    ? 'text-transparent'
                    : '',
            ]"
            :style="inputStyle"
            @focus="handleFocus"
            @blur="handleBlur"
            @click="handleInputClick"
            @input="handleInput"
            @keydown="handleKeydown"
        />

        <div
            v-if="$slots.selection && selectedItem && !multiple && !hasFocus"
            class="pointer-events-none absolute inset-y-0 left-3 right-8 flex items-center text-sm text-slate-700"
        >
            <slot name="selection" v-bind="selectionSlotProps(selectedItem)" />
        </div>

        <button
            type="button"
            :class="toggleButtonClass"
            :disabled="disabled"
            tabindex="-1"
            @mousedown.stop.prevent
            @click.stop="toggleMenu"
        >
            <i
                class="mdi leading-none"
                :class="[
                    toggleIconClass,
                    menuOpen ? 'mdi-chevron-up' : 'mdi-chevron-down',
                ]"
            />
        </button>

        <Teleport to="body">
            <div
                v-if="menuOpen"
                ref="menuRef"
                class="pointer-events-auto fixed overflow-auto rounded-md border border-slate-200 bg-white p-1 shadow-lg"
                data-rw-autocomplete-portal="true"
                :style="{
                    ...menuStyle,
                    zIndex: 2147483000,
                    pointerEvents: 'auto',
                }"
                @pointerdown.stop="handleMenuPointerDown"
                @pointerup.stop="handleMenuPointerUp"
                @pointercancel.stop="handleMenuPointerUp"
                @mousedown.stop.prevent
                @click.stop
            >
                <div
                    v-for="(item, index) in filteredItems"
                    :key="`${resolveItemValue(item)}-${index}`"
                    class="flex w-full items-stretch rounded text-sm text-slate-700 transition-colors"
                    :class="optionRowClass(item, index)"
                    role="option"
                    :aria-selected="
                        multiple
                            ? isItemSelected(item)
                                ? 'true'
                                : 'false'
                            : isSingleSelected(item)
                              ? 'true'
                              : 'false'
                    "
                    @mouseenter="highlightedIndex = index"
                >
                    <button
                        type="button"
                        class="min-w-0 flex-1 cursor-pointer rounded px-2 py-1.5 text-left"
                        @mousedown.stop.prevent="commitItem(item)"
                        @click.stop.prevent
                    >
                        <slot
                            name="option"
                            v-bind="optionSlotProps(item, index)"
                        >
                            <span class="inline-flex w-full items-center gap-2">
                                <i
                                    v-if="multiple && showCheckboxes"
                                    class="mdi text-base leading-none text-slate-600"
                                    :class="
                                        isItemSelected(item)
                                            ? 'mdi-checkbox-marked'
                                            : 'mdi-checkbox-blank-outline'
                                    "
                                />
                                <span>{{ resolveItemTitle(item) }}</span>
                                <i
                                    v-if="!multiple && isSingleSelected(item)"
                                    class="mdi mdi-check ml-auto text-base leading-none text-blue-600"
                                />
                            </span>
                        </slot>
                    </button>

                    <div
                        v-if="$slots['option-action']"
                        class="flex shrink-0 items-center px-1"
                        @mousedown.stop
                        @click.stop
                    >
                        <slot
                            name="option-action"
                            v-bind="optionSlotProps(item, index)"
                        />
                    </div>
                </div>

                <button
                    v-if="
                        allowCustom &&
                        !multiple &&
                        normalizeCustomInput(searchTerm) !== ''
                    "
                    type="button"
                    class="w-full cursor-pointer rounded border border-dashed border-slate-200 px-2 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50"
                    @mousedown.stop.prevent="commitCustom(searchTerm)"
                    @click.stop.prevent
                >
                    {{
                        t(
                            'autocomplete.use_custom_value',
                            'Gebruik vrije waarde:',
                        )
                    }}
                    "{{ normalizeCustomInput(searchTerm) }}"
                </button>

                <p
                    v-if="filteredItems.length === 0"
                    class="px-2 py-1.5 text-xs text-slate-500"
                >
                    {{ t('autocomplete.no_results', 'Geen resultaten') }}
                </p>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="errorTooltipVisible && hasErrorTooltip"
                ref="errorTooltipRef"
                class="rw-error-tooltip fixed"
                :style="{ ...errorTooltipStyle, zIndex: 2147483500 }"
                role="alert"
            >
                {{ errorMessage }}
                <span
                    class="rw-error-tooltip-arrow"
                    :class="
                        errorTooltipOpenAbove
                            ? 'rw-error-tooltip-arrow-top'
                            : 'rw-error-tooltip-arrow-bottom'
                    "
                />
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.rw-error-tooltip {
    position: fixed;
    border-radius: 10px;
    background: #b91c1c;
    color: #fff;
    font-size: 12px;
    line-height: 1.35;
    padding: 8px 10px;
    pointer-events: none;
}

.rw-error-tooltip-arrow {
    position: absolute;
    left: 50%;
    width: 10px;
    height: 10px;
    background: #b91c1c;
    transform: translateX(-50%) rotate(45deg);
}

.rw-error-tooltip-arrow-bottom {
    top: -5px;
}

.rw-error-tooltip-arrow-top {
    bottom: -5px;
}
</style>
