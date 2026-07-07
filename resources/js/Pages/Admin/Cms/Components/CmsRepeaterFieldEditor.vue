<template>
    <div class="grid gap-3 md:col-span-2">
        <div class="flex items-center justify-between gap-3">
            <Label>{{ label }}</Label>
            <Button type="button" variant="outline" @click="emit('add')">
                {{ t('components.block_editor.add_item', 'Item toevoegen') }}
            </Button>
        </div>

        <div class="grid gap-3">
            <div
                v-for="(item, itemIndex) in items"
                :key="item.uid"
                :class="[
                    'grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3',
                    dragOverItemUid === item.uid
                        ? 'border-blue-300 bg-blue-50'
                        : '',
                ]"
                @dragover.prevent="onItemDragOver(item)"
                @drop.prevent="onItemDragEnd"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2">
                        <button
                            type="button"
                            draggable="true"
                            class="inline-flex h-8 w-8 cursor-grab items-center justify-center rounded-md border border-slate-300 bg-white text-slate-500 hover:bg-slate-100 active:cursor-grabbing"
                            :aria-label="dragLabel"
                            :title="dragLabel"
                            @dragstart="onItemDragStart(item, $event)"
                            @dragend="onItemDragEnd"
                        >
                            <span aria-hidden="true">::</span>
                        </button>
                        <div class="min-w-0">
                            <span
                                class="text-xs font-medium uppercase tracking-wide text-slate-500"
                            >
                                #{{ itemIndex + 1 }}
                            </span>
                            <p
                                class="truncate text-sm font-semibold text-slate-800"
                            >
                                {{ itemPreviewTitle(item) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="itemIndex === 0"
                            @click="moveItem(itemIndex, itemIndex - 1)"
                        >
                            {{ t('components.block_editor.up', 'Omhoog') }}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="itemIndex === items.length - 1"
                            @click="moveItem(itemIndex, itemIndex + 1)"
                        >
                            {{ t('components.block_editor.down', 'Omlaag') }}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="toggleItem(item)"
                        >
                            {{
                                item.collapsed
                                    ? t(
                                          'components.block_editor.expand',
                                          'Openen',
                                      )
                                    : t(
                                          'components.block_editor.collapse',
                                          'Sluiten',
                                      )
                            }}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="removeItem(itemIndex)"
                        >
                            {{
                                t(
                                    'components.block_editor.delete',
                                    'Verwijderen',
                                )
                            }}
                        </Button>
                    </div>
                </div>

                <div v-if="!item.collapsed" class="grid gap-3">
                    <slot
                        v-for="childField in field.fields || []"
                        :key="childField.name"
                        :item="item"
                        :child-field="childField"
                    />
                </div>
            </div>

            <p
                v-if="items.length === 0"
                class="rounded-md border border-dashed border-slate-300 bg-white p-3 text-sm text-slate-500"
            >
                {{
                    t(
                        'components.block_editor.repeater_empty',
                        'Nog geen items. Voeg een item toe om deze lijst op te bouwen.',
                    )
                }}
            </p>
        </div>
    </div>
</template>

<script setup>
import { reorderRepeaterItems } from '@/Pages/Admin/Cms/repeaterItemOrder';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { computed, ref } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');

const props = defineProps({
    field: {
        type: Object,
        required: true,
    },
    items: {
        type: Array,
        default: () => [],
    },
    label: {
        type: String,
        required: true,
    },
    itemPreviewTitle: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['add', 'update:items']);

const draggedItemUid = ref('');
const dragOverItemUid = ref('');
const dragLabel = computed(() =>
    t('components.block_editor.drag_item', 'Sleep om te verplaatsen'),
);

function moveItem(fromIndex, toIndex) {
    emit('update:items', reorderRepeaterItems(props.items, fromIndex, toIndex));
}

function removeItem(itemIndex) {
    emit(
        'update:items',
        props.items.filter((item, index) => index !== itemIndex),
    );
}

function toggleItem(item) {
    item.collapsed = !item.collapsed;
}

function onItemDragStart(item, event) {
    draggedItemUid.value = item.uid;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', item.uid);
}

function onItemDragOver(targetItem) {
    if (!draggedItemUid.value || draggedItemUid.value === targetItem.uid) {
        return;
    }

    const fromIndex = props.items.findIndex(
        (item) => item.uid === draggedItemUid.value,
    );
    const toIndex = props.items.findIndex(
        (item) => item.uid === targetItem.uid,
    );

    if (fromIndex < 0 || toIndex < 0) {
        return;
    }

    dragOverItemUid.value = targetItem.uid;
    moveItem(fromIndex, toIndex);
}

function onItemDragEnd() {
    draggedItemUid.value = '';
    dragOverItemUid.value = '';
}
</script>
