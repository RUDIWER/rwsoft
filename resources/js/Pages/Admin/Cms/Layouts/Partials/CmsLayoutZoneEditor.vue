<template>
    <div class="grid gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-slate-900">
                    {{ title }}
                </h3>
                <p class="text-sm text-slate-500">
                    {{ description }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div
                    v-if="supportsResponsiveGrid"
                    class="flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2 py-1"
                >
                    <span class="text-xs font-medium text-slate-600">
                        {{
                            t(
                                'layouts.sections.preview_mode_label',
                                'Block view',
                            )
                        }}
                    </span>
                    <button
                        v-for="mode in previewModeOptions"
                        :key="mode.value"
                        type="button"
                        class="rounded px-2 py-1 text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-blue-200"
                        :class="
                            gridPreviewMode === mode.value
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                        "
                        @click="setGridPreviewMode(mode.value)"
                    >
                        {{ mode.label }}
                    </button>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    class="border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                    @click="openNewSectionDialog()"
                >
                    <span
                        class="mdi mdi-plus-circle text-base text-blue-700"
                        aria-hidden="true"
                    />
                    {{ t('layouts.sections.add_section_button', 'Section') }}
                </Button>
            </div>
        </div>

        <div
            v-if="sections.length === 0"
            class="flex items-center gap-2 rounded-md border border-dashed border-slate-300 p-5 text-sm text-slate-500"
        >
            <span class="mdi mdi-emoticon-sad text-base" aria-hidden="true" />
            {{ t('layouts.sections.empty', 'Nog geen secties toegevoegd.') }}
        </div>

        <div
            v-for="(section, sectionIndex) in sections"
            :key="section.uid"
            class="grid gap-3 rounded-xl border border-slate-300 bg-slate-50 p-4 transition"
        >
            <div
                class="grid items-center gap-3 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]"
            >
                <div class="min-w-0">
                    <h4 class="truncate text-base font-semibold text-slate-900">
                        {{ sectionTitle(section) }}
                    </h4>
                </div>
                <div
                    v-if="supportsResponsiveGrid"
                    class="flex flex-wrap justify-start gap-2 md:justify-center"
                >
                    <button
                        v-for="device in responsiveDeviceOptions"
                        :key="device.value"
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-blue-200"
                        :class="
                            activeGridDevice === device.value
                                ? 'border-blue-300 bg-blue-50 text-blue-700'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900'
                        "
                        @click="activeGridDevice = device.value"
                    >
                        {{ device.label }}
                    </button>
                </div>
                <div class="flex flex-wrap gap-2 md:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-8 gap-2 border-blue-200 bg-white px-3 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                        :title="t('layouts.sections.add_block', 'Blok')"
                        :aria-label="t('layouts.sections.add_block', 'Blok')"
                        @click="openBlockPicker(section)"
                    >
                        <span
                            class="mdi mdi-plus-circle text-base"
                            aria-hidden="true"
                        />
                        {{ t('layouts.sections.add_block', 'Blok') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="h-8 w-8 border-slate-300 bg-white text-slate-700 shadow-none hover:bg-slate-100"
                        :title="
                            t(
                                'layouts.sections.settings',
                                'Sectie-instellingen',
                            )
                        "
                        :aria-label="
                            t(
                                'layouts.sections.settings',
                                'Sectie-instellingen',
                            )
                        "
                        @click="openEditSectionDialog(section, sectionIndex)"
                    >
                        <span
                            class="mdi mdi-cog text-base text-orange-500"
                            aria-hidden="true"
                        />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="h-8 w-8 border-slate-300 bg-white text-slate-700 shadow-none hover:bg-slate-100"
                        :disabled="sectionIndex === 0"
                        :title="t('components.block_editor.up', 'Omhoog')"
                        :aria-label="t('components.block_editor.up', 'Omhoog')"
                        @click="moveSection(sectionIndex, -1)"
                    >
                        <span
                            class="mdi mdi-chevron-up text-xl"
                            aria-hidden="true"
                        />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="h-8 w-8 border-slate-300 bg-white text-slate-700 shadow-none hover:bg-slate-100"
                        :disabled="sectionIndex === sections.length - 1"
                        :title="t('components.block_editor.down', 'Omlaag')"
                        :aria-label="
                            t('components.block_editor.down', 'Omlaag')
                        "
                        @click="moveSection(sectionIndex, 1)"
                    >
                        <span
                            class="mdi mdi-chevron-down text-xl"
                            aria-hidden="true"
                        />
                    </Button>
                </div>
            </div>

            <div class="grid gap-3">
                <div v-if="supportsResponsiveGrid" class="grid gap-3">
                    <div
                        v-if="section.placements.length === 0"
                        class="rounded-md bg-white p-4 text-sm text-slate-500"
                    >
                        {{
                            t(
                                'layouts.sections.empty_blocks',
                                'Nog geen blokken in deze sectie.',
                            )
                        }}
                    </div>

                    <div
                        v-else-if="
                            visiblePlacementsForGrid(section).length === 0
                        "
                        class="rounded-md bg-white p-4 text-sm text-slate-500"
                    >
                        {{
                            t(
                                'layouts.sections.no_visible_blocks_on_device',
                                'No blocks are visible on this device.',
                            )
                        }}
                    </div>

                    <GridLayout
                        v-else
                        :key="gridLayoutKey(section)"
                        class="cms-layout-grid-canvas min-h-[50px] rounded-lg bg-white"
                        :layout="gridLayoutForSection(section)"
                        :col-num="12"
                        :row-height="gridRowHeight"
                        :margin="gridMargin"
                        :is-draggable="true"
                        :is-resizable="true"
                        :is-bounded="true"
                        :vertical-compact="false"
                        :restore-on-drag="true"
                        :prevent-collision="false"
                        @layout-updated="updateGridLayout(section, $event)"
                    >
                        <template #item="{ item }">
                            <HoverCard :open-delay="150" :close-delay="100">
                                <HoverCardTrigger as-child>
                                    <div
                                        class="relative grid h-full min-h-0 overflow-hidden rounded-md border border-blue-200 bg-white text-xs text-slate-700 shadow-sm"
                                        @mouseenter="updateHoverCardCursor"
                                        @mousemove="updateHoverCardCursor"
                                    >
                                        <div
                                            v-if="gridPreviewMode === 'compact'"
                                            class="grid content-start gap-1 p-1 pr-8"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-2"
                                            >
                                                <span
                                                    class="min-w-0 truncate text-[10px] font-semibold uppercase leading-3 tracking-wide text-blue-700"
                                                >
                                                    {{
                                                        placeableBlockLabel(
                                                            placementForGridItem(
                                                                section,
                                                                item,
                                                            )?.block || {},
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </div>

                                        <div
                                            v-if="gridPreviewMode !== 'compact'"
                                            class="pointer-events-none h-full min-h-0 overflow-hidden bg-white"
                                        >
                                            <iframe
                                                v-if="
                                                    placementLivePreviewDocument(
                                                        placementForGridItem(
                                                            section,
                                                            item,
                                                        ),
                                                    )
                                                "
                                                :srcdoc="
                                                    placementLivePreviewDocument(
                                                        placementForGridItem(
                                                            section,
                                                            item,
                                                        ),
                                                    )
                                                "
                                                :title="
                                                    blockPreviewTitle(
                                                        placementForGridItem(
                                                            section,
                                                            item,
                                                        ),
                                                    )
                                                "
                                                class="h-full w-full border-0 bg-transparent"
                                                sandbox="allow-same-origin"
                                                :ref="
                                                    (element) =>
                                                        registerPlacementPreviewFrame(
                                                            placementForGridItem(
                                                                section,
                                                                item,
                                                            ),
                                                            element,
                                                        )
                                                "
                                                @load="
                                                    measurePlacementPreview(
                                                        placementForGridItem(
                                                            section,
                                                            item,
                                                        ),
                                                        $event.currentTarget,
                                                    )
                                                "
                                            />
                                            <div
                                                v-else
                                                class="grid h-full place-content-center bg-slate-50 p-2 text-center text-[11px] leading-4 text-slate-500"
                                            >
                                                {{
                                                    livePreviewPlaceholder(
                                                        section,
                                                    )
                                                }}
                                            </div>
                                        </div>

                                        <button
                                            v-if="
                                                placementForGridItem(
                                                    section,
                                                    item,
                                                )
                                            "
                                            type="button"
                                            class="pointer-events-auto absolute right-1 top-1 z-10 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-orange-500 shadow-none transition hover:border-orange-200 hover:bg-orange-50 hover:text-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-200"
                                            :title="
                                                t(
                                                    'components.block_editor.settings',
                                                    'Settings',
                                                )
                                            "
                                            :aria-label="
                                                t(
                                                    'components.block_editor.settings',
                                                    'Settings',
                                                )
                                            "
                                            @pointerdown.stop
                                            @mousedown.stop
                                            @touchstart.stop
                                            @click.stop="
                                                openPlacementSettings(
                                                    placementForGridItem(
                                                        section,
                                                        item,
                                                    ),
                                                )
                                            "
                                        >
                                            <span
                                                class="mdi mdi-cog text-xs"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </div>
                                </HoverCardTrigger>

                                <HoverCardContent
                                    v-if="
                                        gridPreviewMode === 'compact' &&
                                        placementHoverRows(
                                            placementForGridItem(section, item),
                                        ).length > 0
                                    "
                                    side="bottom"
                                    align="center"
                                    :side-offset="10"
                                    :collision-padding="12"
                                    :avoid-collisions="true"
                                    update-position-strategy="always"
                                    :reference="hoverCardCursorReference"
                                    class="grid content-start gap-1"
                                >
                                    <div
                                        v-for="row in placementHoverRows(
                                            placementForGridItem(section, item),
                                        )"
                                        :key="row.key"
                                        class="grid grid-cols-[5.75rem_minmax(0,1fr)] gap-2"
                                    >
                                        <span
                                            class="font-medium text-slate-400"
                                        >
                                            {{ row.label }}:
                                        </span>
                                        <span
                                            class="min-w-0 break-words text-slate-50"
                                        >
                                            {{ row.value }}
                                        </span>
                                    </div>
                                </HoverCardContent>
                            </HoverCard>
                        </template>
                    </GridLayout>

                    <div
                        v-if="hiddenPlacementsForGrid(section).length > 0"
                        class="grid gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3"
                    >
                        <div class="grid gap-1">
                            <h4 class="text-sm font-semibold text-slate-900">
                                {{
                                    t(
                                        'layouts.sections.hidden_on_device_title',
                                        'Hidden on this device',
                                    )
                                }}
                            </h4>
                            <p class="text-xs text-slate-600">
                                {{
                                    t(
                                        'layouts.sections.hidden_on_device_description',
                                        'These blocks do not take space in the current device grid.',
                                    )
                                }}
                            </p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            <div
                                v-for="placement in hiddenPlacementsForGrid(
                                    section,
                                )"
                                :key="placement.uid"
                                class="flex min-w-0 items-center justify-between gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700"
                            >
                                <div class="min-w-0">
                                    <div
                                        class="truncate font-semibold uppercase tracking-wide text-slate-700"
                                    >
                                        {{
                                            placeableBlockLabel(
                                                placement.block || {},
                                            )
                                        }}
                                    </div>
                                    <div class="truncate text-slate-500">
                                        {{ blockPreviewTitle(placement) }}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white text-orange-500 shadow-none transition hover:border-orange-200 hover:bg-orange-50 hover:text-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-200"
                                    :title="
                                        t(
                                            'components.block_editor.settings',
                                            'Settings',
                                        )
                                    "
                                    :aria-label="
                                        t(
                                            'components.block_editor.settings',
                                            'Settings',
                                        )
                                    "
                                    @click="
                                        openPlacementSettings(
                                            placement,
                                            'style',
                                        )
                                    "
                                >
                                    <span
                                        class="mdi mdi-cog text-sm"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="section.placements.length === 0"
                    class="rounded-md bg-white p-4 text-sm text-slate-500"
                >
                    {{
                        t(
                            'layouts.sections.empty_blocks',
                            'Nog geen blokken in deze sectie.',
                        )
                    }}
                </div>

                <div
                    v-for="(placement, placementIndex) in section.placements"
                    :key="placement.uid"
                    v-show="!supportsResponsiveGrid"
                    :class="placementEditorCardClasses(placement)"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium uppercase tracking-wide text-slate-600"
                                >
                                    {{ placeableBlockLabel(placement.block) }}
                                    #{{ placementIndex + 1 }}
                                </span>
                                <span
                                    :class="
                                        blockCategoryBadgeClasses(
                                            placement.block,
                                        )
                                    "
                                >
                                    {{ blockCategoryLabel(placement.block) }}
                                </span>
                                <span
                                    :class="
                                        placementStatusBadgeClasses(placement)
                                    "
                                >
                                    {{ placementStatusLabel(placement) }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4
                                    class="truncate text-sm font-semibold text-slate-900"
                                >
                                    {{ blockPreviewTitle(placement) }}
                                </h4>
                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500"
                                >
                                    {{ blockUsageHint(placement.block) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="openPlacementSettings(placement)"
                            >
                                {{
                                    t(
                                        'components.block_editor.settings',
                                        'Instellingen',
                                    )
                                }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="h-8 w-8 border-slate-300 bg-white text-slate-700 shadow-none hover:bg-slate-100"
                                :disabled="placementIndex === 0"
                                :title="
                                    t('components.block_editor.up', 'Omhoog')
                                "
                                :aria-label="
                                    t('components.block_editor.up', 'Omhoog')
                                "
                                @click="
                                    movePlacement(section, placementIndex, -1)
                                "
                            >
                                <span
                                    class="mdi mdi-chevron-up text-xl"
                                    aria-hidden="true"
                                />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="h-8 w-8 border-slate-300 bg-white text-slate-700 shadow-none hover:bg-slate-100"
                                :disabled="
                                    placementIndex ===
                                    section.placements.length - 1
                                "
                                :title="
                                    t('components.block_editor.down', 'Omlaag')
                                "
                                :aria-label="
                                    t('components.block_editor.down', 'Omlaag')
                                "
                                @click="
                                    movePlacement(section, placementIndex, 1)
                                "
                            >
                                <span
                                    class="mdi mdi-chevron-down text-xl"
                                    aria-hidden="true"
                                />
                            </Button>
                            <DropdownMenu :modal="false">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        class="h-8 w-8 border-slate-300 bg-white text-slate-700 shadow-none hover:bg-slate-100"
                                        :aria-label="
                                            t('actions.more', 'More actions')
                                        "
                                        :title="
                                            t('actions.more', 'More actions')
                                        "
                                    >
                                        <span
                                            class="mdi mdi-dots-vertical text-base"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-44">
                                    <DropdownMenuItem as-child>
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 text-red-700"
                                            @click="
                                                removePlacement(
                                                    section,
                                                    placementIndex,
                                                )
                                            "
                                        >
                                            <span
                                                class="mdi mdi-delete"
                                                aria-hidden="true"
                                            />
                                            {{
                                                t(
                                                    'components.block_editor.delete',
                                                    'Delete',
                                                )
                                            }}
                                        </button>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    <div :class="placementSummaryCardClasses(placement)">
                        <div
                            class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs"
                        >
                            <span class="font-medium text-slate-800">
                                {{ placementVisibilitySummary(placement) }}
                            </span>
                            <span class="text-slate-300">|</span>
                            <span class="text-slate-600">
                                {{ placementLayoutSummary(placement) }}
                            </span>
                        </div>
                        <div
                            v-if="placementStyleSummary(placement)"
                            class="flex flex-wrap items-center gap-2 text-xs"
                        >
                            <span
                                class="rounded-full bg-white px-2 py-0.5 font-medium text-slate-700 ring-1 ring-slate-200"
                            >
                                {{
                                    t(
                                        'layouts.sections.appearance_summary_label',
                                        'Stijl',
                                    )
                                }}
                            </span>
                            <span class="text-slate-600">
                                {{ placementStyleSummary(placement) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <CmsPlacementSettingsDialog
            :open="settingsDialogOpen"
            :placement="settingsDialogPlacement"
            :active-tab="settingsDialogTab"
            :tabs="settingsDialogTabs"
            :zone="zone"
            :dialog-flash="placementDialogFlash"
            :saving="placementSaving"
            :palette-items="currentColorPaletteItems"
            @update:palette-items="updateColorPaletteItems"
            @update:media-options="updateMediaOptions"
            @update:media-folders="updateMediaFolders"
            :style-token-options="styleTokenOptions"
            :layout-locale="layoutLocale"
            :initial-style-device="activeGridDevice"
            :can-manage-code-blocks="canManageCodeBlocks"
            :form-options="formOptions"
            :menu-options="menuOptions"
            :contact-settings="contactSettings"
            :media-options="currentMediaOptions"
            :media-folders="currentMediaFolders"
            :download-options="downloadOptions"
            :download-folders="downloadFolders"
            :span-options="spanOptions"
            :placement-cache-options="placementCacheOptions"
            :placeable-blocks="catalogBlocks"
            :slot-definitions="selectedPlacementSlotDefinitions"
            :alignment-options="alignmentOptions"
            :content-alignment-options="contentAlignmentOptions"
            :content-vertical-alignment-options="
                contentVerticalAlignmentOptions
            "
            :helpers="settingsDialogHelpers"
            @update:open="handleSettingsDialogOpenChange"
            @update:active-tab="settingsDialogTab = $event"
            @delete-requested="removePlacementFromSettingsDialog"
            @save-requested="handlePlacementSaveRequested"
            @slot-child-settings-requested="openSlotChildSettingsDialog"
        />

        <CmsPlacementSettingsDialog
            :open="slotChildSettingsDialogOpen"
            :placement="slotChildSettingsDialogPlacement"
            :active-tab="slotChildSettingsDialogTab"
            :tabs="slotChildSettingsDialogTabs"
            zone="slot"
            :dialog-flash="emptyPlacementDialogFlash"
            :saving="false"
            :palette-items="currentColorPaletteItems"
            @update:palette-items="updateColorPaletteItems"
            @update:media-options="updateMediaOptions"
            @update:media-folders="updateMediaFolders"
            :style-token-options="styleTokenOptions"
            :layout-locale="layoutLocale"
            :initial-style-device="activeGridDevice"
            :can-manage-code-blocks="canManageCodeBlocks"
            :form-options="formOptions"
            :menu-options="menuOptions"
            :contact-settings="contactSettings"
            :media-options="currentMediaOptions"
            :media-folders="currentMediaFolders"
            :download-options="downloadOptions"
            :download-folders="downloadFolders"
            :span-options="spanOptions"
            :placement-cache-options="placementCacheOptions"
            :placeable-blocks="catalogBlocks"
            :slot-definitions="[]"
            :alignment-options="alignmentOptions"
            :content-alignment-options="contentAlignmentOptions"
            :content-vertical-alignment-options="
                contentVerticalAlignmentOptions
            "
            :helpers="settingsDialogHelpers"
            is-slot-child
            :can-delete="false"
            @update:open="handleSlotChildSettingsDialogOpenChange"
            @update:active-tab="slotChildSettingsDialogTab = $event"
            @save-requested="handleSlotChildSettingsSaveRequested"
        />

        <Dialog
            :open="sectionDialogOpen"
            @update:open="handleSectionDialogOpenChange"
        >
            <DialogScrollContent
                class="flex max-h-[calc(100vh-2rem)] w-[96vw] max-w-6xl flex-col overflow-hidden p-0 md:w-[96vw]"
            >
                <DialogHeader
                    class="shrink-0 border-b border-slate-200 px-6 pb-4 pr-12 pt-6"
                >
                    <DialogTitle>
                        {{ sectionDialogTitle }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'layouts.sections.settings_description',
                                'Beheer de naam, opmaak en zichtbaarheid van deze sectie.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="dialogFlash.message"
                    class="shrink-0 border-b border-slate-200 px-6 py-3"
                >
                    <RwFlashMessage
                        :type="dialogFlash.type"
                        :message="dialogFlash.message"
                        :details="dialogFlash.details"
                    />
                </div>

                <div
                    v-if="sectionDialogForm"
                    class="min-h-0 flex-1 overflow-y-auto px-6 py-5"
                >
                    <div class="grid gap-5">
                        <div class="grid gap-2">
                            <div class="flex flex-wrap items-end gap-4">
                                <div class="grid w-full max-w-md gap-2">
                                    <Label
                                        :for="
                                            sectionFieldId(
                                                sectionDialogForm,
                                                'name',
                                            )
                                        "
                                    >
                                        {{
                                            t(
                                                'layouts.sections.section_name',
                                                'Sectienaam',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        :id="
                                            sectionFieldId(
                                                sectionDialogForm,
                                                'name',
                                            )
                                        "
                                        v-model="sectionDialogForm.name"
                                        :name="
                                            sectionFieldName(
                                                sectionDialogForm,
                                                'name',
                                            )
                                        "
                                        required
                                        :aria-invalid="
                                            sectionDialogNameInvalid
                                                ? 'true'
                                                : 'false'
                                        "
                                        class="border-yellow-200 bg-yellow-50 focus-visible:ring-yellow-300"
                                        :placeholder="
                                            t(
                                                'layouts.sections.section_name_placeholder',
                                                'Bijvoorbeeld bovenbalk',
                                            )
                                        "
                                    />
                                </div>

                                <label
                                    class="flex h-9 items-center gap-2 text-sm text-slate-700"
                                >
                                    <input
                                        :id="
                                            sectionFieldId(
                                                sectionDialogForm,
                                                'is_active',
                                            )
                                        "
                                        v-model="sectionDialogForm.is_active"
                                        :name="
                                            sectionFieldName(
                                                sectionDialogForm,
                                                'is_active',
                                            )
                                        "
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    {{ t('common.columns.active', 'Actief') }}
                                </label>
                            </div>
                            <p
                                v-if="sectionDialogNameError"
                                class="text-xs text-red-600"
                            >
                                {{ sectionDialogNameError }}
                            </p>

                            <div
                                v-if="sectionDialogForm.settings.html_anchor"
                                class="grid max-w-md gap-1 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm"
                            >
                                <Label
                                    :for="
                                        sectionFieldId(
                                            sectionDialogForm,
                                            'html_anchor',
                                        )
                                    "
                                >
                                    {{
                                        t(
                                            'layouts.sections.css_anchor',
                                            'CSS anchor',
                                        )
                                    }}
                                </Label>
                                <Input
                                    :id="
                                        sectionFieldId(
                                            sectionDialogForm,
                                            'html_anchor',
                                        )
                                    "
                                    :model-value="
                                        sectionDialogForm.settings.html_anchor
                                    "
                                    readonly
                                    class="bg-white font-mono text-xs"
                                />
                                <p class="text-xs text-slate-600">
                                    {{
                                        t(
                                            'layouts.sections.css_anchor_help',
                                            'Use this stable ID only for custom site-specific CSS. Platform and theme CSS should keep using classes and tokens.',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="grid items-start gap-4 lg:grid-cols-2">
                            <div class="grid gap-4">
                                <div
                                    class="grid gap-3 rounded-md border border-blue-100 bg-blue-50/60 p-3"
                                >
                                    <div
                                        v-if="!supportsResponsiveGrid"
                                        class="grid gap-1 text-sm"
                                    >
                                        <Label
                                            :for="
                                                sectionFieldId(
                                                    sectionDialogForm,
                                                    'layout_type',
                                                )
                                            "
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.layout_type',
                                                    'Layout',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="
                                                sectionFieldId(
                                                    sectionDialogForm,
                                                    'layout_type',
                                                )
                                            "
                                            v-model="
                                                sectionDialogForm.settings
                                                    .layout_type
                                            "
                                            :name="
                                                sectionFieldName(
                                                    sectionDialogForm,
                                                    'layout_type',
                                                )
                                            "
                                            class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option
                                                v-for="option in sectionLayoutOptionsForZone"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="grid gap-1 text-sm">
                                        <Label
                                            :for="
                                                sectionFieldId(
                                                    sectionDialogForm,
                                                    'width_mode',
                                                )
                                            "
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.width_mode',
                                                    'Breedte',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="
                                                sectionFieldId(
                                                    sectionDialogForm,
                                                    'width_mode',
                                                )
                                            "
                                            v-model="
                                                sectionDialogForm.settings
                                                    .width_mode
                                            "
                                            :name="
                                                sectionFieldName(
                                                    sectionDialogForm,
                                                    'width_mode',
                                                )
                                            "
                                            class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option value="content">
                                                {{
                                                    t(
                                                        'layouts.sections.width_content',
                                                        'Containerbreedte',
                                                    )
                                                }}
                                            </option>
                                            <option value="display">
                                                {{
                                                    t(
                                                        'layouts.sections.width_display',
                                                        'Volle beeldbreedte',
                                                    )
                                                }}
                                            </option>
                                        </select>
                                    </div>

                                    <div
                                        v-if="supportsEdgeScrollBehavior"
                                        class="grid gap-1 text-sm"
                                    >
                                        <Label
                                            :for="
                                                sectionFieldId(
                                                    sectionDialogForm,
                                                    'scroll_behavior',
                                                )
                                            "
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.scroll_behavior',
                                                    'Scrollgedrag',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="
                                                sectionFieldId(
                                                    sectionDialogForm,
                                                    'scroll_behavior',
                                                )
                                            "
                                            v-model="
                                                sectionDialogForm.settings
                                                    .scroll_behavior
                                            "
                                            :name="
                                                sectionFieldName(
                                                    sectionDialogForm,
                                                    'scroll_behavior',
                                                )
                                            "
                                            class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option
                                                v-for="option in sectionScrollBehaviorOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <BackgroundPickerField
                                v-model="sectionDialogForm.settings.background"
                                :palette-items="currentColorPaletteItems"
                                :assets="currentMediaOptions"
                                :folders="currentMediaFolders"
                                @update:palette-items="updateColorPaletteItems"
                                @update:assets="updateMediaOptions"
                                @update:folders="updateMediaFolders"
                                :id-prefix="
                                    sectionFieldId(
                                        sectionDialogForm,
                                        'background',
                                    )
                                "
                                :label="
                                    t(
                                        'layouts.sections.background',
                                        'Achtergrond',
                                    )
                                "
                            />
                        </div>

                        <BoxSpacingEditor
                            v-model="sectionDialogForm.settings.box"
                            v-model:visible-desktop="
                                sectionDialogForm.visible_desktop
                            "
                            v-model:visible-tablet="
                                sectionDialogForm.visible_tablet
                            "
                            v-model:visible-mobile="
                                sectionDialogForm.visible_mobile
                            "
                            :id-prefix="
                                sectionFieldId(sectionDialogForm, 'box')
                            "
                            :title="t('layouts.box.section_title', 'Spacing')"
                            :description="
                                t(
                                    'layouts.box.section_description',
                                    'Stel marge en padding per device en per zijde in.',
                                )
                            "
                        />
                    </div>
                </div>

                <DialogFooter
                    class="shrink-0 flex-row items-center justify-end gap-2 border-t border-slate-200 px-6 py-4"
                >
                    <DropdownMenu v-if="sectionDialogCanDelete" :modal="false">
                        <DropdownMenuTrigger as-child>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="h-9 w-9 shrink-0 shadow-none"
                                :aria-label="t('actions.more', 'Meer acties')"
                                :title="t('actions.more', 'Meer acties')"
                            >
                                <span
                                    class="mdi mdi-dots-vertical text-base"
                                    aria-hidden="true"
                                />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-44">
                            <DropdownMenuItem as-child>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-2 text-red-700"
                                    @click="removeSectionFromDialog"
                                >
                                    <span
                                        class="mdi mdi-delete"
                                        aria-hidden="true"
                                    />
                                    {{
                                        t(
                                            'components.block_editor.delete',
                                            'Verwijderen',
                                        )
                                    }}
                                </button>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="saving"
                        class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                        @click="saveSectionDialog"
                    >
                        <span
                            v-if="saving"
                            class="mdi mdi-loading animate-spin text-base text-green-700"
                            aria-hidden="true"
                        />
                        <span
                            v-else
                            class="mdi mdi-content-save text-base"
                            :class="sectionDialogSaveIconClass"
                            aria-hidden="true"
                        />
                        {{ t('actions.save', 'Bewaren') }}
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <Dialog
            :open="blockPickerOpen"
            @update:open="handleBlockPickerOpenChange"
        >
            <DialogScrollContent
                class="flex max-h-[calc(100vh-2rem)] max-w-4xl flex-col gap-0 overflow-hidden p-0"
            >
                <DialogHeader
                    class="shrink-0 border-b border-slate-200 px-6 pb-4 pr-12 pt-6"
                >
                    <DialogTitle>
                        {{
                            t(
                                'layouts.sections.block_picker_title',
                                'Blok toevoegen',
                            )
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'layouts.sections.block_picker_description',
                                'Kies welk blok je aan deze sectie wil toevoegen.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    <div class="grid gap-4">
                        <div
                            v-if="blockPickerGroups.length === 0"
                            class="rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500"
                        >
                            {{
                                t(
                                    'layouts.sections.block_picker_empty',
                                    'Er zijn geen blokken beschikbaar voor deze zone.',
                                )
                            }}
                        </div>
                        <div
                            v-for="group in blockPickerGroups"
                            :key="group.category"
                            class="grid gap-2"
                        >
                            <h4
                                class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                            >
                                {{ group.label }}
                            </h4>
                            <div
                                class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3"
                            >
                                <button
                                    v-for="option in group.options"
                                    :key="option.value"
                                    type="button"
                                    class="group grid gap-2 rounded-lg border border-slate-200 bg-white p-3 text-left shadow-none transition hover:border-blue-300 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    @click="selectBlockForSection(option.value)"
                                >
                                    <span
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <span
                                            class="text-sm font-semibold text-slate-900 group-hover:text-blue-800"
                                        >
                                            {{ option.label }}
                                        </span>
                                        <span
                                            class="mdi mdi-plus-circle text-lg text-blue-600"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span
                                        class="text-xs leading-5 text-slate-500"
                                    >
                                        {{
                                            blockPickerOptionDescription(option)
                                        }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>

<script setup>
import BackgroundPickerField from '@/Pages/Admin/Cms/Layouts/Partials/BackgroundPickerField.vue';
import BoxSpacingEditor from '@/Pages/Admin/Cms/Layouts/Partials/BoxSpacingEditor.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import CmsPlacementSettingsDialog from '@/Pages/Admin/Cms/Layouts/Partials/CmsPlacementSettingsDialog.vue';
import {
    legacySectionSpacingBox,
    normalizeBoxSpacing,
} from '@/Pages/Admin/Cms/Layouts/Partials/boxSpacing';
import {
    gridItemForPlacementLayout,
    normalizePlacementLayoutConfig as normalizePlacementLayoutConfigHelper,
    resolvePlacementLayoutCollisions,
} from '@/Pages/Admin/Cms/Layouts/placementGridLayout';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { GridLayout } from 'grid-layout-plus';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    zone: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    formOptions: { type: Array, default: () => [] },
    menuOptions: { type: Array, default: () => [] },
    contactSettings: { type: Object, default: () => ({}) },
    mediaOptions: { type: Array, default: () => [] },
    mediaFolders: { type: Array, default: () => [] },
    downloadOptions: { type: Array, default: () => [] },
    downloadFolders: { type: Array, default: () => [] },
    placeableBlocks: { type: Array, default: () => [] },
    colorPaletteItems: { type: Array, default: () => [] },
    styleTokenOptions: { type: Object, default: () => ({}) },
    layoutLocale: { type: String, default: '' },
    canManageCodeBlocks: { type: Boolean, default: false },
    dialogFlash: {
        type: Object,
        default: () => ({ type: '', message: '', details: [] }),
    },
    placementDialogFlash: {
        type: Object,
        default: () => ({ type: '', message: '', details: [] }),
    },
    responsiveGrid: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    placementSaving: { type: Boolean, default: false },
});

const emit = defineEmits([
    'update:modelValue',
    'update:colorPaletteItems',
    'update:mediaOptions',
    'update:mediaFolders',
    'save-requested',
    'placement-save-requested',
    'section-dialog-open-changed',
    'placement-dialog-open-changed',
]);
const { t } = useAdminTranslations('cms_admin_ui');
const hoverCardCursor = ref({ x: 0, y: 0 });
const hoverCardCursorReference = {
    getBoundingClientRect: () => {
        const x = hoverCardCursor.value.x;
        const y = hoverCardCursor.value.y;

        return {
            x,
            y,
            top: y,
            right: x,
            bottom: y,
            left: x,
            width: 0,
            height: 0,
        };
    },
};
const spanOptions = [12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1];
const menuDisplayModes = ['horizontal', 'vertical', 'hamburger'];
const languageDisplayModes = ['horizontal', 'vertical', 'dropdown'];
const menuAlignments = ['left', 'center', 'right'];
const menuItemVariants = ['plain', 'pill', 'underline', 'button'];
const menuSpacings = ['compact', 'normal', 'spacious'];
const formFieldSpacings = ['compact', 'normal', 'spacious'];
const formInputRadii = ['inherit', 'none', 'sm', 'md', 'lg', 'pill'];
const formInputBorders = ['default', 'none', 'subtle', 'strong', 'primary'];
const formSubmitAlignments = ['inherit', 'left', 'center', 'right', 'stretch'];
const formSubmitVariants = ['default', 'outline', 'ghost'];
const contentOverrideExcludedRendererKeys = [
    'breadcrumb',
    'content_slot',
    'dynamic_field',
    'form',
    'list_grid',
    'list_rows',
];
const menuDrawerSides = ['left', 'right'];
const menuDrawerTops = ['viewport', 'below_sticky_header'];
const menuSubmenuBehaviors = ['hover'];
const menuSubmenuSides = ['left', 'right'];
const menuToggleIcons = ['hamburger', 'dots', 'grid'];
const menuToggleShapes = ['pill', 'rounded', 'square', 'circle'];
const menuToggleSizes = ['compact', 'normal', 'large'];
const languageFlagPositions = ['before', 'after'];
const languageFlagShapes = ['rectangle', 'rounded', 'circle'];
const languageFlagSizes = ['small', 'normal', 'large'];
const languageIcons = [
    'none',
    'mdi-earth',
    'mdi-translate',
    'mdi-web',
    'mdi-flag-outline',
];
const menuToggleColorFields = [
    'color',
    'background_color',
    'hover_color',
    'hover_background_color',
];
const menuColorFields = [
    'text_color',
    'background_color',
    'hover_text_color',
    'hover_background_color',
    'pressed_text_color',
    'pressed_background_color',
    'active_text_color',
    'active_background_color',
];
const formColorFields = ['input_background_color', 'input_text_color'];
const gridRowHeight = 34;
const gridMargin = [8, 8];
const activeGridDevice = ref('desktop');
const gridPreviewMode = ref('compact');
const placementPreviewDocuments = ref({});
const placementPreviewMeasuredRows = ref({});
const previewLoadingSections = ref({});
const previewFailedSections = ref({});
const placementPreviewFrames = new Map();
let previewRefreshTimer = null;
let previewMeasurementTimer = null;
let previewRequestSequence = 0;
const settingsDialogOpen = ref(false);
const settingsDialogPlacement = ref(null);
const settingsDialogTab = ref('content');
const slotChildSettingsDialogOpen = ref(false);
const slotChildSettingsDialogPlacement = ref(null);
const slotChildSettingsDialogTab = ref('style');
const sectionDialogOpen = ref(false);
const sectionDialogForm = ref(null);
const sectionDialogIndex = ref(null);
const sectionDialogSubmitted = ref(false);
const blockPickerOpen = ref(false);
const blockPickerSection = ref(null);
const currentColorPaletteItems = ref([...props.colorPaletteItems]);
const currentMediaOptions = ref([...props.mediaOptions]);
const currentMediaFolders = ref([...props.mediaFolders]);
const emptyPlacementDialogFlash = { type: '', message: '', details: [] };

watch(
    () => props.colorPaletteItems,
    (items) => {
        currentColorPaletteItems.value = [...items];
    },
);

watch(
    () => props.mediaOptions,
    (items) => {
        currentMediaOptions.value = [...items];
    },
);

watch(
    () => props.mediaFolders,
    (items) => {
        currentMediaFolders.value = [...items];
    },
);

function updateMediaOptions(items) {
    currentMediaOptions.value = [...items];
    emit('update:mediaOptions', currentMediaOptions.value);
}

function updateColorPaletteItems(items) {
    currentColorPaletteItems.value = [...items];
    emit('update:colorPaletteItems', currentColorPaletteItems.value);
}

function updateMediaFolders(items) {
    currentMediaFolders.value = [...items];
    emit('update:mediaFolders', currentMediaFolders.value);
}

const settingsDialogTabs = computed(() => {
    const tabs = [
        {
            value: 'content',
            label: t('components.block_editor.settings_tab_content', 'Inhoud'),
        },
    ];

    if (selectedPlacementSlotDefinitions.value.length > 0) {
        tabs.push({
            value: 'slots',
            label: t('components.block_editor.settings_tab_slots', 'Slots'),
        });
    }

    tabs.push({
        value: 'style',
        label: t('components.block_editor.settings_tab_style', 'Stijl'),
    });

    if (props.canManageCodeBlocks) {
        tabs.push(
            {
                value: 'code',
                label: t('components.block_editor.settings_tab_code', 'Code'),
            },
            {
                value: 'css',
                label: t('components.block_editor.settings_tab_css', 'CSS'),
            },
        );
    }

    return tabs;
});
const slotChildSettingsDialogTabs = computed(() => {
    const tabs = [
        {
            value: 'content',
            label: t('components.block_editor.settings_tab_content', 'Inhoud'),
        },
        {
            value: 'style',
            label: t('components.block_editor.settings_tab_style', 'Stijl'),
        },
    ];

    if (props.canManageCodeBlocks) {
        tabs.push(
            {
                value: 'code',
                label: t('components.block_editor.settings_tab_code', 'Code'),
            },
            {
                value: 'css',
                label: t('components.block_editor.settings_tab_css', 'CSS'),
            },
        );
    }

    return tabs;
});
const selectedPlacementSlotDefinitions = computed(() =>
    placementSlotDefinitions(settingsDialogPlacement.value),
);
const settingsDialogHelpers = {
    addMediaListItem,
    addRepeaterItem,
    availableMediaListOptions,
    blockEditorFields,
    blockEditorGridClasses,
    blockRenderingModeBadgeClasses,
    blockRenderingModeDescription,
    blockRenderingModeLabel,
    editorFieldLabel,
    editorFieldOptionLabel,
    editorFieldPlaceholder,
    editorFieldWrapperClasses,
    editorTextareaClasses,
    hasCodeEditorField,
    hasEditorFields,
    isSystemBlock,
    mediaListItems,
    placeableBlockRendererKey,
    removeMediaListItem,
    repeaterItemPreviewTitle,
    repeaterItems,
    placeableBlockLabel,
};
const responsiveDeviceOptions = [
    {
        value: 'desktop',
        label: t('layouts.sections.desktop', 'Desktop'),
    },
    {
        value: 'tablet',
        label: t('layouts.sections.tablet', 'Tablet'),
    },
    {
        value: 'mobile',
        label: t('layouts.sections.mobile', 'Mobiel'),
    },
];
const alignmentOptions = [
    {
        value: '',
        label: t('layouts.sections.placement_alignment_default', 'Standaard'),
    },
    {
        value: 'left',
        label: t('layouts.sections.placement_alignment_left', 'Links'),
    },
    {
        value: 'center',
        label: t('layouts.sections.placement_alignment_center', 'Midden'),
    },
    {
        value: 'right',
        label: t('layouts.sections.placement_alignment_right', 'Rechts'),
    },
];
const contentAlignmentOptions = [
    {
        value: '',
        label: t('layouts.sections.content_alignment_default', 'Standaard'),
    },
    {
        value: 'left',
        label: t('layouts.sections.content_alignment_left', 'Links'),
    },
    {
        value: 'center',
        label: t('layouts.sections.content_alignment_center', 'Midden'),
    },
    {
        value: 'right',
        label: t('layouts.sections.content_alignment_right', 'Rechts'),
    },
];
const contentVerticalAlignmentOptions = [
    {
        value: '',
        label: t(
            'layouts.sections.content_vertical_alignment_default',
            'Default',
        ),
    },
    {
        value: 'top',
        label: t('layouts.sections.content_vertical_alignment_top', 'Top'),
    },
    {
        value: 'middle',
        label: t(
            'layouts.sections.content_vertical_alignment_middle',
            'Middle',
        ),
    },
    {
        value: 'bottom',
        label: t(
            'layouts.sections.content_vertical_alignment_bottom',
            'Bottom',
        ),
    },
];
const placementCacheOptions = [
    {
        value: 'inherit',
        label: t('layouts.cache.inherit', 'Overerven'),
    },
    {
        value: 'none',
        label: t('layouts.cache.none', 'Geen cache'),
    },
    {
        value: 'block',
        label: t('layouts.cache.block', 'Per block'),
    },
    {
        value: 'layout',
        label: t('layouts.cache.layout', 'Volledige layout'),
    },
];
const sectionLayoutOptions = [
    {
        value: 'standard',
        label: t('layouts.sections.layout_standard', 'Standaard'),
    },
    { value: 'hero', label: t('layouts.sections.layout_hero', 'Hero') },
    {
        value: 'two_columns',
        label: t('layouts.sections.layout_two_columns', '2 kolommen'),
    },
    { value: 'grid', label: t('layouts.sections.layout_grid', 'Grid') },
];
const sectionLayoutOptionsForZone = computed(() =>
    supportsResponsiveGrid.value
        ? sectionLayoutOptions.filter((option) =>
              ['standard', 'grid'].includes(option.value),
          )
        : sectionLayoutOptions,
);
const supportsResponsiveGrid = computed(
    () => props.responsiveGrid || ['header', 'footer'].includes(props.zone),
);
const supportsEdgeScrollBehavior = computed(() =>
    ['header', 'footer'].includes(props.zone),
);
const sectionScrollBehaviorOptions = [
    {
        value: 'normal',
        label: t('layouts.sections.scroll_behavior_normal', 'Scrolt mee'),
    },
    {
        value: 'sticky',
        label: t('layouts.sections.scroll_behavior_sticky', 'Sticky'),
    },
    {
        value: 'auto_hide',
        label: t(
            'layouts.sections.scroll_behavior_auto_hide',
            'Sticky, automatisch verbergen',
        ),
    },
];
const sectionBackgroundModes = [
    'cover',
    'contain',
    'stretch',
    'center',
    'repeat',
    'repeat-x',
    'repeat-y',
];
const sectionBackgroundPositions = [
    'center center',
    'center top',
    'center bottom',
    'left center',
    'right center',
];
const sectionDialogTitle = computed(() =>
    sectionDialogIndex.value === null
        ? t('layouts.sections.add_section', 'Sectie toevoegen')
        : t('layouts.sections.settings', 'Sectie-instellingen'),
);
const sectionDialogCanDelete = computed(
    () => sectionDialogIndex.value !== null,
);
const sectionDialogNameInvalid = computed(
    () => !String(sectionDialogForm.value?.name || '').trim(),
);
const sectionDialogNameError = computed(() => {
    if (!sectionDialogSubmitted.value) {
        return '';
    }

    if (sectionDialogNameInvalid.value) {
        return t('validation.required', 'This field is required.');
    }

    return '';
});
const sectionDialogSaveIconClass = computed(() =>
    sectionDialogNameInvalid.value ? 'text-red-700' : 'text-green-700',
);
const fallbackVisualBlockTypeOptions = [
    {
        value: 'text',
        label: t('components.block_editor.text', 'Tekst'),
        fields: ['title', 'text'],
    },
    {
        value: 'feature_card',
        label: t('components.block_editor.feature_card', 'Feature kaart'),
        fields: ['title', 'text'],
    },
    {
        value: 'quote',
        label: t('components.block_editor.quote', 'Quote'),
        fields: ['text', 'source'],
    },
    {
        value: 'testimonial',
        label: t('components.block_editor.testimonial', 'Testimonial'),
        fields: ['text', 'source'],
    },
    {
        value: 'stats',
        label: t('components.block_editor.stats', 'Cijferblok'),
        fields: ['value', 'suffix', 'label'],
    },
    {
        value: 'video',
        label: t('components.block_editor.video', 'Video'),
        fields: ['title', 'video_url'],
    },
    {
        value: 'logo_strip',
        label: t('components.block_editor.logo_strip', 'Logo rij'),
        fields: ['title', 'media_asset_ids'],
    },
    {
        value: 'button',
        label: t('components.block_editor.button', 'Knop'),
        fields: ['label', 'url'],
    },
    {
        value: 'image',
        label: t('components.block_editor.image', 'Afbeelding'),
        fields: ['media_asset_id', 'caption'],
    },
    {
        value: 'form',
        label: t('components.block_editor.form', 'Formulier'),
        fields: ['form_translation_key'],
    },
    {
        value: 'breadcrumb',
        label: t('components.block_editor.breadcrumb', 'Breadcrumb'),
        fields: ['show_current', 'compact'],
    },
    {
        value: 'list_rows',
        label: t('components.block_editor.list_rows', 'Lijst - rijen'),
        fields: [
            'title',
            'source_type',
            'category_source',
            'category_id',
            'tag_source',
            'tag_id',
            'show_only_subcategories',
            'limit',
            'sort_field',
            'sort_direction',
            'show_search',
            'show_excerpt',
            'show_image',
            'show_date',
            'show_categories',
            'empty_text',
        ],
    },
    {
        value: 'list_grid',
        label: t('components.block_editor.list_grid', 'Lijst - raster'),
        fields: [
            'title',
            'source_type',
            'category_source',
            'category_id',
            'tag_source',
            'tag_id',
            'show_only_subcategories',
            'limit',
            'sort_field',
            'sort_direction',
            'show_search',
            'show_excerpt',
            'show_image',
            'show_date',
            'show_categories',
            'empty_text',
        ],
    },
];
const fallbackPlaceableBlocks = [
    {
        id: 0,
        key: 'site_head',
        name: t('layouts.sections.site_head', 'Site head'),
        renderer_key: 'site_head',
        rendering_mode: 'platform_blade',
        allowed_zones: ['head'],
        schema: { category: 'system', fields: [], editor_fields: [] },
    },
    {
        id: 0,
        key: 'site_brand',
        name: t('components.block_editor.site_brand', 'Site brand'),
        renderer_key: 'site_brand',
        rendering_mode: 'safe_blade',
        allowed_zones: ['header', 'footer', 'content'],
        schema: {
            category: 'header',
            fields: ['title', 'link_url'],
            editor_fields: [],
        },
    },
    {
        id: 0,
        key: 'site_logo',
        name: t('components.block_editor.site_logo', 'Site logo'),
        renderer_key: 'site_logo',
        rendering_mode: 'safe_blade',
        allowed_zones: ['header', 'footer'],
        schema: {
            category: 'header',
            fields: ['media_asset_id', 'alt_text', 'link_url'],
            editor_fields: [],
        },
    },
    {
        id: 0,
        key: 'site_baseline',
        name: t('components.block_editor.site_baseline', 'Site baseline'),
        renderer_key: 'site_baseline',
        rendering_mode: 'safe_blade',
        allowed_zones: ['header', 'footer'],
        schema: {
            category: 'header',
            fields: ['text'],
            editor_fields: [],
        },
    },
    {
        id: 0,
        key: 'custom_head_code',
        name: t('layouts.sections.custom_head_code', 'Custom head code'),
        renderer_key: 'custom_head_code',
        rendering_mode: 'raw_code_permissioned',
        allowed_zones: ['head'],
        schema: { category: 'code', fields: ['code'], editor_fields: [] },
    },
    {
        id: 0,
        key: 'custom_body_end_code',
        name: t(
            'layouts.sections.custom_body_end_code',
            'Custom body end code',
        ),
        renderer_key: 'custom_body_end_code',
        rendering_mode: 'raw_code_permissioned',
        allowed_zones: ['body_end'],
        schema: { category: 'code', fields: ['code'], editor_fields: [] },
    },
    ...fallbackVisualBlockTypeOptions.map((option) => ({
        id: 0,
        key: option.value,
        name: option.label,
        renderer_key: option.value,
        rendering_mode: 'safe_blade',
        allowed_zones: ['content', 'header', 'footer'],
        schema: {
            category: 'content',
            fields: option.fields,
            editor_fields: [],
        },
    })),
];
const catalogBlocks = computed(() =>
    Array.isArray(props.placeableBlocks) && props.placeableBlocks.length > 0
        ? props.placeableBlocks
        : fallbackPlaceableBlocks,
);
const placeableBlockOptions = computed(() => {
    return catalogBlocks.value
        .map((block, index) => ({ ...block, registry_index: index }))
        .filter(
            (block) =>
                Array.isArray(block.allowed_zones) &&
                block.allowed_zones.includes(props.zone),
        )
        .filter((block) => blockAllowedInPickerZone(block))
        .filter((block) => block.schema?.editor_visible !== false)
        .filter(
            (block) =>
                blockCategoryFromCatalog(block) !== 'code' ||
                props.canManageCodeBlocks,
        )
        .sort(placeableBlockSort)
        .map((block) => placeableBlockOption(block));
});
const blockPickerTypeOptions = computed(() => placeableBlockOptions.value);
const blockPickerGroups = computed(() => {
    return blockPickerTypeOptions.value.reduce((groups, option) => {
        const category = option.category || 'content';
        const existingGroup = groups.find(
            (group) => group.category === category,
        );

        if (existingGroup) {
            existingGroup.options.push(option);

            return groups;
        }

        groups.push({
            category,
            label: blockCategoryLabelByValue(category),
            options: [option],
        });

        return groups;
    }, []);
});

const previewModeOptions = computed(() => [
    {
        value: 'compact',
        label: t('layouts.sections.preview_mode_compact', 'Compact'),
    },
    {
        value: 'live',
        label: t('layouts.sections.preview_mode_live', 'Live content'),
    },
]);

const sections = ref(
    props.modelValue.map((section) => normalizeSection(section)),
);

watch(
    sections,
    () => {
        applyAddressBlockContactDefaultsToSections();
        emitSections();
        schedulePreviewRefresh();
    },
    { deep: true },
);

watch(gridPreviewMode, (mode) => {
    if (mode === 'live') {
        schedulePreviewRefresh(0);
        schedulePreviewMeasurements();

        return;
    }

    placementPreviewMeasuredRows.value = {};
});

watch(activeGridDevice, () => {
    placementPreviewMeasuredRows.value = {};
    schedulePreviewRefresh();
});

onBeforeUnmount(() => {
    if (previewRefreshTimer) {
        window.clearTimeout(previewRefreshTimer);
    }

    if (previewMeasurementTimer) {
        window.clearTimeout(previewMeasurementTimer);
    }

    placementPreviewFrames.clear();
});

watch(
    () => props.contactSettings,
    () => {
        if (applyAddressBlockContactDefaultsToSections()) {
            emitSections();
        }
    },
    { deep: true, immediate: true },
);

function emitSections() {
    emit('update:modelValue', serializeSections(sections.value));
}

function handlePlacementSaveRequested() {
    emitSections();
    emit('placement-save-requested');
}

function setGridPreviewMode(mode) {
    gridPreviewMode.value = mode === 'live' ? 'live' : 'compact';
}

function schedulePreviewRefresh(delay = 450) {
    if (gridPreviewMode.value !== 'live' || !supportsResponsiveGrid.value) {
        return;
    }

    if (previewRefreshTimer) {
        window.clearTimeout(previewRefreshTimer);
    }

    previewRefreshTimer = window.setTimeout(() => {
        refreshLivePreviews();
    }, delay);
}

async function refreshLivePreviews() {
    if (gridPreviewMode.value !== 'live' || !supportsResponsiveGrid.value) {
        return;
    }

    const requestSequence = ++previewRequestSequence;
    const nextDocuments = {};
    const nextFailedSections = {};
    const loadingSections = {};
    const sectionsToPreview = sections.value.filter(
        (section) => section.placements.length > 0,
    );

    sectionsToPreview.forEach((section) => {
        loadingSections[section.uid] = true;
    });
    previewLoadingSections.value = loadingSections;

    await Promise.all(
        sectionsToPreview.map(async (section) => {
            try {
                const response = await window.axios.post(
                    route('admin.cms.section-preview'),
                    {
                        zone: props.zone,
                        locale: props.layoutLocale || '',
                        device: activeGridDevice.value,
                        section: serializePreviewSection(section),
                    },
                );

                Object.assign(nextDocuments, response.data?.previews || {});
            } catch {
                nextFailedSections[section.uid] = true;
            }
        }),
    );

    if (requestSequence !== previewRequestSequence) {
        return;
    }

    placementPreviewDocuments.value = nextDocuments;
    placementPreviewMeasuredRows.value = {};
    previewFailedSections.value = nextFailedSections;
    previewLoadingSections.value = {};

    schedulePreviewMeasurements(75);
}

function serializePreviewSection(section) {
    return {
        ...serializeSection(section),
        uid: section.uid,
        placements: section.placements.map((placement) => ({
            ...serializePlacement(placement),
            uid: placement.uid,
            published_style_revision:
                placement.published_style_revision || null,
        })),
    };
}

function placementLivePreviewDocument(placement) {
    if (!placement?.uid) {
        return '';
    }

    return placementPreviewDocuments.value[placement.uid] || '';
}

function registerPlacementPreviewFrame(placement, element) {
    if (!placement?.uid) {
        return;
    }

    if (element) {
        placementPreviewFrames.set(placement.uid, element);
        schedulePreviewMeasurements();

        return;
    }

    placementPreviewFrames.delete(placement.uid);
}

function measurePlacementPreview(placement, frame) {
    if (gridPreviewMode.value !== 'live' || !placement?.uid || !frame) {
        return;
    }

    window.setTimeout(() => {
        updatePlacementPreviewRows(placement.uid, measuredFrameRows(frame));
    }, 0);

    window.setTimeout(() => {
        updatePlacementPreviewRows(placement.uid, measuredFrameRows(frame));
    }, 250);
}

function schedulePreviewMeasurements(delay = 0) {
    if (gridPreviewMode.value !== 'live') {
        return;
    }

    if (previewMeasurementTimer) {
        window.clearTimeout(previewMeasurementTimer);
    }

    previewMeasurementTimer = window.setTimeout(() => {
        previewMeasurementTimer = null;
        measureVisiblePlacementPreviews();
    }, delay);
}

function measureVisiblePlacementPreviews() {
    if (gridPreviewMode.value !== 'live') {
        return;
    }

    placementPreviewFrames.forEach((frame, uid) => {
        updatePlacementPreviewRows(uid, measuredFrameRows(frame));
    });
}

function measuredFrameRows(frame) {
    try {
        const document = frame.contentDocument;
        const wrapper = document?.querySelector('.rw-admin-placement-preview');
        const placement = document?.querySelector('.rw-public-placement');
        const measuredHeight = Math.max(
            wrapper?.scrollHeight || 0,
            wrapper?.getBoundingClientRect?.().height || 0,
            placement?.scrollHeight || 0,
            placement?.getBoundingClientRect?.().height || 0,
        );

        return previewRowsForHeight(measuredHeight);
    } catch {
        return null;
    }
}

function previewRowsForHeight(height) {
    const measuredHeight = Number(height);

    if (!Number.isFinite(measuredHeight) || measuredHeight <= 0) {
        return null;
    }

    return Math.max(
        1,
        Math.ceil(
            (measuredHeight + gridMargin[1]) / (gridRowHeight + gridMargin[1]),
        ),
    );
}

function updatePlacementPreviewRows(uid, rows) {
    if (!uid || !Number.isFinite(rows) || rows < 1) {
        return;
    }

    if (placementPreviewMeasuredRows.value[uid] === rows) {
        return;
    }

    placementPreviewMeasuredRows.value = {
        ...placementPreviewMeasuredRows.value,
        [uid]: rows,
    };
}

function livePreviewPlaceholder(section) {
    if (previewLoadingSections.value[section.uid]) {
        return t('layouts.sections.live_preview_loading', 'Loading preview...');
    }

    if (previewFailedSections.value[section.uid]) {
        return t(
            'layouts.sections.live_preview_failed',
            'The live preview could not be loaded.',
        );
    }

    return t('layouts.sections.live_preview_loading', 'Loading preview...');
}

function openNewSectionDialog() {
    sectionDialogForm.value = normalizeSection({ placements: [] });
    sectionDialogIndex.value = null;
    sectionDialogSubmitted.value = false;
    sectionDialogOpen.value = true;
}

function openEditSectionDialog(section, index) {
    sectionDialogForm.value = cloneSectionForDialog(section);
    sectionDialogIndex.value = index;
    sectionDialogSubmitted.value = false;
    sectionDialogOpen.value = true;
}

function handleSectionDialogOpenChange(open) {
    sectionDialogOpen.value = open;
    emit('section-dialog-open-changed', { zone: props.zone, open });

    if (!open) {
        sectionDialogForm.value = null;
        sectionDialogIndex.value = null;
        sectionDialogSubmitted.value = false;
    }
}

async function saveSectionDialog() {
    if (!sectionDialogForm.value) {
        return;
    }

    sectionDialogSubmitted.value = true;

    if (sectionDialogNameInvalid.value) {
        return;
    }

    const normalizedSection = normalizeSection(sectionDialogForm.value);

    if (sectionDialogIndex.value === null) {
        sections.value.push(normalizedSection);
    } else if (sections.value[sectionDialogIndex.value]) {
        sections.value[sectionDialogIndex.value] = {
            ...sections.value[sectionDialogIndex.value],
            name: normalizedSection.name,
            is_active: normalizedSection.is_active,
            visible_mobile: normalizedSection.visible_mobile,
            visible_tablet: normalizedSection.visible_tablet,
            visible_desktop: normalizedSection.visible_desktop,
            settings: normalizedSection.settings,
        };
    }

    await nextTick();
    emit('save-requested');
}

function cloneSectionForDialog(section) {
    return normalizeSection({
        ...section,
        settings: { ...section.settings },
        placements: Array.isArray(section.placements)
            ? [...section.placements]
            : [],
    });
}

function removeSection(index) {
    sections.value = sections.value.filter(
        (section, sectionIndex) => sectionIndex !== index,
    );
}

function removeSectionFromDialog() {
    if (sectionDialogIndex.value === null) {
        return;
    }

    removeSection(sectionDialogIndex.value);
    handleSectionDialogOpenChange(false);
}

function moveSection(index, direction) {
    const nextIndex = index + direction;

    if (nextIndex < 0 || nextIndex >= sections.value.length) {
        return;
    }

    const nextSections = [...sections.value];
    const [section] = nextSections.splice(index, 1);
    nextSections.splice(nextIndex, 0, section);
    sections.value = nextSections;
}

function openBlockPicker(section) {
    blockPickerSection.value = section;
    blockPickerOpen.value = true;
}

function handleBlockPickerOpenChange(open) {
    blockPickerOpen.value = open;

    if (!open) {
        blockPickerSection.value = null;
    }
}

function selectBlockForSection(placeableBlockId) {
    if (!blockPickerSection.value) {
        return;
    }

    addPlacement(blockPickerSection.value, placeableBlockId);
    handleBlockPickerOpenChange(false);
}

function addPlacement(section, placeableBlockId = defaultPlaceableBlockId()) {
    const placements = Array.isArray(section.placements)
        ? section.placements
        : [];

    section.placements = resolvePlacementLayoutCollisions(
        [
            ...placements,
            normalizePlacement(
                {
                    settings:
                        contentOverrideSettingsForNewPlacement(
                            placeableBlockId,
                        ),
                    block: { cms_placeable_block_id: placeableBlockId },
                },
                placements.length,
            ),
        ],
        { applyAlignment: true },
    );
    emitSections();
}

function contentOverrideSettingsForNewPlacement(placeableBlockId) {
    if (props.zone !== 'content') {
        return {};
    }

    const definition = placeableBlockDefinitionById(placeableBlockId);

    if (
        !definition ||
        blockCategoryFromCatalog(definition) !== 'content' ||
        contentOverrideExcludedRendererKeys.includes(definition.renderer_key) ||
        catalogBlockEditorFields(definition).length === 0
    ) {
        return {};
    }

    const baseKey = normalizeContentKey(definition.renderer_key || 'block');

    if (!baseKey) {
        return {};
    }

    return {
        content_key: uniqueContentKey(baseKey),
        editor_label: definition.name || definition.label || baseKey,
        page_editable: true,
        page_editable_fields: catalogBlockEditorFields(definition)
            .filter((field) => field.type !== 'code')
            .map((field) => field.name)
            .filter(Boolean),
        page_editable_meta: ['is_active'],
    };
}

function uniqueContentKey(baseKey) {
    const usedKeys = contentKeysForSections(sections.value);

    if (!usedKeys.has(baseKey)) {
        return baseKey;
    }

    let suffix = 2;
    let candidate = `${baseKey}_${suffix}`;

    while (usedKeys.has(candidate)) {
        suffix += 1;
        candidate = `${baseKey}_${suffix}`;
    }

    return candidate;
}

function contentKeysForSections(sectionList) {
    const keys = new Set();

    sectionList.forEach((section) => {
        collectPlacementContentKeys(section.placements, keys);
    });

    return keys;
}

function collectPlacementContentKeys(placements, keys) {
    (Array.isArray(placements) ? placements : []).forEach((placement) => {
        const contentKey = normalizeContentKey(placement.settings?.content_key);

        if (contentKey) {
            keys.add(contentKey);
        }

        Object.values(placement.slots || {}).forEach((slot) => {
            collectPlacementContentKeys(slot?.placements, keys);
        });
    });
}

function openPlacementSettings(placement, tab = 'content') {
    applyAddressBlockContactDefaults(placement.block);
    settingsDialogPlacement.value = placement;
    settingsDialogTab.value = tab;
    settingsDialogOpen.value = true;
}

function handleSettingsDialogOpenChange(open) {
    settingsDialogOpen.value = open;
    emit('placement-dialog-open-changed', { zone: props.zone, open });

    if (!open) {
        settingsDialogPlacement.value = null;
    }
}

function openSlotChildSettingsDialog(placement) {
    slotChildSettingsDialogPlacement.value = placement;
    slotChildSettingsDialogTab.value = 'style';
    slotChildSettingsDialogOpen.value = true;
}

function handleSlotChildSettingsDialogOpenChange(open) {
    slotChildSettingsDialogOpen.value = open;

    if (!open) {
        slotChildSettingsDialogPlacement.value = null;
    }
}

function handleSlotChildSettingsSaveRequested() {
    slotChildSettingsDialogOpen.value = false;
    slotChildSettingsDialogPlacement.value = null;
    handlePlacementSaveRequested();
}

function sectionFieldId(section, field) {
    return `cms-${props.zone}-${sanitizeFieldIdentifier(section.uid)}-${sanitizeFieldIdentifier(field)}`;
}

function sectionFieldName(section, field) {
    return `cms_${props.zone}_${sanitizeFieldIdentifier(section.uid)}_${sanitizeFieldIdentifier(field)}`;
}

function sanitizeFieldIdentifier(value) {
    return String(value).replace(/[^A-Za-z0-9_-]/g, '_');
}

function removePlacement(section, index) {
    section.placements = section.placements.filter(
        (placement, placementIndex) => placementIndex !== index,
    );
    emitSections();
}

function removePlacementFromSettingsDialog() {
    const placementUid = settingsDialogPlacement.value?.uid;

    if (!placementUid) {
        return;
    }

    const section = sections.value.find((item) =>
        item.placements.some((placement) => placement.uid === placementUid),
    );

    if (!section) {
        return;
    }

    const placementIndex = section.placements.findIndex(
        (placement) => placement.uid === placementUid,
    );

    if (placementIndex < 0) {
        return;
    }

    removePlacement(section, placementIndex);
    handleSettingsDialogOpenChange(false);
}

function movePlacement(section, index, direction) {
    const nextIndex = index + direction;

    if (nextIndex < 0 || nextIndex >= section.placements.length) {
        return;
    }

    const nextPlacements = [...section.placements];
    const [placement] = nextPlacements.splice(index, 1);
    nextPlacements.splice(nextIndex, 0, placement);
    section.placements = nextPlacements;
}

function normalizeSection(section) {
    const placements = Array.isArray(section.placements)
        ? section.placements.map((placement, placementIndex) =>
              normalizePlacement(placement, placementIndex),
          )
        : [];

    return {
        uid:
            section.uid ||
            `section-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        id: section.id || null,
        name: section.name || '',
        is_active: Boolean(section.is_active ?? true),
        visible_mobile: Boolean(section.visible_mobile ?? true),
        visible_tablet: Boolean(section.visible_tablet ?? true),
        visible_desktop: Boolean(section.visible_desktop ?? true),
        collapsed: Boolean(section.collapsed ?? false),
        settings: normalizeSectionSettings(section.settings || {}),
        placements: resolvePlacementLayoutCollisions(placements, {
            applyAlignment: true,
        }),
    };
}

function normalizePlacement(placement, placementIndex = 0, depth = 0) {
    const normalizedBlock = normalizeBlock(placement.block || {});

    return {
        uid:
            placement.uid ||
            `placement-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        id: placement.id || null,
        is_active: Boolean(placement.is_active ?? true),
        visible_mobile: Boolean(placement.visible_mobile ?? true),
        visible_tablet: Boolean(placement.visible_tablet ?? true),
        visible_desktop: Boolean(placement.visible_desktop ?? true),
        mobile_span: Number(placement.mobile_span || 12),
        tablet_span: Number(placement.tablet_span || 12),
        desktop_span: Number(placement.desktop_span || 12),
        layout_config: normalizePlacementLayoutConfig(
            placement.layout_config,
            placement,
            placementIndex,
        ),
        style_config: normalizePlacementStyleConfig(placement.style_config),
        published_style_revision_id:
            placement.published_style_revision_id || null,
        published_style_revision: placement.published_style_revision || null,
        style_revisions: Array.isArray(placement.style_revisions)
            ? placement.style_revisions
            : [],
        height_mode: placement.height_mode || 'auto',
        height_value: placement.height_value || null,
        cache_strategy: placement.cache_strategy || 'inherit',
        settings: {
            html_anchor:
                typeof placement.settings?.html_anchor === 'string'
                    ? placement.settings.html_anchor
                    : null,
            content_key:
                typeof placement.settings?.content_key === 'string'
                    ? placement.settings.content_key
                    : '',
            editor_label:
                typeof placement.settings?.editor_label === 'string'
                    ? placement.settings.editor_label
                    : '',
            page_editable: Boolean(
                placement.settings?.page_editable ||
                placement.settings?.content_key,
            ),
            page_editable_fields: Array.isArray(
                placement.settings?.page_editable_fields,
            )
                ? placement.settings.page_editable_fields.filter(
                      (field) => typeof field === 'string' && field,
                  )
                : blockEditorFields(normalizedBlock)
                      .filter((field) => field.type !== 'code')
                      .map((field) => field.name)
                      .filter(Boolean),
            page_editable_meta: Array.isArray(
                placement.settings?.page_editable_meta,
            )
                ? placement.settings.page_editable_meta.filter(
                      (field) => field === 'is_active',
                  )
                : [],
            alignment: ['left', 'center', 'right'].includes(
                placement.settings?.alignment,
            )
                ? placement.settings.alignment
                : '',
            content_alignment: ['left', 'center', 'right'].includes(
                placement.settings?.content_alignment,
            )
                ? placement.settings.content_alignment
                : '',
        },
        block: normalizedBlock,
        slots:
            depth === 0
                ? normalizePlacementSlots(placement.slots, normalizedBlock)
                : {},
    };
}

function normalizePlacementSlots(slots, block) {
    const source = slots && typeof slots === 'object' ? slots : {};
    const normalizedSlots = {};

    placementSlotDefinitions({ block }).forEach((slot) => {
        const slotKey = String(slot.key || '');

        if (slotKey === '') {
            return;
        }

        const slotData = source[slotKey];
        const placements = Array.isArray(slotData?.placements)
            ? slotData.placements
            : Array.isArray(slotData)
              ? slotData
              : [];

        normalizedSlots[slotKey] = {
            placements: placements.map((placement, index) =>
                normalizePlacement(placement, index, 1),
            ),
        };
    });

    return normalizedSlots;
}

function normalizeBlock(block) {
    const placeableBlock = placeableBlockDefinitionById(
        block.cms_placeable_block_id,
    );
    const normalizedBlock = {
        id: block.id || null,
        cms_placeable_block_id: placeableBlock?.id || defaultPlaceableBlockId(),
        placeable_block_revision_id: block.placeable_block_revision_id || null,
        name: block.name || '',
        title: block.title || block.heading || '',
        text: block.text || block.content || block.body || '',
        source: block.source || '',
        media_asset_id: block.media_asset_id || '',
        caption: block.caption || '',
        label: block.label || '',
        url: block.url || '',
        form_translation_key: block.form_translation_key || '',
        show_current: Boolean(block.show_current ?? true),
        compact: Boolean(block.compact ?? false),
        source_type: block.source_type || 'category',
        category_source: block.category_source || 'all',
        category_id: block.category_id || '',
        tag_source: block.tag_source || 'all',
        tag_id: block.tag_id || '',
        show_only_subcategories: Boolean(
            block.show_only_subcategories ?? false,
        ),
        limit: Number(block.limit || 24),
        sort_field: block.sort_field || 'published_at',
        sort_direction: block.sort_direction || 'desc',
        show_search: Boolean(block.show_search ?? false),
        show_excerpt: Boolean(block.show_excerpt ?? true),
        show_image: Boolean(block.show_image ?? true),
        show_date: Boolean(block.show_date ?? true),
        show_categories: Boolean(block.show_categories ?? true),
        empty_text: block.empty_text || '',
        code: block.code || '',
        _contact_defaults_applied: truthyBlockValue(
            block._contact_defaults_applied,
        ),
    };

    blockEditorFields(normalizedBlock).forEach((field) => {
        if (Object.prototype.hasOwnProperty.call(normalizedBlock, field.name)) {
            return;
        }

        normalizedBlock[field.name] = editorFieldDefault(
            field,
            block[field.name],
        );
    });

    return normalizedBlock;
}

function applyAddressBlockContactDefaults(block) {
    if (placeableBlockRendererKey(block) !== 'address_block') {
        return false;
    }

    if (block._contact_defaults_applied === true) {
        return false;
    }

    const fieldMap = {
        media_asset_id: 'image_media_asset_id',
        company_name: 'company_name',
        street: 'street',
        postal_code: 'postal_code',
        city: 'city',
        country: 'country',
        country_code: 'country_code',
        phone_1_label: 'phone_1_label',
        phone_1: 'phone_1',
        phone_2_label: 'phone_2_label',
        phone_2: 'phone_2',
        phone_3_label: 'phone_3_label',
        phone_3: 'phone_3',
        email_1_label: 'email_1_label',
        email_1: 'email_1',
        email_2_label: 'email_2_label',
        email_2: 'email_2',
        vat_number: 'vat_number',
    };

    let hasAppliedDefault = false;

    Object.entries(fieldMap).forEach(([blockField, settingKey]) => {
        if (!isEmptyAddressBlockValue(block[blockField])) {
            return;
        }

        const settingValue = props.contactSettings?.[settingKey];

        if (!isEmptyAddressBlockValue(settingValue)) {
            block[blockField] = settingValue;
            hasAppliedDefault = true;
        }
    });

    if (hasAppliedDefault) {
        applyAddressBlockVisibilityDefaults(block);
        block._contact_defaults_applied = true;
    }

    return hasAppliedDefault;
}

function applyAddressBlockVisibilityDefaults(block) {
    if (!isEmptyAddressBlockValue(block.company_name)) {
        block.show_company_name = true;
    }

    if (
        ['street', 'postal_code', 'city', 'country', 'country_code'].some(
            (field) => !isEmptyAddressBlockValue(block[field]),
        )
    ) {
        block.show_address = true;
    }

    if (
        ['phone_1', 'phone_2', 'phone_3'].some(
            (field) => !isEmptyAddressBlockValue(block[field]),
        )
    ) {
        block.show_phones = true;
    }

    if (
        ['email_1', 'email_2'].some(
            (field) => !isEmptyAddressBlockValue(block[field]),
        )
    ) {
        block.show_emails = true;
    }

    if (!isEmptyAddressBlockValue(block.vat_number)) {
        block.show_vat_number = true;
    }
}

function applyAddressBlockContactDefaultsToSections() {
    let changed = false;

    sections.value.forEach((section) => {
        section.placements.forEach((placement) => {
            if (applyAddressBlockContactDefaults(placement.block)) {
                changed = true;
            }
        });
    });

    return changed;
}

function isEmptyAddressBlockValue(value) {
    return value === null || value === undefined || String(value).trim() === '';
}

function truthyBlockValue(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
}

function serializeSections(sectionList) {
    const usedContentKeys = new Set();

    return sectionList.map((section) =>
        serializeSection(section, usedContentKeys),
    );
}

function serializeSection(section, usedContentKeys = new Set()) {
    const placements = resolvePlacementLayoutCollisions(section.placements, {
        applyAlignment: true,
    });

    return {
        id: section.id,
        name: section.name || null,
        is_active: Boolean(section.is_active),
        visible_mobile: Boolean(section.visible_mobile),
        visible_tablet: Boolean(section.visible_tablet),
        visible_desktop: Boolean(section.visible_desktop),
        settings: normalizeSectionSettings(section.settings || {}),
        placements: placements.map((placement) =>
            serializePlacement(placement, 0, usedContentKeys),
        ),
    };
}

function normalizeSectionSettings(settings) {
    const box = normalizeBoxSpacing(
        settings.box,
        legacySectionSpacingBox(settings.spacing),
    );

    if (supportsResponsiveGrid.value) {
        return {
            html_anchor:
                typeof settings.html_anchor === 'string'
                    ? settings.html_anchor
                    : null,
            layout_type: 'grid',
            width_mode: ['content', 'display'].includes(settings.width_mode)
                ? settings.width_mode
                : 'display',
            spacing: 'none',
            scroll_behavior: normalizedSectionScrollBehavior(settings),
            background: normalizeSectionBackground(settings.background),
            box,
        };
    }

    const allowedLayoutTypes = sectionLayoutOptionsForZone.value.map(
        (option) => option.value,
    );

    return {
        html_anchor:
            typeof settings.html_anchor === 'string'
                ? settings.html_anchor
                : null,
        layout_type: allowedLayoutTypes.includes(settings.layout_type)
            ? settings.layout_type
            : 'standard',
        width_mode: ['content', 'display'].includes(settings.width_mode)
            ? settings.width_mode
            : 'display',
        spacing: 'none',
        ...(supportsEdgeScrollBehavior.value
            ? { scroll_behavior: normalizedSectionScrollBehavior(settings) }
            : {}),
        background: normalizeSectionBackground(settings.background),
        box,
    };
}

function normalizeSectionBackground(background) {
    const value =
        background && typeof background === 'object' ? background : {};

    return {
        color: normalizeHexColor(value.color),
        media_asset_id: value.media_asset_id || null,
        mode: sectionBackgroundModes.includes(value.mode)
            ? value.mode
            : 'cover',
        position: sectionBackgroundPositions.includes(value.position)
            ? value.position
            : 'center center',
        image_opacity: normalizeSectionBackgroundImageOpacity(
            value.image_opacity,
        ),
    };
}

function normalizeSectionBackgroundImageOpacity(value) {
    const opacity = Number(value ?? 100);

    if (!Number.isFinite(opacity)) {
        return 100;
    }

    return Math.min(100, Math.max(0, Math.round(opacity)));
}

function normalizedSectionScrollBehavior(settings) {
    return ['normal', 'sticky', 'auto_hide'].includes(settings.scroll_behavior)
        ? settings.scroll_behavior
        : 'normal';
}

function serializePlacement(placement, depth = 0, usedContentKeys = new Set()) {
    return {
        id: placement.id,
        is_active: Boolean(placement.is_active),
        visible_mobile: Boolean(placement.visible_mobile),
        visible_tablet: Boolean(placement.visible_tablet),
        visible_desktop: Boolean(placement.visible_desktop),
        mobile_span: Number(placement.mobile_span || 12),
        tablet_span: Number(placement.tablet_span || 12),
        desktop_span: Number(placement.desktop_span || 12),
        layout_config: normalizePlacementLayoutConfig(
            placement.layout_config,
            placement,
        ),
        style_config: normalizePlacementStyleConfig(placement.style_config),
        height_mode: placement.height_mode || 'auto',
        height_value: placement.height_value || null,
        cache_strategy: placement.cache_strategy || 'inherit',
        settings: serializePlacementSettings(placement, usedContentKeys),
        block: serializeBlock(placement.block),
        slots:
            depth === 0
                ? serializePlacementSlots(placement, usedContentKeys)
                : {},
    };
}

function serializePlacementSlots(placement, usedContentKeys = new Set()) {
    const slots =
        placement.slots && typeof placement.slots === 'object'
            ? placement.slots
            : {};
    const serialized = {};

    placementSlotDefinitions(placement).forEach((slot) => {
        const slotKey = String(slot.key || '');

        if (slotKey === '') {
            return;
        }

        const placements = Array.isArray(slots[slotKey]?.placements)
            ? slots[slotKey].placements
            : [];

        serialized[slotKey] = {
            placements: placements.map((childPlacement) =>
                serializePlacement(childPlacement, 1, usedContentKeys),
            ),
        };
    });

    return serialized;
}

function normalizePlacementLayoutConfig(
    layoutConfig,
    placement = {},
    fallbackY = 0,
) {
    return normalizePlacementLayoutConfigHelper(
        layoutConfig,
        placement,
        fallbackY,
    );
}

function normalizePlacementStyleConfig(styleConfig) {
    const config = { devices: {} };
    const legacyAppearance =
        styleConfig?.appearance && typeof styleConfig.appearance === 'object'
            ? styleConfig.appearance
            : {};
    const legacyDesktop = {
        appearance: legacyAppearance,
    };

    ['desktop', 'tablet', 'mobile'].forEach((device) => {
        const source =
            styleConfig?.devices?.[device] ||
            styleConfig?.[device] ||
            (device === 'desktop' ? legacyDesktop : {});
        config.devices[device] = normalizePlacementDeviceStyle(
            source,
            device === 'desktop' ? null : config.devices.desktop,
        );
    });

    config.box = normalizeBoxSpacing(styleConfig?.box);
    config.menu = normalizePlacementMenuStyle(styleConfig?.menu);
    config.language = normalizePlacementLanguageStyle(styleConfig?.language);
    config.form = normalizePlacementFormStyle(styleConfig?.form);
    config.developer = {
        css_source:
            typeof styleConfig?.developer?.css_source === 'string'
                ? styleConfig.developer.css_source
                : '',
    };

    return config;
}

function normalizePlacementFormStyle(form) {
    const source = form && typeof form === 'object' ? form : {};
    const allowedColorTokens = styleTokenValues(
        props.styleTokenOptions?.color,
        [
            'page',
            'surface',
            'surface-muted',
            'text',
            'muted',
            'border',
            'primary',
            'primary-strong',
            'primary-contrast',
            'success',
            'success-bg',
            'error',
            'error-bg',
        ],
    );
    const normalized = {
        field_spacing: allowedStyleValue(
            source.field_spacing,
            formFieldSpacings,
            'normal',
        ),
        label_weight: allowedStyleValue(
            source.label_weight,
            styleTokenValues(props.styleTokenOptions?.fontWeight, [
                'inherit',
                'normal',
                'medium',
                'semibold',
                'bold',
            ]),
            'inherit',
        ),
        input_radius: allowedStyleValue(
            source.input_radius,
            formInputRadii,
            'inherit',
        ),
        input_border: allowedStyleValue(
            source.input_border,
            formInputBorders,
            'default',
        ),
        submit_alignment: allowedStyleValue(
            source.submit_alignment,
            formSubmitAlignments,
            'inherit',
        ),
        submit_variant: allowedStyleValue(
            source.submit_variant,
            formSubmitVariants,
            'default',
        ),
    };

    formColorFields.forEach((field) => {
        normalized[field] = normalizeCssColor(source[field]);
        normalized[`${field}_token`] = allowedStyleValue(
            source[`${field}_token`],
            allowedColorTokens,
            '',
        );
    });

    return normalized;
}

function normalizePlacementLanguageStyle(language) {
    const source = language && typeof language === 'object' ? language : {};
    const devices =
        source.devices && typeof source.devices === 'object'
            ? source.devices
            : {};

    return {
        devices: ['desktop', 'tablet', 'mobile'].reduce((model, device) => {
            const deviceSource =
                devices[device] && typeof devices[device] === 'object'
                    ? devices[device]
                    : {};
            const fallbackDisplay =
                device === 'desktop' ? 'horizontal' : 'dropdown';

            model[device] = {
                display: allowedStyleValue(
                    deviceSource.display,
                    languageDisplayModes,
                    fallbackDisplay,
                ),
                alignment: allowedStyleValue(
                    deviceSource.alignment,
                    menuAlignments,
                    'right',
                ),
                label: normalizeDeviceLabel(deviceSource.label),
                icon: normalizeLanguageIcon(deviceSource.icon),
            };

            return model;
        }, {}),
        item_variant: allowedStyleValue(
            source.item_variant,
            menuItemVariants,
            'pill',
        ),
        spacing: allowedStyleValue(source.spacing, menuSpacings, 'normal'),
        appearance: normalizePlacementMenuAppearance(source.appearance),
        flag_position: allowedStyleValue(
            source.flag_position,
            languageFlagPositions,
            'before',
        ),
        flag_shape: allowedStyleValue(
            source.flag_shape,
            languageFlagShapes,
            'rounded',
        ),
        flag_size: allowedStyleValue(
            source.flag_size,
            languageFlagSizes,
            'normal',
        ),
    };
}

function normalizePlacementMenuStyle(menu) {
    const source = menu && typeof menu === 'object' ? menu : {};
    const devices =
        source.devices && typeof source.devices === 'object'
            ? source.devices
            : {};

    let fallbackToggle = null;

    return {
        devices: ['desktop', 'tablet', 'mobile'].reduce((model, device) => {
            const deviceSource =
                devices[device] && typeof devices[device] === 'object'
                    ? devices[device]
                    : {};
            const fallbackDisplay =
                device === 'desktop' ? 'horizontal' : 'hamburger';

            model[device] = {
                display: allowedStyleValue(
                    deviceSource.display,
                    menuDisplayModes,
                    fallbackDisplay,
                ),
                alignment: allowedStyleValue(
                    deviceSource.alignment,
                    menuAlignments,
                    'right',
                ),
                toggle_label: normalizeMenuToggleLabel(
                    deviceSource.toggle_label,
                ),
                toggle: normalizeMenuToggle(
                    deviceSource.toggle,
                    fallbackToggle,
                ),
            };
            fallbackToggle = model[device].toggle;

            return model;
        }, {}),
        item_variant: allowedStyleValue(
            source.item_variant,
            menuItemVariants,
            'pill',
        ),
        spacing: allowedStyleValue(source.spacing, menuSpacings, 'normal'),
        drawer_side: allowedStyleValue(
            source.drawer_side,
            menuDrawerSides,
            'right',
        ),
        drawer_top: allowedStyleValue(
            source.drawer_top,
            menuDrawerTops,
            'viewport',
        ),
        submenu_behavior: allowedStyleValue(
            source.submenu_behavior,
            menuSubmenuBehaviors,
            'hover',
        ),
        submenu_side: allowedStyleValue(
            source.submenu_side,
            menuSubmenuSides,
            'right',
        ),
        appearance: normalizePlacementMenuAppearance(source.appearance),
    };
}

function normalizeMenuToggle(toggle, fallbackToggle = null) {
    const source = toggle && typeof toggle === 'object' ? toggle : {};
    const fallback = fallbackToggle || defaultMenuToggle();
    const allowedColorTokens = styleTokenValues(
        props.styleTokenOptions?.color,
        [
            'page',
            'surface',
            'surface-muted',
            'text',
            'muted',
            'border',
            'primary',
            'primary-strong',
            'primary-contrast',
            'success',
            'success-bg',
            'error',
            'error-bg',
        ],
    );
    const normalized = {
        icon: allowedStyleValue(source.icon, menuToggleIcons, fallback.icon),
        shape: allowedStyleValue(
            source.shape,
            menuToggleShapes,
            fallback.shape,
        ),
        size: allowedStyleValue(source.size, menuToggleSizes, fallback.size),
    };

    menuToggleColorFields.forEach((field) => {
        normalized[field] = Object.prototype.hasOwnProperty.call(source, field)
            ? normalizeCssColor(source[field])
            : fallback[field] || null;
        normalized[`${field}_token`] = allowedStyleValue(
            Object.prototype.hasOwnProperty.call(source, `${field}_token`)
                ? source[`${field}_token`]
                : fallback[`${field}_token`] || '',
            allowedColorTokens,
            '',
        );
    });

    return normalized;
}

function normalizeDeviceLabel(value) {
    const label = String(value || '').trim();

    return label.length > 120 ? label.slice(0, 120) : label;
}

function normalizeLanguageIcon(value) {
    const icon = String(value || '').trim();

    if (languageIcons.includes(icon)) {
        return icon;
    }

    return /^mdi-[a-z0-9-]+$/.test(icon) && icon.length <= 64 ? icon : 'none';
}

function defaultMenuToggle() {
    return {
        icon: 'hamburger',
        shape: 'pill',
        size: 'normal',
        color: null,
        color_token: '',
        background_color: null,
        background_color_token: '',
        hover_color: null,
        hover_color_token: '',
        hover_background_color: null,
        hover_background_color_token: '',
    };
}

function normalizePlacementMenuAppearance(appearance) {
    const source =
        appearance && typeof appearance === 'object' ? appearance : {};
    const normalized = {
        typography_preset: allowedStyleValue(
            source.typography_preset,
            styleTokenValues(props.styleTokenOptions?.typographyPreset, [
                'inherit',
                'h1',
                'h2',
                'h3',
                'h4',
                'h5',
                'h6',
                'body',
                'lead',
                'small',
                'caption',
                'eyebrow',
            ]),
            'inherit',
        ),
        font_family_token: allowedStyleValue(
            source.font_family_token,
            styleTokenValues(props.styleTokenOptions?.fontFamily, [
                'inherit',
                'body',
                'heading',
                'brand',
                'accent',
            ]),
            'inherit',
        ),
        font_size_token: allowedStyleValue(
            source.font_size_token,
            styleTokenValues(props.styleTokenOptions?.fontSize, [
                'inherit',
                'body',
                'small',
                'nav',
                'brand',
                'baseline',
            ]),
            'inherit',
        ),
        font_weight: allowedStyleValue(
            source.font_weight,
            styleTokenValues(props.styleTokenOptions?.fontWeight, [
                'inherit',
                'normal',
                'medium',
                'semibold',
                'bold',
            ]),
            'inherit',
        ),
    };

    menuColorFields.forEach((field) => {
        normalized[field] = normalizeCssColor(source[field]);
        normalized[`${field}_token`] = allowedStyleValue(
            source[`${field}_token`],
            styleTokenValues(props.styleTokenOptions?.color, [
                'page',
                'surface',
                'surface-muted',
                'text',
                'muted',
                'border',
                'primary',
                'primary-strong',
                'primary-contrast',
                'success',
                'success-bg',
                'error',
                'error-bg',
            ]),
            '',
        );
    });

    return normalized;
}

function normalizeMenuToggleLabel(value) {
    const label = String(value || '').trim();

    return label.length > 120 ? label.slice(0, 120) : label;
}

function normalizePlacementDeviceStyle(style, fallbackStyle = null) {
    const source = style && typeof style === 'object' ? style : {};
    const fallback = fallbackStyle || defaultPlacementDeviceStyle();
    const appearance =
        source.appearance && typeof source.appearance === 'object'
            ? source.appearance
            : {};

    return {
        alignment: allowedStyleValue(
            source.alignment,
            ['left', 'center', 'right', ''],
            fallbackStyle ? fallback.alignment : '',
        ),
        content_alignment: allowedStyleValue(
            source.content_alignment,
            ['left', 'center', 'right', ''],
            fallbackStyle ? fallback.content_alignment : '',
        ),
        content_vertical_alignment: allowedStyleValue(
            source.content_vertical_alignment,
            ['top', 'middle', 'bottom', ''],
            fallbackStyle ? fallback.content_vertical_alignment : '',
        ),
        z_index: allowedStyleValue(
            source.z_index,
            ['auto', '0', '10', '20', '30', '40', '50'],
            fallbackStyle ? fallback.z_index : 'auto',
        ),
        appearance: normalizePlacementDeviceAppearance(
            appearance,
            fallbackStyle ? fallback.appearance : null,
        ),
    };
}

function defaultPlacementDeviceStyle() {
    return {
        alignment: '',
        content_alignment: '',
        content_vertical_alignment: '',
        z_index: 'auto',
        appearance: normalizePlacementDeviceAppearance({}),
    };
}

function normalizePlacementDeviceAppearance(
    appearance,
    fallbackAppearance = null,
) {
    const fallback = fallbackAppearance || {
        background_color: null,
        background_color_token: '',
        foreground_color: null,
        foreground_color_token: '',
        typography_preset: 'inherit',
        font_family_token: 'inherit',
        font_size_token: 'inherit',
        font_weight: 'inherit',
        logo_size: 'default',
        padding: 'none',
        radius: 'inherit',
        border: 'none',
        shadow: 'none',
    };

    return {
        background_color:
            normalizeHexColor(appearance?.background_color) ??
            fallback.background_color,
        background_color_token: allowedStyleValue(
            appearance?.background_color_token,
            styleTokenValues(props.styleTokenOptions?.color, [
                'page',
                'surface',
                'surface-muted',
                'text',
                'muted',
                'border',
                'primary',
                'primary-strong',
                'primary-contrast',
                'success',
                'success-bg',
                'error',
                'error-bg',
            ]),
            fallback.background_color_token,
        ),
        foreground_color:
            normalizeHexColor(appearance?.foreground_color) ??
            fallback.foreground_color,
        foreground_color_token: allowedStyleValue(
            appearance?.foreground_color_token,
            styleTokenValues(props.styleTokenOptions?.color, [
                'page',
                'surface',
                'surface-muted',
                'text',
                'muted',
                'border',
                'primary',
                'primary-strong',
                'primary-contrast',
                'success',
                'success-bg',
                'error',
                'error-bg',
            ]),
            fallback.foreground_color_token,
        ),
        typography_preset: allowedStyleValue(
            appearance?.typography_preset,
            styleTokenValues(props.styleTokenOptions?.typographyPreset, [
                'inherit',
                'h1',
                'h2',
                'h3',
                'h4',
                'h5',
                'h6',
                'body',
                'lead',
                'small',
                'caption',
                'eyebrow',
            ]),
            fallback.typography_preset,
        ),
        font_family_token: allowedStyleValue(
            appearance?.font_family_token,
            styleTokenValues(props.styleTokenOptions?.fontFamily, [
                'inherit',
                'body',
                'heading',
                'brand',
                'accent',
            ]),
            fallback.font_family_token,
        ),
        font_size_token: allowedStyleValue(
            appearance?.font_size_token,
            styleTokenValues(props.styleTokenOptions?.fontSize, [
                'inherit',
                'body',
                'small',
                'nav',
                'brand',
                'baseline',
            ]),
            fallback.font_size_token,
        ),
        font_weight: allowedStyleValue(
            appearance?.font_weight,
            styleTokenValues(props.styleTokenOptions?.fontWeight, [
                'inherit',
                'normal',
                'medium',
                'semibold',
                'bold',
            ]),
            fallback.font_weight,
        ),
        logo_size: allowedStyleValue(
            appearance?.logo_size,
            ['small', 'default', 'large'],
            fallback.logo_size,
        ),
        padding: allowedStyleValue(
            appearance?.padding,
            ['none', 'sm', 'md', 'lg'],
            fallback.padding,
        ),
        radius: allowedStyleValue(
            appearance?.radius,
            ['inherit', 'none', 'sm', 'md', 'lg'],
            fallback.radius,
        ),
        border: allowedStyleValue(
            appearance?.border,
            ['none', 'subtle', 'strong', 'primary'],
            fallback.border,
        ),
        shadow: allowedStyleValue(
            appearance?.shadow,
            ['none', 'sm', 'md', 'lg'],
            fallback.shadow,
        ),
    };
}

function allowedStyleValue(value, allowedValues, fallback) {
    return allowedValues.includes(value) ? value : fallback;
}

function styleTokenValues(configuredOptions, fallbackValues) {
    const values = Array.isArray(configuredOptions)
        ? configuredOptions
              .map((option) => option?.value)
              .filter(
                  (value) =>
                      typeof value === 'string' && /^[a-z0-9_-]+$/.test(value),
              )
        : [];

    return values.length > 0 ? values : fallbackValues;
}

function normalizeHexColor(value) {
    const color = String(value || '').trim();
    const match = color.match(/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/);

    if (!match) {
        return null;
    }

    let hex = match[1].toLowerCase();

    if (hex.length === 3) {
        hex = hex
            .split('')
            .map((character) => character + character)
            .join('');
    }

    return `#${hex}`;
}

function normalizeCssColor(value) {
    const color = String(value || '').trim();

    if (color === '') {
        return null;
    }

    if (color.length > 120 || /[;{}<>]|url\s*\(|expression\s*\(/i.test(color)) {
        return null;
    }

    if (/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(color)) {
        const hex = color.slice(1).toLowerCase();

        if (hex.length === 3 || hex.length === 4) {
            return `#${hex
                .split('')
                .map((character) => character + character)
                .join('')}`;
        }

        return `#${hex}`;
    }

    if (['transparent', 'currentColor'].includes(color)) {
        return color;
    }

    if (/^var\(--rw-public-[a-z0-9_-]+\)$/.test(color)) {
        return color;
    }

    if (/^(?:rgb|rgba|hsl|hsla)\([0-9.%\s,/+-]+\)$/.test(color)) {
        return color.replace(/\s+/g, ' ');
    }

    return null;
}

function clampInteger(value, min, max) {
    const parsed = Number.parseInt(value, 10);

    return Math.min(max, Math.max(min, Number.isFinite(parsed) ? parsed : min));
}

function integerWithFallback(value, fallback) {
    const parsed = Number.parseInt(value, 10);

    return Number.isFinite(parsed) ? parsed : fallback;
}

function serializePlacementSettings(placement, usedContentKeys = new Set()) {
    const settings = {};
    const contentKey = uniqueSerializedContentKey(
        normalizeContentKey(placement.settings?.content_key),
        usedContentKeys,
    );

    if (contentKey) {
        settings.content_key = contentKey;
    }

    const editorLabel = String(placement.settings?.editor_label || '').trim();

    if (editorLabel) {
        settings.editor_label = editorLabel;
    }

    if (
        Object.prototype.hasOwnProperty.call(
            placement.settings || {},
            'page_editable',
        ) ||
        contentKey
    ) {
        settings.page_editable = Boolean(placement.settings?.page_editable);
    }

    const pageEditableFields = Array.isArray(
        placement.settings?.page_editable_fields,
    )
        ? placement.settings.page_editable_fields.filter((field) =>
              /^[a-z0-9_]+$/.test(String(field || '')),
          )
        : [];

    if (pageEditableFields.length > 0) {
        settings.page_editable_fields = [...new Set(pageEditableFields)];
    }

    const pageEditableMeta = Array.isArray(
        placement.settings?.page_editable_meta,
    )
        ? placement.settings.page_editable_meta.filter(
              (field) => field === 'is_active',
          )
        : [];

    if (pageEditableMeta.length > 0) {
        settings.page_editable_meta = [...new Set(pageEditableMeta)];
    }

    const alignment = placement.settings?.alignment;

    if (['left', 'center', 'right'].includes(alignment)) {
        settings.alignment = alignment;
    }

    const contentAlignment = placement.settings?.content_alignment;

    if (['left', 'center', 'right'].includes(contentAlignment)) {
        settings.content_alignment = contentAlignment;
    }

    return settings;
}

function uniqueSerializedContentKey(contentKey, usedContentKeys) {
    if (!contentKey) {
        return '';
    }

    if (!usedContentKeys.has(contentKey)) {
        usedContentKeys.add(contentKey);

        return contentKey;
    }

    let suffix = 2;
    let candidate = `${contentKey}_${suffix}`;

    while (usedContentKeys.has(candidate)) {
        suffix += 1;
        candidate = `${contentKey}_${suffix}`;
    }

    usedContentKeys.add(candidate);

    return candidate;
}

function normalizeContentKey(value) {
    const key = String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/^_+|_+$/g, '');

    return /^[a-z][a-z0-9_]{0,79}$/.test(key) ? key : '';
}

function serializeBlock(block) {
    const serializedBlock = {
        id: block.id,
        cms_placeable_block_id: block.cms_placeable_block_id || null,
        placeable_block_revision_id: block.placeable_block_revision_id || null,
        name: block.name || null,
        title: block.title || null,
        text: block.text || null,
        source: block.source || null,
        media_asset_id: block.media_asset_id || null,
        caption: block.caption || null,
        label: block.label || null,
        url: block.url || null,
        form_translation_key: block.form_translation_key || null,
        label_display: block.label_display || null,
        show_current: Boolean(block.show_current),
        hide_missing_translations:
            block.hide_missing_translations === null ||
            block.hide_missing_translations === undefined
                ? null
                : Boolean(block.hide_missing_translations),
        flag_position: block.flag_position || null,
        flag_shape: block.flag_shape || null,
        flag_size: block.flag_size || null,
        compact: Boolean(block.compact),
        source_type: block.source_type || 'category',
        category_source: block.category_source || 'all',
        category_id: block.category_id || null,
        tag_source: block.tag_source || 'all',
        tag_id: block.tag_id || null,
        show_only_subcategories: Boolean(block.show_only_subcategories),
        limit: Number(block.limit || 24),
        sort_field: block.sort_field || 'published_at',
        sort_direction: block.sort_direction || 'desc',
        show_search: Boolean(block.show_search),
        show_excerpt: Boolean(block.show_excerpt),
        show_image: Boolean(block.show_image),
        show_date: Boolean(block.show_date),
        show_categories: Boolean(block.show_categories),
        empty_text: block.empty_text || null,
        code: block.code || null,
    };

    if (placeableBlockRendererKey(block) === 'address_block') {
        serializedBlock._contact_defaults_applied = Boolean(
            block._contact_defaults_applied,
        );
    }

    blockEditorFields(block).forEach((field) => {
        if (Object.prototype.hasOwnProperty.call(serializedBlock, field.name)) {
            return;
        }

        serializedBlock[field.name] = serializedEditorFieldValue(
            field,
            block[field.name],
        );
    });

    return serializedBlock;
}

function placeableBlockDefinition(block) {
    if (!block?.cms_placeable_block_id) {
        return null;
    }

    return placeableBlockDefinitionById(block.cms_placeable_block_id);
}

function placeableBlockDefinitionById(id) {
    return catalogBlocks.value.find((block) => Number(block.id) === Number(id));
}

function placementSlotDefinitions(placement) {
    if (!placement?.block) {
        return [];
    }

    const slots = placeableBlockDefinition(placement?.block)?.schema?.slots;

    return Array.isArray(slots) ? slots.filter((slot) => slot?.key) : [];
}

function blockFields(block) {
    const fields = placeableBlockDefinition(block)?.schema?.fields;

    return Array.isArray(fields) ? fields : [];
}

function blockEditorFields(block) {
    const editorFields = placeableBlockDefinition(block)?.schema?.editor_fields;

    if (Array.isArray(editorFields) && editorFields.length > 0) {
        return editorFields;
    }

    return blockFields(block).map((field) => fallbackEditorField(field));
}

function catalogBlockEditorFields(block) {
    const editorFields = block?.schema?.editor_fields;

    if (Array.isArray(editorFields) && editorFields.length > 0) {
        return editorFields;
    }

    const fields = Array.isArray(block?.schema?.fields)
        ? block.schema.fields
        : [];

    return fields.map((field) => fallbackEditorField(field));
}

function blockCategory(block) {
    return blockCategoryFromCatalog(placeableBlockDefinition(block));
}

function isSystemBlock(block) {
    return blockCategory(block) === 'system';
}

function blockRenderingMode(block) {
    return placeableBlockDefinition(block)?.rendering_mode || 'platform_blade';
}

function blockRenderingModeLabel(block) {
    const mode = blockRenderingMode(block);

    return t(
        `components.block_editor.rendering_mode_${mode}`,
        renderingModeFallbackLabel(mode),
    );
}

function blockRenderingModeDescription(block) {
    const mode = blockRenderingMode(block);

    return t(
        `components.block_editor.rendering_mode_${mode}_description`,
        renderingModeFallbackDescription(mode),
    );
}

function blockRenderingModeBadgeClasses(block) {
    const mode = blockRenderingMode(block);

    if (mode === 'safe_blade') {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-100';
    }

    if (mode === 'raw_code_permissioned') {
        return 'bg-orange-50 text-orange-700 ring-orange-100';
    }

    return 'bg-blue-50 text-blue-700 ring-blue-100';
}

function renderingModeFallbackLabel(mode) {
    if (mode === 'safe_blade') {
        return 'SafeBlade';
    }

    if (mode === 'raw_code_permissioned') {
        return 'Raw code';
    }

    return 'Platform Blade';
}

function renderingModeFallbackDescription(mode) {
    if (mode === 'safe_blade') {
        return 'Dit blok gebruikt SafeBlade templates.';
    }

    if (mode === 'raw_code_permissioned') {
        return 'Dit blok rendert vertrouwde code en is alleen beschikbaar voor gebruikers met developerrechten.';
    }

    return 'Dit blok gebruikt vaste platformlogica.';
}

function hasEditorFields(block) {
    return blockEditorFields(block).length > 0;
}

function hasCodeEditorField(block) {
    return blockEditorFields(block).some((field) => field.type === 'code');
}

function fallbackEditorField(field) {
    const fields = {
        title: {
            name: 'title',
            type: 'text',
            label_key: 'components.block_editor.title',
            placeholder_key: 'components.block_editor.optional_title',
        },
        text: {
            name: 'text',
            type: 'textarea',
            label_key: 'components.block_editor.text',
            placeholder_key: 'components.block_editor.text_placeholder',
            rows: 4,
        },
        source: {
            name: 'source',
            type: 'text',
            label_key: 'components.block_editor.source',
            placeholder_key: 'components.block_editor.optional_source',
        },
        label: {
            name: 'label',
            type: 'text',
            label_key: 'components.block_editor.label',
            placeholder_key: 'components.block_editor.button_label_placeholder',
        },
        url: {
            name: 'url',
            type: 'text',
            label_key: 'components.block_editor.url',
            placeholder_key: 'components.block_editor.url_placeholder',
        },
        media_asset_id: {
            name: 'media_asset_id',
            type: 'media_select',
            label_key: 'components.block_editor.image',
        },
        caption: {
            name: 'caption',
            type: 'text',
            label_key: 'components.block_editor.caption',
            placeholder_key: 'components.block_editor.optional_caption',
        },
        form_translation_key: {
            name: 'form_translation_key',
            type: 'form_select',
            label_key: 'components.block_editor.form',
        },
        show_current: {
            name: 'show_current',
            type: 'checkbox',
            label_key: 'components.block_editor.show_current_page',
        },
        compact: {
            name: 'compact',
            type: 'checkbox',
            label_key: 'components.block_editor.compact_display',
        },
        limit: {
            name: 'limit',
            type: 'number',
            label_key: 'components.block_editor.limit',
            min: 1,
            max: 100,
        },
        show_search: {
            name: 'show_search',
            type: 'checkbox',
            label_key: 'components.block_editor.show_search',
        },
        code: {
            name: 'code',
            type: 'code',
            label_key: 'common.columns.code',
            rows: 8,
        },
        video_url: {
            name: 'video_url',
            type: 'text',
            label_key: 'components.block_editor.video_url',
            placeholder_key: 'components.block_editor.video_url_placeholder',
        },
        media_asset_ids: {
            name: 'media_asset_ids',
            type: 'media_list',
            label_key: 'components.block_editor.logo_images',
        },
    };

    return fields[field] || { name: field, type: 'text', label_key: null };
}

function editorFieldDefault(field, value = undefined) {
    if (field.type === 'media_list') {
        return Array.isArray(value) ? value : [];
    }

    if (field.type === 'repeater') {
        return normalizeRepeaterItems(value, field);
    }

    if (field.type === 'checkbox') {
        return Boolean(value ?? false);
    }

    if (field.type === 'number') {
        return value ?? null;
    }

    return value ?? '';
}

function serializedEditorFieldValue(field, value) {
    if (field.type === 'repeater') {
        return normalizeRepeaterItems(value, field)
            .map((item) => serializedRepeaterItem(item, field))
            .filter((item) => repeaterItemHasValue(item, field));
    }

    if (field.type === 'checkbox') {
        return Boolean(value);
    }

    return value ?? null;
}

function blockEditorGridClasses(block) {
    return [
        'grid gap-3 md:grid-cols-2',
        hasCodeEditorField(block)
            ? 'rounded-md border border-orange-100 bg-orange-50 p-3'
            : '',
    ];
}

function editorFieldWrapperClasses(field) {
    return [
        field.type === 'checkbox'
            ? 'flex items-center gap-2 text-sm'
            : 'grid gap-2',
        ['textarea', 'code', 'repeater'].includes(field.type)
            ? 'md:col-span-2'
            : '',
    ];
}

function editorTextareaClasses(field) {
    return field.type === 'code'
        ? 'min-h-44 rounded-md border border-orange-200 bg-white px-3 py-2 font-mono text-xs outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100'
        : 'min-h-24 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100';
}

function editorFieldLabel(field) {
    return field.label_key ? t(field.label_key, field.name) : field.name;
}

function editorFieldPlaceholder(field) {
    return field.placeholder_key ? t(field.placeholder_key, '') : '';
}

function editorFieldOptionLabel(option) {
    return option.label_key ? t(option.label_key, option.label) : option.label;
}

function normalizeRepeaterItems(value, field) {
    return Array.isArray(value)
        ? value
              .filter((item) => item && typeof item === 'object')
              .map((item) => normalizeRepeaterItem(item, field))
        : [];
}

function normalizeRepeaterItem(item, field, collapsedDefault = true) {
    return repeaterChildFields(field).reduce(
        (normalized, childField) => ({
            ...normalized,
            [childField.name]: item[childField.name] || '',
        }),
        {
            collapsed: Boolean(item.collapsed ?? collapsedDefault),
            uid:
                item.uid ||
                `item-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        },
    );
}

function serializedRepeaterItem(item, field) {
    return repeaterChildFields(field).reduce(
        (serialized, childField) => ({
            ...serialized,
            [childField.name]: item[childField.name] || null,
        }),
        {},
    );
}

function repeaterChildFields(field) {
    return Array.isArray(field.fields)
        ? field.fields.filter((childField) => childField?.name)
        : [];
}

function repeaterItemHasValue(item, field) {
    return repeaterChildFields(field).some(
        (childField) => item[childField.name],
    );
}

function repeaterItems(block, field) {
    if (!Array.isArray(block[field.name])) {
        block[field.name] = [];
    }

    return block[field.name];
}

function addRepeaterItem(block, field) {
    block[field.name] = [
        ...repeaterItems(block, field),
        {
            ...normalizeRepeaterItem({}, field, false),
        },
    ];
}

function mediaListValue(block, field) {
    if (!Array.isArray(block[field.name])) {
        block[field.name] = [];
    }

    return block[field.name];
}

function mediaListItems(block, field) {
    const ids = mediaListValue(block, field).map((id) => Number(id));

    return currentMediaOptions.value.filter((asset) =>
        ids.includes(Number(asset.id)),
    );
}

function availableMediaListOptions(block, field) {
    const ids = mediaListValue(block, field).map((id) => Number(id));

    return currentMediaOptions.value.filter(
        (asset) => !ids.includes(Number(asset.id)),
    );
}

function addMediaListItem(block, field, event) {
    const id = Number(event.target.value || 0);
    event.target.value = '';

    if (id <= 0) {
        return;
    }

    const ids = mediaListValue(block, field).map((value) => Number(value));

    if (!ids.includes(id)) {
        block[field.name] = [...ids, id];
    }
}

function removeMediaListItem(block, field, id) {
    block[field.name] = mediaListValue(block, field).filter(
        (value) => Number(value) !== Number(id),
    );
}

function placeableBlockLabel(block) {
    return (
        placeableBlockDefinition(block)?.name ||
        t('components.block_editor.block_fallback', 'Blok')
    );
}

function placeableBlockRendererKey(block) {
    return placeableBlockDefinition(block)?.renderer_key || '';
}

function placeableBlockOption(block) {
    const category = blockCategoryFromCatalog(block);

    return {
        value: Number(block.id),
        category,
        rendering_mode: block.rendering_mode || 'platform_blade',
        label: block.name || block.key,
        description: block.description || '',
    };
}

function blockPickerOptionDescription(option) {
    if (option.description) {
        return option.description;
    }

    if (option.rendering_mode === 'platform_blade') {
        return t(
            'layouts.sections.block_picker_platform_rendered',
            'Wordt door de vaste platform-rendering opgebouwd.',
        );
    }

    if (option.rendering_mode === 'raw_code_permissioned') {
        return t(
            'layouts.sections.block_picker_code_rendered',
            'Alleen voor vertrouwde codevelden.',
        );
    }

    return t(
        'layouts.sections.block_picker_safe_blade',
        'Rendert met de veilige SafeBlade-template.',
    );
}

function blockCategoryLabel(block) {
    return blockCategoryLabelByValue(blockCategory(block));
}

function blockCategoryLabelByValue(category) {
    const labels = {
        system: t('layouts.sections.block_category_system', 'Systeem'),
        code: t('layouts.sections.block_category_code', 'Code'),
        content: t('layouts.sections.block_category_content', 'Content'),
        header: t('layouts.sections.block_category_header', 'Header'),
        navigation: t(
            'layouts.sections.block_category_navigation',
            'Navigatie',
        ),
    };

    return labels[category] || labels.content;
}

function blockCategoryBadgeClasses(block) {
    const category = blockCategory(block);

    return [
        'rounded-full px-2 py-0.5 font-medium ring-1',
        category === 'system' ? 'bg-blue-50 text-blue-700 ring-blue-100' : '',
        category === 'code'
            ? 'bg-orange-50 text-orange-700 ring-orange-100'
            : '',
        !['system', 'code'].includes(category)
            ? 'bg-slate-100 text-slate-700 ring-slate-200'
            : '',
    ];
}

function blockUsageHint(block) {
    const category = blockCategory(block);

    if (category === 'system') {
        return t(
            'layouts.sections.block_hint_system',
            'Gebruikt globale site-instellingen.',
        );
    }

    if (category === 'code') {
        return t(
            'layouts.sections.block_hint_code',
            'Alleen voor vertrouwde snippets.',
        );
    }

    return t(
        'layouts.sections.block_hint_content',
        'Bewerk de zichtbare inhoud hieronder.',
    );
}

function placementAppearance(placement) {
    return placementDeviceStyle(placement, activeGridDevice.value).appearance;
}

function placementDeviceStyle(placement, device = 'desktop') {
    const styleConfig = placement?.style_config || {};
    const devices = styleConfig.devices || {};

    if (devices[device]) {
        return devices[device];
    }

    if (device !== 'desktop' && devices.desktop) {
        return devices.desktop;
    }

    return defaultPlacementDeviceStyle();
}

function placementEditorCardClasses(placement) {
    return [
        'grid gap-4 rounded-lg border p-4 transition',
        placement.is_active
            ? 'border-slate-200 bg-white shadow-sm'
            : 'border-slate-200 bg-slate-50 opacity-70',
    ];
}

function placementSummaryCardClasses(placement) {
    return [
        'grid gap-3 border p-3 text-sm text-slate-600',
        ...placementAppearanceClasses(placement),
    ];
}

function placementStatusLabel(placement) {
    return placement.is_active
        ? t('common.columns.active', 'Actief')
        : t('common.status.inactive', 'Inactief');
}

function placementStatusBadgeClasses(placement) {
    return [
        'rounded-full px-2 py-0.5 text-xs font-medium ring-1',
        placement.is_active
            ? 'bg-green-50 text-green-700 ring-green-100'
            : 'bg-slate-100 text-slate-500 ring-slate-200',
    ];
}

function placementVisibilitySummary(placement) {
    const visibleDevices = responsiveDeviceOptions
        .filter((device) => placement[`visible_${device.value}`])
        .map((device) => device.label);

    if (visibleDevices.length === 0) {
        return t('layouts.sections.visibility_none', 'Geen devices zichtbaar');
    }

    return t('layouts.sections.visibility_summary', 'Zichtbaar: :devices', {
        devices: visibleDevices.join(' · '),
    });
}

function placementLayoutSummary(placement) {
    const parts = responsiveDeviceOptions.map((device) => {
        const deviceLayout = placement.layout_config?.[device.value] || {};
        const span = Number(
            deviceLayout.w || placement[`${device.value}_span`] || 12,
        );

        return `${device.label} ${span}/12`;
    });

    return parts.join(' · ');
}

function placementAppearanceClasses(placement) {
    const appearance = placementAppearance(placement);

    return [
        appearance.background_color ? 'bg-white' : 'bg-slate-50',
        appearance.padding === 'sm' ? 'p-2' : '',
        appearance.padding === 'md' ? 'p-3' : '',
        appearance.padding === 'lg' ? 'p-4' : '',
        appearance.radius === 'none' ? 'rounded-none' : '',
        appearance.radius === 'sm' ? 'rounded-md' : '',
        ['inherit', 'md'].includes(appearance.radius) ? 'rounded-lg' : '',
        appearance.radius === 'lg' ? 'rounded-xl' : '',
        appearance.border === 'subtle' ? 'border-slate-200' : '',
        appearance.border === 'strong' ? 'border-slate-400' : '',
        appearance.border === 'primary' ? 'border-blue-300' : '',
        !['subtle', 'strong', 'primary'].includes(appearance.border)
            ? 'border-slate-100'
            : '',
        appearance.shadow === 'sm' ? 'shadow-sm' : '',
        appearance.shadow === 'md' ? 'shadow-md' : '',
        appearance.shadow === 'lg' ? 'shadow-lg' : '',
    ];
}

function placementStyleSummary(placement) {
    const appearance = placementAppearance(placement);
    const labels = [
        colorSummaryLabel(appearance.background_color),
        stylePresetLabel('padding', appearance.padding),
        stylePresetLabel('radius', appearance.radius),
        stylePresetLabel('border', appearance.border),
        stylePresetLabel('shadow', appearance.shadow),
    ].filter(Boolean);

    return labels.join(' · ');
}

function stylePresetLabel(group, value) {
    const labels = {
        padding: {
            sm: t('layouts.sections.appearance_size_sm', 'Klein'),
            md: t('layouts.sections.appearance_size_md', 'Normaal'),
            lg: t('layouts.sections.appearance_size_lg', 'Ruim'),
        },
        radius: {
            none: t('layouts.sections.appearance_radius_none', 'Geen hoeken'),
            sm: t('layouts.sections.appearance_radius_sm', 'Kleine hoeken'),
            md: t('layouts.sections.appearance_radius_md', 'Normale hoeken'),
            lg: t('layouts.sections.appearance_radius_lg', 'Ronde hoeken'),
        },
        border: {
            subtle: t('layouts.sections.appearance_border_subtle', 'Subtiel'),
            strong: t('layouts.sections.appearance_border_strong', 'Sterk'),
            primary: t('layouts.sections.appearance_border_primary', 'Accent'),
        },
        shadow: {
            sm: t('layouts.sections.appearance_shadow_sm', 'Kleine schaduw'),
            md: t('layouts.sections.appearance_shadow_md', 'Schaduw'),
            lg: t('layouts.sections.appearance_shadow_lg', 'Ruime schaduw'),
        },
    };

    return labels[group]?.[value] || '';
}

function colorSummaryLabel(value) {
    const color = normalizeHexColor(value);

    if (!color) {
        return t('layouts.colors.no_background', 'Geen achtergrond');
    }

    return t('layouts.colors.custom_background', 'Achtergrond :color', {
        color,
    });
}

function placeableBlockSort(left, right) {
    return (
        blockDefinitionPriority(left) - blockDefinitionPriority(right) ||
        left.registry_index - right.registry_index
    );
}

function blockDefinitionPriority(definition) {
    if (props.zone === 'content') {
        return 0;
    }

    const category = blockCategoryFromCatalog(definition);

    if (category === 'system') {
        return 0;
    }

    if (category === 'code') {
        return 1;
    }

    return 2;
}

function sectionTitle(section) {
    return (
        section.name ||
        t('layouts.sections.section_name_placeholder', 'Bijvoorbeeld bovenbalk')
    );
}

function blockPreviewTitle(placement) {
    if (!placement) {
        return t('components.block_editor.block_fallback', 'Blok');
    }

    const directTitle =
        placement.block.title ||
        placement.block.label ||
        placement.block.caption ||
        placement.block.name ||
        '';

    if (directTitle) {
        return directTitle;
    }

    const repeaterField = blockEditorFields(placement.block).find(
        (field) => field.type === 'repeater',
    );
    const firstItem = repeaterField
        ? normalizeRepeaterItems(
              placement.block[repeaterField.name],
              repeaterField,
          ).find((item) => repeaterItemHasValue(item, repeaterField))
        : null;

    if (firstItem) {
        const previewField = repeaterChildFields(repeaterField).find(
            (childField) => firstItem[childField.name],
        );

        if (previewField) {
            return firstItem[previewField.name];
        }
    }

    return placeableBlockLabel(placement.block);
}

function updateHoverCardCursor(event) {
    hoverCardCursor.value = {
        x: event.clientX,
        y: event.clientY,
    };
}

function placementHoverRows(placement) {
    if (!placement?.block) {
        return [];
    }

    const rows = [
        hoverRow(
            'block_type',
            t('layouts.sections.hover_block_type', 'Block type'),
            placeableBlockLabel(placement.block),
        ),
        hoverRow(
            'category',
            t('layouts.sections.hover_category', 'Category'),
            blockCategoryLabel(placement.block),
        ),
        ...dynamicFieldHoverRows(placement.block),
        ...blockConfiguredHoverRows(placement.block),
        hoverRow(
            'status',
            t('layouts.sections.hover_status', 'Status'),
            placementStatusLabel(placement),
        ),
        hoverRow(
            'visibility',
            t('layouts.sections.hover_visibility', 'Visibility'),
            placementVisibilitySummary(placement),
        ),
        hoverRow(
            'layout',
            t('layouts.sections.hover_layout', 'Layout'),
            placementLayoutSummary(placement),
        ),
        hoverRow(
            'style',
            t('layouts.sections.hover_style', 'Style'),
            placementStyleSummary(placement),
        ),
    ];

    return rows.filter(Boolean);
}

function dynamicFieldHoverRows(block) {
    if (placeableBlockRendererKey(block) !== 'dynamic_field') {
        return [];
    }

    const fieldKey = String(block.field_key || '').trim();
    const field = blockEditorFields(block).find(
        (editorField) => editorField.name === 'field_key',
    );

    return [
        hoverRow(
            'field_key',
            t('components.block_editor.field_key', 'Data field'),
            optionDisplayLabel(field?.options || [], fieldKey),
        ),
    ].filter(Boolean);
}

function blockConfiguredHoverRows(block) {
    const excludedFields = new Set(['field_key']);
    const rows = [];

    for (const field of blockEditorFields(block)) {
        if (!field?.name || excludedFields.has(field.name)) {
            continue;
        }

        const value = editorFieldHoverValue(block, field);
        const row = hoverRow(field.name, editorFieldLabel(field), value);

        if (row) {
            rows.push(row);
        }

        if (rows.length >= 4) {
            break;
        }
    }

    return rows;
}

function editorFieldHoverValue(block, field) {
    const value = block?.[field.name];

    if (field.type === 'checkbox') {
        return value
            ? t('common.boolean.yes', 'Yes')
            : t('common.boolean.no', 'No');
    }

    if (isBlankHoverValue(value)) {
        return '';
    }

    if (field.type === 'select') {
        return optionDisplayLabel(field.options || [], value);
    }

    if (field.type === 'form_select') {
        return optionDisplayLabel(props.formOptions, value);
    }

    if (field.type === 'media_select') {
        return mediaOptionDisplayLabel(value);
    }

    if (field.type === 'media_list') {
        return mediaListDisplayLabel(value);
    }

    if (field.type === 'repeater') {
        const count = normalizeRepeaterItems(value, field).filter((item) =>
            repeaterItemHasValue(item, field),
        ).length;

        return count > 0
            ? t('layouts.sections.hover_repeater_count', ':count items', {
                  count,
              })
            : '';
    }

    if (Array.isArray(value)) {
        return formatHoverValue(value.join(', '));
    }

    if (typeof value === 'object') {
        return '';
    }

    return formatHoverValue(value);
}

function hoverRow(key, label, value) {
    if (isBlankHoverValue(value)) {
        return null;
    }

    return {
        key,
        label,
        value: formatHoverValue(value),
    };
}

function isBlankHoverValue(value) {
    return (
        value === null ||
        value === undefined ||
        (typeof value === 'string' && value.trim() === '') ||
        (Array.isArray(value) && value.length === 0)
    );
}

function formatHoverValue(value) {
    const normalized = String(value ?? '')
        .replace(/\s+/g, ' ')
        .trim();

    return normalized.length > 140
        ? `${normalized.slice(0, 137).trim()}...`
        : normalized;
}

function optionDisplayLabel(options, value) {
    if (isBlankHoverValue(value)) {
        return '';
    }

    const normalizedValue = String(value);
    const option = (Array.isArray(options) ? options : []).find(
        (candidate) =>
            String(
                candidate?.value ?? candidate?.id ?? candidate?.key ?? '',
            ) === normalizedValue,
    );

    if (!option) {
        return normalizedValue;
    }

    const label = option.label_key
        ? t(option.label_key, option.label || normalizedValue)
        : option.label || option.title || option.name || normalizedValue;

    return label === normalizedValue
        ? normalizedValue
        : `${label} (${normalizedValue})`;
}

function mediaOptionDisplayLabel(value) {
    if (isBlankHoverValue(value)) {
        return '';
    }

    const normalizedValue = String(value);
    const mediaOption = currentMediaOptions.value.find(
        (option) => String(option.id ?? option.value ?? '') === normalizedValue,
    );

    return mediaOption
        ? mediaOption.label ||
              mediaOption.title ||
              mediaOption.name ||
              mediaOption.file_name ||
              mediaOption.filename ||
              `#${normalizedValue}`
        : `#${normalizedValue}`;
}

function mediaListDisplayLabel(value) {
    const ids = Array.isArray(value) ? value : [];
    const labels = ids.map((id) => mediaOptionDisplayLabel(id)).filter(Boolean);

    return labels.length > 0 ? labels.join(', ') : '';
}

function gridLayoutForSection(section) {
    return visiblePlacementsForGrid(section).map((placement, index) =>
        previewSizedGridItemForPlacement(
            placement,
            activeGridDevice.value,
            index,
        ),
    );
}

function visiblePlacementsForGrid(section) {
    return section.placements.filter((placement) =>
        placementVisibleOnDevice(placement, activeGridDevice.value),
    );
}

function hiddenPlacementsForGrid(section) {
    return section.placements.filter(
        (placement) =>
            !placementVisibleOnDevice(placement, activeGridDevice.value),
    );
}

function placementVisibleOnDevice(placement, device) {
    return Boolean(placement?.[`visible_${device}`] ?? true);
}

function gridLayoutKey(section) {
    const device = activeGridDevice.value;

    return [
        device,
        section.uid,
        section.placements
            .map((placement) =>
                [
                    placement.uid,
                    placementVisibleOnDevice(placement, device) ? 1 : 0,
                ].join(':'),
            )
            .join(','),
        ...section.placements.map((placement) => {
            const layout = placement.layout_config?.[device] || {};

            return [
                placement.uid,
                placement[`${device}_span`] || layout.w || 12,
            ].join(':');
        }),
    ].join('|');
}

function gridItemForPlacement(placement, device, index) {
    return gridItemForPlacementLayout(placement, device, index, {
        applyAlignment: true,
    });
}

function previewSizedGridItemForPlacement(placement, device, index) {
    const item = gridItemForPlacement(placement, device, index);
    const measuredRows = placementPreviewMeasuredRows.value[placement.uid];

    if (gridPreviewMode.value !== 'live' || !Number.isFinite(measuredRows)) {
        return item;
    }

    return {
        ...item,
        h: Math.max(1, measuredRows),
    };
}

function updateGridLayout(section, layout) {
    const device = activeGridDevice.value;

    layout.forEach((item) => {
        const placement = section.placements.find(
            (candidate) => candidate.uid === item.i,
        );

        if (!placement) {
            return;
        }

        const measuredRows = placementPreviewMeasuredRows.value[placement.uid];
        const currentLayout = placement.layout_config?.[device] || {};
        const nextLayout = {
            x: clampInteger(item.x ?? 0, 0, 11),
            y: Math.max(0, integerWithFallback(item.y, 0)),
            w: clampInteger(item.w ?? 12, 1, 12),
            h:
                gridPreviewMode.value === 'live' &&
                Number.isFinite(measuredRows)
                    ? Math.max(1, integerWithFallback(currentLayout.h, 1))
                    : Math.max(1, integerWithFallback(item.h, 1)),
        };

        if (
            Number(currentLayout.x ?? 0) === nextLayout.x &&
            Number(currentLayout.y ?? 0) === nextLayout.y &&
            Number(currentLayout.w ?? placement[`${device}_span`] ?? 12) ===
                nextLayout.w &&
            Number(currentLayout.h ?? 1) === nextLayout.h
        ) {
            return;
        }

        placement[`${device}_span`] = nextLayout.w;
        placement.layout_config = normalizePlacementLayoutConfig(
            {
                ...placement.layout_config,
                [device]: nextLayout,
            },
            placement,
        );
    });

    schedulePreviewMeasurements();
}

function placementForGridItem(section, item) {
    return section.placements.find((placement) => placement.uid === item.i);
}

function repeaterItemPreviewTitle(item, field) {
    const previewField = repeaterChildFields(field).find(
        (childField) => item[childField.name],
    );

    return previewField
        ? item[previewField.name]
        : t('components.block_editor.repeater_item_fallback', 'Nieuw item');
}

function defaultPlaceableBlockId() {
    if (props.zone === 'head') {
        return firstAvailablePlaceableBlockId([
            'site_head',
            'custom_head_code',
        ]);
    }

    if (props.zone === 'body_end') {
        return firstAvailablePlaceableBlockId(
            props.canManageCodeBlocks ? ['custom_body_end_code'] : ['text'],
        );
    }

    if (props.zone === 'content') {
        return firstAvailablePlaceableBlockId(['text']);
    }

    if (props.zone === 'header') {
        return firstAvailablePlaceableBlockId([
            'site_brand',
            'site_logo',
            'site_menu',
            'site_baseline',
            'site_language_switcher',
            'text',
        ]);
    }

    if (props.zone === 'footer') {
        return firstAvailablePlaceableBlockId([
            'site_brand',
            'site_logo',
            'site_menu',
            'text',
        ]);
    }

    return firstAvailablePlaceableBlockId(['text']);
}

function firstAvailablePlaceableBlockId(candidates) {
    const preferredBlock = placeableBlockOptions.value.find((option) =>
        candidates.includes(
            catalogBlocks.value.find(
                (block) => Number(block.id) === Number(option.value),
            )?.renderer_key,
        ),
    );

    return (
        preferredBlock?.value || placeableBlockOptions.value[0]?.value || null
    );
}

function blockCategoryFromCatalog(block) {
    return block?.category || block?.schema?.category || 'content';
}

function blockAllowedInPickerZone(block) {
    const category = blockCategoryFromCatalog(block);

    if (props.zone === 'head') {
        return ['system', 'code'].includes(category);
    }

    if (props.zone === 'body_end') {
        return category === 'code';
    }

    return true;
}
</script>

<style scoped>
:deep(.vgl-layout) {
    --vgl-placeholder-bg: rgb(37 99 235);
    --vgl-placeholder-opacity: 18%;
    --vgl-resizer-border-color: rgb(37 99 235);
    box-sizing: border-box;
    position: relative;
    transition: none;
}

:deep(.vgl-item) {
    box-sizing: border-box;
    position: absolute;
    transition: none;
}

:deep(.vgl-item--placeholder) {
    background-color: var(--vgl-placeholder-bg);
    opacity: var(--vgl-placeholder-opacity);
    user-select: none;
    z-index: 2;
}

:deep(.vgl-item--transform) {
    left: 0;
    right: auto;
    transition: none;
}

:deep(.vgl-item--dragging),
:deep(.vgl-item--resizing) {
    opacity: 0.92;
    transition: none;
    user-select: none;
    z-index: 3;
}

:deep(.vgl-item__resizer) {
    bottom: 0;
    box-sizing: border-box;
    cursor: se-resize;
    height: 10px;
    position: absolute;
    right: 0;
    width: 10px;
}

:deep(.vgl-item__resizer::before) {
    border: 0 solid var(--vgl-resizer-border-color);
    border-bottom-width: 2px;
    border-right-width: 2px;
    content: '';
    inset: 0 3px 3px 0;
    position: absolute;
}
</style>
