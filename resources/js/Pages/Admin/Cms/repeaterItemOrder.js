export function reorderRepeaterItems(items, fromIndex, toIndex) {
    if (
        !Array.isArray(items) ||
        fromIndex < 0 ||
        toIndex < 0 ||
        fromIndex >= items.length ||
        toIndex >= items.length ||
        fromIndex === toIndex
    ) {
        return items;
    }

    const reorderedItems = [...items];
    const [item] = reorderedItems.splice(fromIndex, 1);
    reorderedItems.splice(toIndex, 0, item);

    return reorderedItems;
}
