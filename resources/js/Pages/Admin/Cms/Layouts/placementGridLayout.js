const devices = ['desktop', 'tablet', 'mobile'];
const columnCount = 12;

export function normalizePlacementLayoutConfig(
    layoutConfig,
    placement = {},
    fallbackY = 0,
) {
    return devices.reduce((config, device) => {
        const deviceLayout = layoutConfig?.[device] || {};
        const width = Number(
            deviceLayout.w || placement[`${device}_span`] || columnCount,
        );

        config[device] = normalizeLayoutItem({
            x: deviceLayout.x ?? 0,
            y: deviceLayout.y ?? fallbackY,
            w: width,
            h: deviceLayout.h ?? 1,
        });

        return config;
    }, {});
}

export function gridItemForPlacementLayout(
    placement,
    device,
    index,
    { applyAlignment = false } = {},
) {
    const layout = placement.layout_config?.[device] || {};
    const width = layout.w || placement[`${device}_span`] || columnCount;
    const normalized = normalizeLayoutItem({
        x: layout.x ?? 0,
        y: layout.y ?? index,
        w: width,
        h: layout.h ?? 1,
    });
    const alignmentX = applyAlignment
        ? alignmentStartX(normalized.w, placement.style_config, device)
        : null;

    return {
        i: placement.uid,
        ...normalized,
        ...(alignmentX === null ? {} : { x: alignmentX }),
    };
}

export function resolvePlacementLayoutCollisions(
    placements,
    { applyAlignment = false } = {},
) {
    const resolvedPlacements = placements.map((placement, index) => ({
        ...placement,
        layout_config: normalizePlacementLayoutConfig(
            placement.layout_config,
            placement,
            index,
        ),
    }));

    devices.forEach((device) => {
        const placed = [];
        const layoutItems = resolvedPlacements
            .map((placement, index) => ({
                index,
                visible: isPlacementVisibleOnDevice(placement, device),
                layout: gridItemForPlacementLayout(placement, device, index, {
                    applyAlignment,
                }),
            }))
            .filter((item) => item.visible)
            .sort((left, right) => {
                return (
                    left.layout.y - right.layout.y ||
                    left.layout.x - right.layout.x ||
                    left.index - right.index
                );
            });

        layoutItems.forEach(({ index, layout }) => {
            const nextLayout = { ...layout };

            while (
                placed.some((candidate) =>
                    layoutsOverlap(candidate, nextLayout),
                )
            ) {
                nextLayout.y += 1;
            }

            placed.push(nextLayout);
            resolvedPlacements[index] = {
                ...resolvedPlacements[index],
                layout_config: {
                    ...resolvedPlacements[index].layout_config,
                    [device]: {
                        x: nextLayout.x,
                        y: nextLayout.y,
                        w: nextLayout.w,
                        h: nextLayout.h,
                    },
                },
            };
        });
    });

    return resolvedPlacements;
}

function normalizeLayoutItem(layout) {
    const width = clampInteger(layout.w ?? columnCount, 1, columnCount);
    let x = clampInteger(layout.x ?? 0, 0, columnCount - 1);

    if (x + width > columnCount) {
        x = Math.max(0, columnCount - width);
    }

    return {
        x,
        y: Math.max(0, integerWithFallback(layout.y, 0)),
        w: width,
        h: Math.max(1, integerWithFallback(layout.h, 1)),
    };
}

function alignmentStartX(width, styleConfig, device) {
    const alignment = styleConfig?.devices?.[device]?.alignment;

    if (alignment === 'left') {
        return 0;
    }

    if (alignment === 'center') {
        return Math.floor((columnCount - width) / 2);
    }

    if (alignment === 'right') {
        return columnCount - width;
    }

    return null;
}

function isPlacementVisibleOnDevice(placement, device) {
    const key = `visible_${device}`;

    return (
        placement[key] !== false &&
        placement[key] !== 0 &&
        placement[key] !== '0'
    );
}

function layoutsOverlap(left, right) {
    return !(
        left.x + left.w <= right.x ||
        right.x + right.w <= left.x ||
        left.y + left.h <= right.y ||
        right.y + right.h <= left.y
    );
}

function clampInteger(value, min, max) {
    const parsed = Number.parseInt(value, 10);

    return Math.min(max, Math.max(min, Number.isFinite(parsed) ? parsed : min));
}

function integerWithFallback(value, fallback) {
    const parsed = Number.parseInt(value, 10);

    return Number.isFinite(parsed) ? parsed : fallback;
}
