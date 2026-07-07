export const responsiveDevices = ['desktop', 'tablet', 'mobile'];
export const boxGroups = ['padding', 'margin'];
export const boxSides = ['top', 'right', 'bottom', 'left'];
export const boxUnits = ['px', 'rem', 'em', '%', 'vw', 'vh'];

export function normalizeBoxSpacing(box, fallback = null) {
    const input =
        box && typeof box === 'object' && boxHasValues(box)
            ? box
            : fallback || {};

    return responsiveDevices.reduce((deviceConfig, device) => {
        deviceConfig[device] = boxGroups.reduce((groupConfig, group) => {
            const groupInput = input?.[device]?.[group] || {};
            groupConfig[group] = {
                unit: boxUnits.includes(groupInput.unit)
                    ? groupInput.unit
                    : 'rem',
            };

            boxSides.forEach((side) => {
                const value = normalizeBoxValue(groupInput[side]);
                groupConfig[group][side] =
                    group === 'padding' && value !== null
                        ? Math.max(0, value)
                        : value;
                groupConfig[group][`${side}_unit`] = boxUnits.includes(
                    groupInput[`${side}_unit`],
                )
                    ? groupInput[`${side}_unit`]
                    : groupConfig[group].unit;
            });

            return groupConfig;
        }, {});

        return deviceConfig;
    }, {});
}

export function legacySectionSpacingBox(spacing) {
    const values = {
        compact: { desktop: 2, tablet: 1.5, mobile: 1 },
        normal: { desktop: 3, tablet: 2, mobile: 1.5 },
        spacious: { desktop: 5, tablet: 3.5, mobile: 2.5 },
    }[spacing];

    if (!values) {
        return null;
    }

    return responsiveDevices.reduce((box, device) => {
        box[device] = {
            padding: {
                unit: 'rem',
                top_unit: 'rem',
                right_unit: 'rem',
                bottom_unit: 'rem',
                left_unit: 'rem',
                top: values[device],
                right: null,
                bottom: values[device],
                left: null,
            },
            margin: emptyBoxGroup(),
        };

        return box;
    }, {});
}

export function boxHasValues(box) {
    return responsiveDevices.some((device) =>
        boxGroups.some((group) =>
            boxSides.some(
                (side) =>
                    normalizeBoxValue(box?.[device]?.[group]?.[side]) !== null,
            ),
        ),
    );
}

function normalizeBoxValue(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = Number(value);

    return Number.isFinite(number) ? number : null;
}

function emptyBoxGroup(unit = 'rem') {
    return {
        unit,
        top_unit: unit,
        right_unit: unit,
        bottom_unit: unit,
        left_unit: unit,
        top: null,
        right: null,
        bottom: null,
        left: null,
    };
}
