<template>
    <Head :title="t('meta.page_title', 'Database diagram')" />

    <AdminLayout :suppress-flash="true">
        <Card class="overflow-hidden rounded-none border border-slate-200 bg-white shadow-none">
            <CardHeader class="px-4 py-4 sm:px-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                            aria-hidden="true"
                        >
                            <span class="mdi mdi-database-search text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg font-semibold text-slate-900">
                                {{ t('page.title', 'Database diagram') }}
                            </CardTitle>
                            <CardDescription class="mt-1 text-sm text-slate-400">
                                {{
                                    t(
                                        'page.subtitle',
                                        'Technisch overzicht van tabellen, relaties en modelkoppelingen',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            class="border-slate-300 text-slate-950 shadow-none hover:bg-slate-50 hover:text-slate-950"
                            :aria-label="t('actions.back', 'Terug')"
                            :title="t('actions.back', 'Terug')"
                            @click="goBack"
                        >
                            <span class="mdi mdi-arrow-left-circle text-lg" />
                        </Button>
                        <RwActionButton
                            v-if="canUseSqlEditor"
                            :label="t('actions.sql_editor', 'SQL editor')"
                            icon="mdi-code-braces"
                            tone="new"
                            @click="openSqlEditor"
                        />
                        <RwActionButton
                            v-if="canExportFullDatabase"
                            :label="t('actions.backup', 'Backup')"
                            icon="mdi-backup-restore"
                            tone="neutral"
                            @click="backupDialogOpen = true"
                        />
                        <RwActionButton
                            v-if="canCreateTableDefinition"
                            :label="t('actions.table', 'Tabel')"
                            icon="mdi-plus-circle"
                            tone="new"
                            @click="openTableBuilder"
                        />
                    </div>
                </div>
            </CardHeader>

            <div class="border-t border-slate-200" />

            <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        v-if="canSwitchTableScope"
                        variant="outline"
                        size="sm"
                        class="shadow-none"
                        @click="toggleTableScope"
                    >
                        <i
                            :class="
                                tableScope === 'shared'
                                    ? 'mdi mdi-view-grid-outline mr-1'
                                    : 'mdi mdi-view-grid-plus mr-1'
                            "
                        />
                        {{
                            tableScope === 'shared'
                                ? t(
                                      'actions.show_application_tables',
                                      'Applicatie tabellen',
                                  )
                                : t(
                                      'actions.show_shared_tables',
                                      'Gemeenschappelijke tabellen',
                                  )
                        }}
                    </Button>

                    <Button
                        variant="outline"
                        size="sm"
                        class="shadow-none"
                        @click="toggleViewMode"
                    >
                        <i
                            :class="
                                viewMode === 'list'
                                    ? 'mdi mdi-view-grid mr-1'
                                    : 'mdi mdi-view-list mr-1'
                            "
                        />
                        {{
                            viewMode === 'list'
                                ? t('actions.view_diagram', 'Diagram')
                                : t('actions.view_list', 'Lijst')
                        }}
                    </Button>

                    <div
                        v-if="viewMode === 'diagram'"
                        class="ml-1 inline-flex items-center gap-1"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 w-8 p-0 shadow-none"
                            :title="t('diagram.sort_asc', 'Sorteer A-Z')"
                            :aria-label="t('diagram.sort_asc', 'Sorteer A-Z')"
                            @click="setSortDirection('asc')"
                        >
                            <i class="mdi mdi-sort-alphabetical-ascending" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 w-8 p-0 shadow-none"
                            :title="t('diagram.sort_desc', 'Sorteer Z-A')"
                            :aria-label="t('diagram.sort_desc', 'Sorteer Z-A')"
                            @click="setSortDirection('desc')"
                        >
                            <i class="mdi mdi-sort-alphabetical-descending" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 w-8 p-0 shadow-none"
                            :title="t('diagram.sort_manual', 'Vrije schikking')"
                            :aria-label="t('diagram.sort_manual', 'Vrije schikking')"
                            :class="{ 'bg-slate-100': isManualSort }"
                            @click="setSortDirection('manual')"
                        >
                            <i class="mdi mdi-drag-variant" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 w-8 p-0 shadow-none"
                            :title="t('diagram.zoom_out', 'Verkleinen')"
                            :aria-label="t('diagram.zoom_out', 'Verkleinen')"
                            :disabled="diagramZoom <= 0.5"
                            @click="zoomOut"
                        >
                            <i class="mdi mdi-magnify-minus-outline" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 w-8 p-0 shadow-none"
                            :title="t('diagram.zoom_in', 'Vergroten')"
                            :aria-label="t('diagram.zoom_in', 'Vergroten')"
                            :disabled="diagramZoom >= 2"
                            @click="zoomIn"
                        >
                            <i class="mdi mdi-magnify-plus-outline" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 w-8 p-0 shadow-none"
                            :title="
                                hasOpenSections
                                    ? t(
                                          'diagram.collapse_all',
                                          'Alles dichtklappen',
                                      )
                                    : t(
                                          'diagram.expand_all',
                                          'Alles openklappen',
                                    )
                            "
                            :aria-label="
                                hasOpenSections
                                    ? t(
                                          'diagram.collapse_all',
                                          'Alles dichtklappen',
                                      )
                                    : t(
                                          'diagram.expand_all',
                                          'Alles openklappen',
                                      )
                            "
                            @click="toggleAllSections"
                        >
                            <i
                                :class="
                                    hasOpenSections
                                        ? 'mdi mdi-arrow-collapse-all'
                                        : 'mdi mdi-arrow-expand-all'
                                "
                            />
                        </Button>
                    </div>
                </div>

            </div>

            <div v-if="pageFlash.message" class="px-4 pt-4 sm:px-5">
                <RwFlashMessage
                    :type="pageFlash.type"
                    :message="pageFlash.message"
                />
            </div>

            <CardContent class="grid gap-4 px-4 pb-5 pt-4 sm:px-5">
                <div class="grid w-full gap-2 rounded border border-slate-200 bg-slate-50 p-3">
                    <div
                        v-if="tableScope === 'shared'"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <Badge class="bg-orange-500 text-xs text-white">
                            {{
                                t(
                                    'search.shared_badge',
                                    'Gemeenschappelijke tabellen',
                                )
                            }}
                        </Badge>
                    </div>

                    <div class="grid w-full gap-1 sm:max-w-sm">
                        <label
                            for="db-diagram-search"
                            class="text-[11px] text-slate-600"
                        >
                            {{ t('search.title', 'Zoeken') }}
                        </label>
                        <Input
                            id="db-diagram-search"
                            v-model="search"
                            :placeholder="
                                t(
                                    'search.placeholder',
                                    'Zoek op tabelnaam, kolom of index...',
                                )
                            "
                            class="rw-flat-search-input h-9 bg-white"
                        />
                    </div>
                </div>

                <div
                    v-if="!hasAnyTables"
                    class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                >
                    <span>
                        {{
                            t(
                                'empty.prefix',
                                'Nog geen tabellen beschikbaar, maak uw eerste tabel aan door op de knop',
                            )
                        }}
                        <span
                            class="inline-flex items-center gap-1 align-middle"
                        >
                            <i class="mdi mdi-plus-circle text-base" />
                            {{ t('actions.table', 'Tabel') }}
                        </span>
                        {{ t('empty.suffix', 'te klikken.') }}
                    </span>
                </div>

                <div v-else-if="viewMode === 'diagram'" class="diagram-scroll">
                    <div
                        class="diagram-canvas"
                        ref="diagramContainerRef"
                        :style="diagramCanvasStyle"
                    >
                        <svg
                            class="diagram-connections"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <g
                                v-for="connection in activeConnections"
                                :key="connection.key"
                            >
                                <path
                                    :d="connection.d"
                                    class="connection-line"
                                />

                                <g
                                    v-if="connection.startBadge"
                                    :transform="`translate(${connection.startBadge.x}, ${connection.startBadge.y})`"
                                >
                                    <circle
                                        class="connection-badge-circle"
                                        r="7"
                                    />
                                    <text
                                        class="connection-badge-text"
                                        y="0"
                                        text-anchor="middle"
                                        dominant-baseline="central"
                                    >
                                        {{ connection.startBadge.label }}
                                    </text>
                                </g>

                                <g
                                    v-if="connection.endBadge"
                                    :transform="`translate(${connection.endBadge.x}, ${connection.endBadge.y})`"
                                >
                                    <circle
                                        class="connection-badge-circle"
                                        r="7"
                                    />
                                    <text
                                        class="connection-badge-text"
                                        y="0"
                                        text-anchor="middle"
                                        dominant-baseline="central"
                                    >
                                        {{ connection.endBadge.label }}
                                    </text>
                                </g>
                            </g>
                        </svg>

                        <Card
                            v-for="table in filteredTables"
                            :key="table.name"
                            :ref="setTableCardRef(table.name)"
                            class="diagram-node rw-flat-card-clear"
                            :draggable="canDragCards"
                            :class="{
                                'diagram-node-draggable': canDragCards,
                                'diagram-node-dragging':
                                    draggingTableName === table.name,
                            }"
                            @dragstart="onCardDragStart(table.name, $event)"
                            @dragover="onCardDragOver(table.name, $event)"
                            @drop="onCardDrop(table.name, $event)"
                            @dragend="onCardDragEnd"
                        >
                            <CardHeader class="pb-2 pt-3">
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <div class="flex min-w-0 items-start gap-2">
                                        <button
                                            v-if="showDragHandle"
                                            type="button"
                                            class="drag-handle"
                                            :class="{
                                                'drag-handle-disabled':
                                                    !canDragCards,
                                            }"
                                            :title="dragHandleTitle"
                                            :aria-label="dragHandleTitle"
                                        >
                                            <i class="mdi mdi-drag-vertical" />
                                        </button>

                                        <div>
                                            <CardTitle class="text-sm">
                                                {{ table.name }}
                                            </CardTitle>
                                            <CardDescription class="text-xs">
                                                {{ table.columns.length }}
                                                {{
                                                    t(
                                                        'units.columns',
                                                        'kolommen',
                                                    )
                                                }}
                                                |
                                                {{ table.foreign_keys.length }}
                                                {{
                                                    t(
                                                        'units.relations',
                                                        'relaties',
                                                    )
                                                }}
                                                |
                                                {{ table.indexes.length }}
                                                {{
                                                    t(
                                                        'units.indexes',
                                                        'indexen',
                                                    )
                                                }}
                                            </CardDescription>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-1">
                                        <Badge
                                            v-if="
                                                isEditBlockedTable(table.name)
                                            "
                                            class="readonly-chip"
                                        >
                                            <i class="mdi mdi-alert-circle" />
                                            {{
                                                t('badges.readonly', 'Readonly')
                                            }}
                                        </Badge>

                                        <Badge
                                            v-if="
                                                hasSharedAssignments(table.name)
                                            "
                                            class="shared-chip"
                                        >
                                            {{
                                                t(
                                                    'badges.shared',
                                                    'Gemeenschappelijk',
                                                )
                                            }}
                                        </Badge>

                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-7 w-7"
                                                    :title="
                                                        t(
                                                            'actions.table_actions',
                                                            'Tabel acties',
                                                        )
                                                    "
                                                    :aria-label="
                                                        t(
                                                            'actions.table_actions',
                                                            'Tabel acties',
                                                        )
                                                    "
                                                    @click.stop
                                                >
                                                    <i
                                                        class="mdi mdi-dots-vertical text-base"
                                                    />
                                                </Button>
                                            </DropdownMenuTrigger>

                                            <DropdownMenuContent
                                                align="end"
                                                class="w-44"
                                            >
                                                <DropdownMenuItem
                                                    v-if="
                                                        hasEditTableAction(
                                                            table.name,
                                                        )
                                                    "
                                                    as-child
                                                >
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center gap-2"
                                                        @click.stop="
                                                            openTableBuilderEdit(
                                                                table.name,
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            class="mdi mdi-pencil-box-multiple"
                                                        />
                                                        {{
                                                            t(
                                                                'actions.edit',
                                                                'Bewerken',
                                                            )
                                                        }}
                                                    </button>
                                                </DropdownMenuItem>

                                                <DropdownMenuItem
                                                    v-if="
                                                        hasModelCodeAction(
                                                            table.name,
                                                        )
                                                    "
                                                    as-child
                                                >
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center gap-2"
                                                        @click.stop="
                                                            openModelCodeDialog(
                                                                table.name,
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            class="mdi mdi-code-tags"
                                                        />
                                                        {{
                                                            t(
                                                                'actions.model_code',
                                                                'Model Code',
                                                            )
                                                        }}
                                                    </button>
                                                </DropdownMenuItem>

                                                <DropdownMenuItem
                                                    v-if="
                                                        canExportDatabaseContents
                                                    "
                                                    as-child
                                                >
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center gap-2"
                                                        @click.stop="
                                                            exportTableSql(
                                                                table.name,
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            class="mdi mdi-database-export"
                                                        />
                                                        {{
                                                            t(
                                                                'actions.export_sql',
                                                                'Exporteer SQL',
                                                            )
                                                        }}
                                                    </button>
                                                </DropdownMenuItem>

                                                <DropdownMenuItem
                                                    v-if="
                                                        canManageSharedTableAccess &&
                                                        isSharedTableName(
                                                            table.name,
                                                        )
                                                    "
                                                    as-child
                                                >
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center gap-2"
                                                        @click.stop="
                                                            openApplicationAccessDialog(
                                                                table.name,
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            class="mdi mdi-account-cog"
                                                        />
                                                        {{
                                                            t(
                                                                'actions.application_access',
                                                                'Applicatietoegang',
                                                            )
                                                        }}
                                                    </button>
                                                </DropdownMenuItem>

                                                <DropdownMenuSeparator
                                                    v-if="
                                                        hasDeleteTableAction(
                                                            table.name,
                                                        )
                                                    "
                                                />

                                                <DropdownMenuItem
                                                    v-if="
                                                        hasDeleteTableAction(
                                                            table.name,
                                                        )
                                                    "
                                                    as-child
                                                >
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center gap-2 text-red-600"
                                                        :disabled="
                                                            deletingTableName ===
                                                            table.name
                                                        "
                                                        @click.stop="
                                                            openDeleteTableDialog(
                                                                table.name,
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            class="mdi mdi-delete-forever"
                                                        />
                                                        {{
                                                            t(
                                                                'actions.delete',
                                                                'Verwijderen',
                                                            )
                                                        }}
                                                    </button>
                                                </DropdownMenuItem>

                                                <DropdownMenuItem
                                                    v-if="
                                                        !hasTableMenuActions(
                                                            table.name,
                                                        )
                                                    "
                                                    as-child
                                                >
                                                    <button
                                                        type="button"
                                                        disabled
                                                        class="flex w-full cursor-not-allowed items-center gap-2 text-slate-400"
                                                    >
                                                        <i
                                                            class="mdi mdi-information-outline"
                                                        />
                                                        {{
                                                            t(
                                                                'actions.no_actions',
                                                                'Geen acties',
                                                            )
                                                        }}
                                                    </button>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="grid gap-2 pb-3 text-xs">
                                <details
                                    v-if="table.columns.length"
                                    :data-columns-table="table.name"
                                    :open="
                                        isSectionOpen(
                                            sectionKey(table.name, 'columns'),
                                        )
                                    "
                                    @toggle="
                                        onSectionToggle(
                                            sectionKey(table.name, 'columns'),
                                            $event,
                                        )
                                    "
                                >
                                    <summary class="node-summary">
                                        <i
                                            class="mdi mdi-chevron-right summary-chevron"
                                            aria-hidden="true"
                                        />
                                        {{ t('sections.fields', 'Velden') }}
                                        ({{ table.columns.length }})
                                    </summary>
                                    <ul class="node-list">
                                        <li
                                            v-for="column in table.columns"
                                            :key="`${table.name}-${column.name}`"
                                            :id="
                                                fieldRowId(
                                                    table.name,
                                                    column.name,
                                                )
                                            "
                                            :data-field-row="
                                                fieldRowId(
                                                    table.name,
                                                    column.name,
                                                )
                                            "
                                            class="node-row"
                                            :class="{
                                                'field-row-active':
                                                    isFieldRowActive(
                                                        table.name,
                                                        column.name,
                                                    ),
                                            }"
                                        >
                                            <span
                                                class="node-row-title field-name font-mono text-slate-700"
                                            >
                                                {{ column.name }}
                                            </span>
                                            <span
                                                class="node-row-meta field-type text-slate-500"
                                            >
                                                {{
                                                    column.column_type ||
                                                    column.data_type
                                                }}
                                                <template
                                                    v-if="
                                                        String(
                                                            column.key || '',
                                                        ) !== ''
                                                    "
                                                >
                                                    · {{ column.key }}
                                                </template>
                                            </span>
                                        </li>
                                    </ul>
                                </details>

                                <details
                                    v-if="table.foreign_keys.length"
                                    :open="
                                        isSectionOpen(
                                            sectionKey(table.name, 'relations'),
                                        )
                                    "
                                    @toggle="
                                        onSectionToggle(
                                            sectionKey(table.name, 'relations'),
                                            $event,
                                        )
                                    "
                                >
                                    <summary class="node-summary">
                                        <i
                                            class="mdi mdi-chevron-right summary-chevron"
                                            aria-hidden="true"
                                        />
                                        {{
                                            t('sections.relations', 'Relaties')
                                        }}
                                        ({{ table.foreign_keys.length }})
                                    </summary>
                                    <ul class="node-list">
                                        <li
                                            v-for="foreignKey in table.foreign_keys"
                                            :key="`${table.name}-${foreignKey.column}-${foreignKey.referenced_table}`"
                                            class="node-row relation-row"
                                            :data-relation-row="
                                                relationKey(
                                                    table.name,
                                                    foreignKey,
                                                )
                                            "
                                            :class="{
                                                'relation-row-active':
                                                    isRelationActive(
                                                        table.name,
                                                        foreignKey,
                                                    ),
                                            }"
                                            @click="
                                                toggleTableRelation(
                                                    table.name,
                                                    foreignKey,
                                                )
                                            "
                                        >
                                            <span
                                                class="node-row-title font-mono text-slate-700"
                                            >
                                                {{ foreignKey.column }}
                                            </span>
                                            <span
                                                class="node-row-meta text-slate-500"
                                            >
                                                ->
                                                {{
                                                    foreignKey.referenced_table
                                                }}.{{
                                                    foreignKey.referenced_column
                                                }}
                                            </span>
                                        </li>
                                    </ul>
                                </details>

                                <details
                                    v-if="table.indexes.length"
                                    :open="
                                        isSectionOpen(
                                            sectionKey(table.name, 'indexes'),
                                        )
                                    "
                                    @toggle="
                                        onSectionToggle(
                                            sectionKey(table.name, 'indexes'),
                                            $event,
                                        )
                                    "
                                >
                                    <summary class="node-summary">
                                        <i
                                            class="mdi mdi-chevron-right summary-chevron"
                                            aria-hidden="true"
                                        />
                                        {{ t('sections.indexes', 'Indexen') }}
                                        ({{ table.indexes.length }})
                                    </summary>
                                    <ul class="node-list">
                                        <li
                                            v-for="index in table.indexes"
                                            :key="`${table.name}-${index.name}`"
                                            class="node-row"
                                        >
                                            <span
                                                class="node-row-title font-mono text-slate-700"
                                            >
                                                {{ index.name }}
                                            </span>
                                            <span
                                                class="node-row-meta text-slate-500"
                                            >
                                                {{ formatIndex(index) }}
                                            </span>
                                        </li>
                                    </ul>
                                </details>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <div v-else class="list-view-container">
                    <Card class="list-sidebar rw-flat-card-clear">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm">{{
                                t('stats.tables', 'Tabellen')
                            }}</CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'list.select_table',
                                        'Selecteer een tabel voor dataweergave',
                                    )
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="list-sidebar-content">
                            <button
                                v-for="table in filteredTables"
                                :key="`list-${table.name}`"
                                type="button"
                                class="table-list-item"
                                :class="{
                                    'table-list-item-active':
                                        selectedTableName === table.name,
                                }"
                                @click="selectedTableName = table.name"
                            >
                                <span class="table-list-title">{{
                                    table.name
                                }}</span>
                                <span class="table-list-details">
                                    <span class="table-list-meta">
                                        {{ table.columns.length }}
                                        {{ t('units.columns', 'kolommen') }}
                                    </span>
                                    <Badge
                                        v-if="hasSharedAssignments(table.name)"
                                        class="shared-chip table-list-chip"
                                    >
                                        {{
                                            t(
                                                'badges.shared',
                                                'Gemeenschappelijk',
                                            )
                                        }}
                                    </Badge>
                                </span>
                            </button>
                        </CardContent>
                    </Card>

                    <Card class="list-content rw-flat-card-clear overflow-hidden">
                        <CardHeader class="border-b border-slate-200">
                            <div
                                class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
                            >
                                <div>
                                    <CardTitle class="text-base">
                                        {{ selectedTableName }}
                                    </CardTitle>
                                    <CardDescription>
                                        {{
                                            t(
                                                'list.inline_description',
                                                'Inline lijstweergave met RWTable',
                                            )
                                        }}
                                    </CardDescription>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge
                                        v-if="!canUseEditModeOnSelected"
                                        class="readonly-chip"
                                    >
                                        <i class="mdi mdi-alert-circle" />
                                        {{ t('badges.readonly', 'Readonly') }}
                                    </Badge>
                                    <Badge
                                        v-if="
                                            hasSharedAssignments(
                                                selectedTableName,
                                            )
                                        "
                                        class="shared-chip"
                                    >
                                        {{
                                            t(
                                                'badges.shared',
                                                'Gemeenschappelijk',
                                            )
                                        }}
                                    </Badge>
                                    <Button
                                        v-if="canAddOnSelected"
                                        size="sm"
                                        variant="outline"
                                        class="shadow-none"
                                        @click="openCreateFormForSelected"
                                    >
                                        <i class="mdi mdi-plus-circle mr-1" />
                                        {{ t('actions.add', 'Toevoegen') }}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :disabled="!canUseEditModeOnSelected"
                                        :class="listModeToggleButtonClass"
                                        @click="toggleListMode"
                                    >
                                        <i
                                            :class="`${listModeToggleIcon} mr-1`"
                                        />
                                        {{ listModeToggleLabel }}
                                    </Button>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="shadow-none"
                                                :disabled="!selectedTableName"
                                            >
                                                <i
                                                    class="mdi mdi-dots-vertical mr-1"
                                                />
                                                {{
                                                    t(
                                                        'actions.actions',
                                                        'Acties',
                                                    )
                                                }}
                                            </Button>
                                        </DropdownMenuTrigger>

                                        <DropdownMenuContent
                                            align="end"
                                            class="w-48"
                                        >
                                            <DropdownMenuItem
                                                v-if="
                                                    hasEditTableAction(
                                                        selectedTableName,
                                                    )
                                                "
                                                as-child
                                            >
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2"
                                                    @click="
                                                        openTableBuilderEdit(
                                                            selectedTableName,
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="mdi mdi-pencil-box-multiple"
                                                    />
                                                    {{
                                                        t(
                                                            'actions.edit',
                                                            'Bewerken',
                                                        )
                                                    }}
                                                </button>
                                            </DropdownMenuItem>

                                            <DropdownMenuItem
                                                v-if="
                                                    hasModelCodeAction(
                                                        selectedTableName,
                                                    )
                                                "
                                                as-child
                                            >
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2"
                                                    @click="
                                                        openModelCodeDialog(
                                                            selectedTableName,
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="mdi mdi-code-tags"
                                                    />
                                                    {{
                                                        t(
                                                            'actions.model_code',
                                                            'Model Code',
                                                        )
                                                    }}
                                                </button>
                                            </DropdownMenuItem>

                                            <DropdownMenuItem
                                                v-if="
                                                    canExportDatabaseContents &&
                                                    selectedTableName
                                                "
                                                as-child
                                            >
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2"
                                                    @click="
                                                        exportTableSql(
                                                            selectedTableName,
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="mdi mdi-database-export"
                                                    />
                                                    {{
                                                        t(
                                                            'actions.export_sql',
                                                            'Exporteer SQL',
                                                        )
                                                    }}
                                                </button>
                                            </DropdownMenuItem>

                                            <DropdownMenuItem
                                                v-if="
                                                    canManageSharedTableAccess &&
                                                    selectedTableName &&
                                                    isSharedTableName(
                                                        selectedTableName,
                                                    )
                                                "
                                                as-child
                                            >
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2"
                                                    @click="
                                                        openApplicationAccessDialog(
                                                            selectedTableName,
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="mdi mdi-account-cog"
                                                    />
                                                    {{
                                                        t(
                                                            'actions.application_access',
                                                            'Applicatietoegang',
                                                        )
                                                    }}
                                                </button>
                                            </DropdownMenuItem>

                                            <DropdownMenuSeparator
                                                v-if="
                                                    hasDeleteTableAction(
                                                        selectedTableName,
                                                    )
                                                "
                                            />

                                            <DropdownMenuItem
                                                v-if="
                                                    hasDeleteTableAction(
                                                        selectedTableName,
                                                    )
                                                "
                                                as-child
                                            >
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center gap-2 text-red-600"
                                                    :disabled="
                                                        deletingTableName ===
                                                        selectedTableName
                                                    "
                                                    @click="
                                                        openDeleteTableDialog(
                                                            selectedTableName,
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="mdi mdi-delete-forever"
                                                    />
                                                    {{
                                                        t(
                                                            'actions.delete',
                                                            'Verwijderen',
                                                        )
                                                    }}
                                                </button>
                                            </DropdownMenuItem>

                                            <DropdownMenuItem
                                                v-if="
                                                    !hasTableMenuActions(
                                                        selectedTableName,
                                                    )
                                                "
                                                as-child
                                            >
                                                <button
                                                    type="button"
                                                    disabled
                                                    class="flex w-full cursor-not-allowed items-center gap-2 text-slate-400"
                                                >
                                                    <i
                                                        class="mdi mdi-information-outline"
                                                    />
                                                    {{
                                                        t(
                                                            'actions.no_actions',
                                                            'Geen acties',
                                                        )
                                                    }}
                                                </button>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="p-0">
                            <RwTable
                                v-if="selectedTableName"
                                :key="`db-list-${selectedTableName}-${listMode}`"
                                :data="{ data: [], total: 0 }"
                                :columns="listTableColumns"
                                :managed="true"
                                :start-on-mount="true"
                                :data-source="listDataSource"
                                :response-map="{
                                    data: 'data',
                                    total: 'total',
                                    current: 'current_page',
                                    last: 'last_page',
                                }"
                                :columns-param-mode="'none'"
                                :id-key="listPrimaryKey"
                                :inline-update-route="listInlineUpdateRoute"
                                :before-inline-update="beforeListInlineUpdate"
                                :inline-delete-route="listInlineDeleteRoute"
                                :before-inline-delete="beforeListInlineDelete"
                                :row-menu="listRowMenuEnabled"
                                :row-menu-items="listRowMenuItems"
                                :options="{ scrollMode: 'infinite' }"
                                :global-search="false"
                                :url-sync="'none'"
                                :row-quantity-select="false"
                                :horizontal-scroll="true"
                                :initial-height="'calc(100vh + 100px)'"
                                table-id="rw-db-diagram-list-view"
                            />
                        </CardContent>
                    </Card>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="applicationAccessDialogOpen">
            <DialogContent class="rw-flat-card-clear max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{
                        t(
                            'dialogs.application_access.title',
                            'Applicatietoegang',
                        )
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'dialogs.application_access.for_table',
                                'Beheer voor tabel',
                            )
                        }}
                        <span class="font-mono">{{ accessTableName }}</span>
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="shadow-none"
                            :disabled="applicationAccessSaving"
                            @click="closeApplicationAccessDialog"
                        >
                            <i class="mdi mdi-arrow-left-circle mr-1" />
                            {{ t('actions.back', 'Terug') }}
                        </Button>

                        <div class="ml-auto">
                            <RwActionButton
                                :label="t('actions.save', 'Bewaren')"
                                icon="mdi-content-save"
                                tone="save"
                                :loading="applicationAccessSaving"
                                :disabled="
                                    applicationAccessSaving || !accessTableName
                                "
                                @click="saveApplicationAccess"
                            />
                        </div>
                    </div>

                    <RwFlashMessage
                        v-if="applicationAccessSuccessMessage"
                        type="success"
                        :message="applicationAccessSuccessMessage"
                    />

                    <RwFlashMessage
                        v-if="applicationAccessErrorMessage"
                        type="danger"
                        :message="applicationAccessErrorMessage"
                    />

                    <div
                        v-if="applicationAccessRows.length === 0"
                        class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                    >
                        {{
                            t(
                                'dialogs.application_access.empty',
                                'Geen applicaties beschikbaar.',
                            )
                        }}
                    </div>

                    <div
                        v-else
                        class="max-h-72 overflow-auto rounded-md border border-slate-200"
                    >
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-slate-600">
                                    <th
                                        class="border-b border-slate-200 px-3 py-2 text-left font-semibold"
                                    >
                                        {{
                                            t(
                                                'dialogs.application_access.headers.application',
                                                'Applicatie',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="border-b border-slate-200 px-3 py-2 text-left font-semibold"
                                    >
                                        {{
                                            t(
                                                'dialogs.application_access.headers.show_in_application_tables',
                                                'Toon in applicatie tabellen',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="border-b border-slate-200 px-3 py-2 text-left font-semibold"
                                    >
                                        {{
                                            t(
                                                'dialogs.application_access.headers.allow_fk',
                                                'FK toegestaan',
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in applicationAccessRows"
                                    :key="`access-${row.application_id}`"
                                >
                                    <td
                                        class="border-b border-slate-100 px-3 py-2"
                                    >
                                        <div class="font-medium text-slate-800">
                                            {{ row.name }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ row.slug }}
                                        </div>
                                    </td>
                                    <td
                                        class="border-b border-slate-100 px-3 py-2"
                                    >
                                        <label
                                            class="inline-flex cursor-pointer items-center gap-2"
                                        >
                                            <input
                                                v-model="
                                                    row.show_in_application_scope
                                                "
                                                type="checkbox"
                                                :disabled="
                                                    applicationAccessSaving
                                                "
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            <span
                                                class="text-xs text-slate-600"
                                                >{{
                                                    t(
                                                        'dialogs.application_access.enable',
                                                        'Inschakelen',
                                                    )
                                                }}</span
                                            >
                                        </label>
                                    </td>
                                    <td
                                        class="border-b border-slate-100 px-3 py-2"
                                    >
                                        <label
                                            class="inline-flex cursor-pointer items-center gap-2"
                                        >
                                            <input
                                                v-model="row.allow_fk"
                                                type="checkbox"
                                                :disabled="
                                                    applicationAccessSaving
                                                "
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            <span
                                                class="text-xs text-slate-600"
                                                >{{
                                                    t(
                                                        'dialogs.application_access.enable',
                                                        'Inschakelen',
                                                    )
                                                }}</span
                                            >
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="modelCodeDialogOpen">
            <DialogContent class="rw-flat-card-clear max-w-6xl">
                <DialogHeader>
                    <DialogTitle>{{
                        t('dialogs.model_code.title', 'Model Code')
                    }}</DialogTitle>
                    <DialogDescription>
                        <span class="font-mono">{{ modelCodeTableName }}</span>
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="flex items-center gap-2 border-b border-slate-200 py-2"
                >
                    <RwActionButton
                        :label="t('actions.back', 'Terug')"
                        icon="mdi-arrow-left-circle"
                        tone="back"
                        :disabled="modelCodeLoading || modelCodeSaving"
                        @click="closeModelCodeDialog"
                    />

                    <div class="ml-auto flex items-center gap-2">
                        <RwActionButton
                            :label="t('actions.copy', 'Kopieren')"
                            icon="mdi-content-copy"
                            tone="new"
                            :disabled="
                                modelCodeActiveTab !== 'generated' ||
                                generatedCodeEditor.trim() === ''
                            "
                            @click="copyGeneratedCode"
                        />
                        <RwActionButton
                            :label="t('actions.save', 'Bewaren')"
                            icon="mdi-content-save"
                            tone="save"
                            :loading="modelCodeSaving"
                            :disabled="
                                modelCodeActiveTab !== 'model' ||
                                !modelCodeCanSave
                            "
                            @click="saveModelCode"
                        />
                    </div>
                </div>

                <RwFlashMessage
                    v-if="modelCodeFlash.message"
                    :type="modelCodeFlash.type"
                    :message="modelCodeFlash.message"
                />

                <div class="grid gap-3">
                    <div
                        class="grid gap-2 border-b border-slate-200 pb-2 md:grid-cols-[minmax(0,1fr)_auto] md:items-end"
                    >
                        <div class="grid gap-1.5">
                            <label
                                class="text-xs font-semibold text-slate-700"
                                >{{
                                    t(
                                        'dialogs.model_code.selector_label',
                                        'Model',
                                    )
                                }}</label
                            >
                            <select
                                v-model="modelCodeSelectedClass"
                                class="rw-flat-search-input w-full rounded-md px-2 pr-8"
                                :disabled="modelCodeLoading || modelCodeSaving"
                                @change="onModelCodeSelectionChange"
                            >
                                <option
                                    v-for="modelOption in modelCodeModels"
                                    :key="`model-code-option-${modelOption.class}`"
                                    :value="modelOption.class"
                                >
                                    {{
                                        modelOption.name +
                                        ' (' +
                                        modelOption.class +
                                        ')'
                                    }}
                                </option>
                            </select>
                        </div>

                        <div
                            class="inline-flex rounded border border-slate-300 bg-slate-50 p-1"
                        >
                            <button
                                type="button"
                                class="model-code-tab-btn"
                                :class="{
                                    'model-code-tab-btn-active':
                                        modelCodeActiveTab === 'model',
                                }"
                                @click="modelCodeActiveTab = 'model'"
                            >
                                {{
                                    t('dialogs.model_code.tabs.model', 'Model')
                                }}
                            </button>
                            <button
                                type="button"
                                class="model-code-tab-btn"
                                :class="{
                                    'model-code-tab-btn-active':
                                        modelCodeActiveTab === 'generated',
                                }"
                                @click="modelCodeActiveTab = 'generated'"
                            >
                                {{
                                    t(
                                        'dialogs.model_code.tabs.generated',
                                        'Generated',
                                    )
                                }}
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="modelCodeActiveTab === 'generated'"
                        class="text-[11px] text-orange-700"
                    >
                        {{
                            t(
                                'dialogs.model_code.generated_notice',
                                'Generated code is read-only en wordt automatisch herschreven door table builder.',
                            )
                        }}
                    </p>

                    <p
                        v-if="modelCodeDirty"
                        class="w-fit rounded bg-orange-100 px-2 py-1 text-[11px] font-medium text-orange-800"
                    >
                        {{
                            t(
                                'dialogs.model_code.unsaved',
                                'Niet bewaarde wijzigingen',
                            )
                        }}
                    </p>

                    <RwCodeEditor
                        v-if="modelCodeActiveTab === 'model'"
                        v-model="modelCodeEditor"
                        language="php"
                        height="620px"
                        theme="graphite"
                        :disabled="modelCodeLoading || modelCodeSaving"
                        :extensions="modelCodeEditorExtensions"
                    />
                    <RwCodeEditor
                        v-else
                        v-model="generatedCodeEditor"
                        language="php"
                        height="620px"
                        theme="graphite"
                        readonly
                        :extensions="modelCodeEditorExtensions"
                    />
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteTableDialogOpen">
            <DialogContent class="rw-flat-card-clear max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        t('dialogs.delete_table.title', 'Tabel verwijderen')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'dialogs.delete_table.description_prefix',
                                'Verwijder tabel',
                            )
                        }}
                        <span class="font-mono">{{
                            pendingDeleteTableName
                        }}</span>
                        {{
                            t(
                                'dialogs.delete_table.description_suffix',
                                'via migratie-first flow.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <RwFlashMessage
                        type="warning"
                        :message="
                            t(
                                'dialogs.delete_table.warning',
                                'Deze actie maakt een drop-table migratie en voert die standaard uit. Alle data in deze tabel gaat verloren.',
                            )
                        "
                    />

                    <div class="flex items-center gap-2">
                        <RwActionButton
                            :label="t('actions.back', 'Terug')"
                            icon="mdi-arrow-left-circle"
                            tone="back"
                            :disabled="deletingTableName !== ''"
                            @click="closeDeleteTableDialog"
                        />

                        <div class="ml-auto">
                            <RwActionButton
                                :label="t('actions.delete', 'Verwijderen')"
                                icon="mdi-delete-forever"
                                tone="delete"
                                :loading="deletingTableName !== ''"
                                :disabled="
                                    deletingTableName !== '' ||
                                    !pendingDeleteTableName
                                "
                                @click="confirmDeleteTable"
                            />
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <BackupDialog
            v-model="backupDialogOpen"
            :table-names="allTableNames"
            project-name="rwsoft"
            :can-view-logs="canViewDatabaseLogs"
        />
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RwActionButton from '@/Components/RwActionButton.vue';
import RwCodeEditor from '@/Components/RwCodeEditor.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwTable from '@/Components/RwTable.vue';
import BackupDialog from '@/Pages/Admin/RwDbDiagram/Partials/BackupDialog.vue';
import { autocompletion } from '@codemirror/autocomplete';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

const props = defineProps({
    tableSchema: {
        type: Object,
        default: () => ({ tables: [], edges: [] }),
    },
    modelSchema: {
        type: Object,
        default: () => ({ tables: [], edges: [] }),
    },
    canEditDatabaseContents: {
        type: Boolean,
        default: false,
    },
    canAddDatabaseContents: {
        type: Boolean,
        default: false,
    },
    canDeleteDatabaseContents: {
        type: Boolean,
        default: false,
    },
    canExportDatabaseContents: {
        type: Boolean,
        default: false,
    },
    canExportFullDatabase: {
        type: Boolean,
        default: false,
    },
    canViewDatabaseLogs: {
        type: Boolean,
        default: false,
    },
    canUseSqlEditor: {
        type: Boolean,
        default: false,
    },
    canCreateTableDefinition: {
        type: Boolean,
        default: false,
    },
    canEditTableDefinition: {
        type: Boolean,
        default: false,
    },
    canManageModelCode: {
        type: Boolean,
        default: false,
    },
    editBlockedTables: {
        type: Array,
        default: () => [],
    },
    nonEditableColumnsByTable: {
        type: Object,
        default: () => ({}),
    },
    tableScope: {
        type: String,
        default: 'application',
    },
    canSwitchTableScope: {
        type: Boolean,
        default: false,
    },
    canManageSharedTableAccess: {
        type: Boolean,
        default: false,
    },
    applicationPrefixes: {
        type: Array,
        default: () => [],
    },
    applicationAccessTargets: {
        type: Array,
        default: () => [],
    },
    sharedTableAccessByTable: {
        type: Object,
        default: () => ({}),
    },
    sidebarMenus: {
        type: Array,
        default: () => [],
    },
    activeTablePrefix: {
        type: String,
        default: '',
    },
});

const page = usePage();

const uiMessages = computed(() => {
    const messages = page.props?.app?.translations?.db_diagram_ui ?? {};

    return messages && typeof messages === 'object' ? messages : {};
});

function getNestedTranslation(source, key) {
    if (!source || typeof source !== 'object') {
        return null;
    }

    return String(key || '')
        .split('.')
        .filter((segment) => segment !== '')
        .reduce((carry, segment) => {
            if (!carry || typeof carry !== 'object') {
                return null;
            }

            if (!Object.prototype.hasOwnProperty.call(carry, segment)) {
                return null;
            }

            return carry[segment];
        }, source);
}

function interpolateTranslation(template, replacements = {}) {
    return Object.entries(replacements).reduce(
        (carry, [token, replacement]) => {
            return carry.replaceAll(`:${token}`, String(replacement ?? ''));
        },
        String(template || ''),
    );
}

function t(key, fallback = '', replacements = {}) {
    const translated = getNestedTranslation(uiMessages.value, key);
    const resolved =
        typeof translated === 'string' && translated.trim() !== ''
            ? translated
            : fallback || key;

    return interpolateTranslation(resolved, replacements);
}

const search = ref('');
const viewMode = ref('diagram');
const sortDirection = ref('asc');
const diagramZoom = ref(1);
const listMode = ref('view');
const selectedTableName = ref('');
const activeRelations = ref([]);
const openSections = ref({});
const tableOrder = ref([]);
const manualOrder = ref([]);
const draggingTableName = ref('');
const backupDialogOpen = ref(false);
const applicationAccessDialogOpen = ref(false);
const accessTableName = ref('');
const applicationAccessRows = ref([]);
const applicationAccessSaving = ref(false);
const applicationAccessSuccessMessage = ref('');
const applicationAccessErrorMessage = ref('');
const deleteTableDialogOpen = ref(false);
const pendingDeleteTableName = ref('');
const deletingTableName = ref('');
const modelCodeDialogOpen = ref(false);
const modelCodeLoading = ref(false);
const modelCodeSaving = ref(false);
const modelCodeTableName = ref('');
const modelCodeActiveTab = ref('model');
const modelCodeModels = ref([]);
const modelCodeSelectedClass = ref('');
const modelCodeEditor = ref('');
const modelCodeLoaded = ref('');
const generatedCodeEditor = ref('');
const modelCodeModelPath = ref('');
const modelCodeGeneratedPath = ref('');
const modelCodeFlash = ref({ type: '', message: '' });
const sharedAccessState = ref({
    ...(props.sharedTableAccessByTable || {}),
});

const diagramContainerRef = ref(null);
const tableCardRefs = new Map();
const tableCardLayouts = ref(new Map());
const fieldRowLayouts = ref(new Map());
let resizeObserver = null;

const orderStorageKey = 'rwdbdiagram:order:v2';
const manualOrderStorageKey = 'rwdbdiagram:order:manual:v2';
const sortStorageKey = 'rwdbdiagram:sort:v2';
const zoomStorageKey = 'rwdbdiagram:zoom:v2';
const viewModeStorageKey = 'rwdbdiagram:view-mode:v2';
const searchStorageKey = 'rwdbdiagram:search:v2';

const pageFlash = computed(() => {
    const flash = page.props?.flash || {};

    if (flash.error) {
        return { type: 'danger', message: flash.error };
    }

    if (flash.warning) {
        return { type: 'warning', message: flash.warning };
    }

    if (flash.status) {
        return { type: 'success', message: flash.status };
    }

    return { type: '', message: '' };
});

const allTables = computed(() => {
    return Array.isArray(props.tableSchema?.tables)
        ? props.tableSchema.tables
        : [];
});

const tableCount = computed(() => allTables.value.length);
const hasAnyTables = computed(() => tableCount.value > 0);
const allTableNames = computed(() =>
    allTables.value
        .map((table) => String(table?.name || ''))
        .filter((name) => name !== ''),
);

const relationCount = computed(() => {
    const edges = Array.isArray(props.tableSchema?.edges)
        ? props.tableSchema.edges
        : [];

    return edges.length;
});

const modelCount = computed(() => {
    const tables = Array.isArray(props.modelSchema?.tables)
        ? props.modelSchema.tables
        : [];

    return tables.reduce((carry, table) => {
        const models = Array.isArray(table.models) ? table.models.length : 0;

        return carry + models;
    }, 0);
});

const tableMap = computed(() => {
    const map = new Map();
    for (const table of allTables.value) {
        map.set(String(table?.name || ''), table);
    }

    return map;
});

const modelOptionsByTable = computed(() => {
    const map = new Map();
    const tables = Array.isArray(props.modelSchema?.tables)
        ? props.modelSchema.tables
        : [];

    for (const table of tables) {
        const tableName = String(table?.name || '').trim();
        if (tableName === '') {
            continue;
        }

        const models = Array.isArray(table?.models)
            ? table.models
                  .map((model) => ({
                      class: String(model?.class || '').trim(),
                      name: String(model?.name || '').trim(),
                  }))
                  .filter((model) => model.class !== '')
            : [];

        map.set(tableName, models);
    }

    return map;
});

const modelCodeDirty = computed(() => {
    return modelCodeEditor.value !== modelCodeLoaded.value;
});

const modelCodeCanSave = computed(() => {
    return (
        modelCodeSelectedClass.value.trim() !== '' &&
        !modelCodeLoading.value &&
        !modelCodeSaving.value
    );
});

const modelCodeCompletionOptions = [
    'class',
    'namespace',
    'use',
    'public',
    'protected',
    'private',
    'function',
    'return',
    'if',
    'else',
    'foreach',
    'array',
    'extends',
    'implements',
    'trait',
    'belongsTo',
    'hasMany',
    'hasOne',
    'belongsToMany',
    'morphTo',
    'morphMany',
    'casts',
    'rules',
    'customRules',
    'generatedRules',
    'generatedCasts',
    'fillable',
    'guarded',
    'table',
    'scope',
].map((label) => ({
    label,
    type: 'keyword',
}));

const modelCodeEditorExtensions = computed(() => [
    autocompletion({
        override: [modelCodeCompletionSource],
        activateOnTyping: true,
    }),
]);

const tableNames = computed(() =>
    allTables.value
        .map((table) => String(table?.name || ''))
        .filter((name) => name !== ''),
);

const sortedTableNames = computed(() =>
    [...tableNames.value].sort((left, right) =>
        left.localeCompare(right, 'nl', { sensitivity: 'base' }),
    ),
);

const sortedTableNamesDesc = computed(() =>
    [...sortedTableNames.value].reverse(),
);

const hasManualOrder = computed(() => {
    if (!tableNames.value.length) {
        return false;
    }

    const currentSet = new Set(tableNames.value);

    return manualOrder.value.some((name) => currentSet.has(name));
});

const isManualSort = computed(() => sortDirection.value === 'manual');

const orderedTableNames = computed(() => {
    if (sortDirection.value === 'desc') {
        return sortedTableNamesDesc.value;
    }

    if (sortDirection.value === 'manual') {
        const fallback = sortedTableNames.value;
        const normalized = normalizeOrder(manualOrder.value, fallback);

        return normalized.length ? normalized : fallback;
    }

    return sortedTableNames.value;
});

const orderedTables = computed(() =>
    orderedTableNames.value
        .map((name) => tableMap.value.get(name))
        .filter((table) => Boolean(table)),
);

const filteredTables = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (keyword === '') {
        return orderedTables.value;
    }

    return orderedTables.value.filter((table) => {
        const tableName = String(table?.name || '').toLowerCase();
        const columnNames = Array.isArray(table?.columns)
            ? table.columns.map((column) =>
                  String(column?.name || '').toLowerCase(),
              )
            : [];
        const indexNames = Array.isArray(table?.indexes)
            ? table.indexes.map((index) =>
                  String(index?.name || '').toLowerCase(),
              )
            : [];

        return (
            tableName.includes(keyword) ||
            columnNames.some((value) => value.includes(keyword)) ||
            indexNames.some((value) => value.includes(keyword))
        );
    });
});

const selectedTable = computed(() => {
    const fromFiltered = filteredTables.value.find(
        (table) => table.name === selectedTableName.value,
    );
    if (fromFiltered) {
        return fromFiltered;
    }

    return filteredTables.value[0] || null;
});

const canUseEditModeOnSelected = computed(() => {
    if (!selectedTable.value) {
        return false;
    }

    return (
        props.canEditDatabaseContents &&
        !isEditBlockedTable(String(selectedTable.value.name || ''))
    );
});

const canAddOnSelected = computed(() => {
    return (
        selectedTable.value &&
        listMode.value === 'edit' &&
        canUseEditModeOnSelected.value &&
        props.canAddDatabaseContents
    );
});

const listModeToggleLabel = computed(() => {
    return listMode.value === 'edit'
        ? t('list.mode_edit', 'Edit mode')
        : t('list.mode_view', 'View mode');
});

const listModeToggleIcon = computed(() => {
    return listMode.value === 'edit' ? 'mdi mdi-pencil' : 'mdi mdi-eye';
});

const listModeToggleButtonClass = computed(() => {
    if (listMode.value === 'edit') {
        return 'border-orange-300 bg-white text-orange-700 shadow-none hover:bg-orange-50 hover:text-orange-800 active:bg-orange-100';
    }

    return 'border-blue-300 bg-white text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800 active:bg-blue-100';
});

const listPrimaryKey = computed(() => {
    const table = selectedTable.value;
    if (!table || !Array.isArray(table.columns)) {
        return 'id';
    }

    const primary = table.columns.find(
        (column) => String(column?.key || '') === 'PRI',
    );
    if (primary?.name) {
        return String(primary.name);
    }

    const fallbackId = table.columns.find(
        (column) => String(column?.name || '') === 'id',
    );
    if (fallbackId?.name) {
        return String(fallbackId.name);
    }

    return String(table.columns[0]?.name || 'id');
});

const listDataSource = computed(() => {
    const tableName = String(selectedTable.value?.name || '');

    return {
        type: 'axios',
        path: route('admin.db-diagram.table-data', { table: tableName }),
        method: 'get',
    };
});

const listTableColumns = computed(() => {
    const table = selectedTable.value;
    if (!table || !Array.isArray(table.columns)) {
        return [];
    }

    const nonEditableSet = new Set(
        Array.isArray(props.nonEditableColumnsByTable?.[table.name])
            ? props.nonEditableColumnsByTable[table.name].map((column) =>
                  String(column),
              )
            : [],
    );

    return table.columns.map((column) => {
        const key = String(column?.name || '');
        const editable =
            listMode.value === 'edit' &&
            canUseEditModeOnSelected.value &&
            !nonEditableSet.has(key);

        return {
            key,
            label: key,
            type: inferTableColumnType(column),
            sortable: true,
            selected: true,
            editable,
            validationType: editable ? 'client' : undefined,
            validationRules: editable ? 'nullable' : undefined,
        };
    });
});

const listInlineUpdateRoute = (id) => {
    const tableName = String(selectedTable.value?.name || '');
    if (
        !tableName ||
        listMode.value !== 'edit' ||
        !canUseEditModeOnSelected.value
    ) {
        return null;
    }

    return route('admin.db-diagram.table-edit', { table: tableName, id });
};

const listInlineDeleteRoute = (id) => {
    const tableName = String(selectedTable.value?.name || '');
    if (
        !tableName ||
        listMode.value !== 'edit' ||
        !canUseEditModeOnSelected.value ||
        !props.canDeleteDatabaseContents
    ) {
        return null;
    }

    return route('admin.db-diagram.table-delete', { table: tableName, id });
};

const listRowMenuEnabled = computed(() => {
    return listMode.value === 'edit' && canUseEditModeOnSelected.value;
});

const listRowMenuItems = computed(() => {
    if (!listRowMenuEnabled.value) {
        return [];
    }

    const items = [
        {
            key: 'edit',
            label: t('actions.edit', 'Bewerken'),
            icon: 'mdi-pencil',
        },
    ];

    if (props.canDeleteDatabaseContents) {
        items.push({
            key: 'delete',
            label: t('actions.delete', 'Verwijderen'),
            icon: 'mdi-delete',
            color: 'red',
        });
    }

    return items;
});

const hasOpenSections = computed(() => {
    const values = Object.values(openSections.value);

    return values.some((value) => value === true);
});

const showDragHandle = computed(
    () => isManualSort.value || !hasManualOrder.value,
);

const canDragCards = computed(
    () => viewMode.value === 'diagram' && sortDirection.value === 'manual',
);

const dragHandleTitle = computed(() => {
    if (!canDragCards.value) {
        return t(
            'diagram.drag_disabled',
            'Schakel naar vrije schikking om te verslepen',
        );
    }

    return t('diagram.drag_enabled', 'Sleep om te verplaatsen');
});

const diagramCanvasStyle = computed(() => ({
    transform: `scale(${diagramZoom.value})`,
    transformOrigin: 'top left',
    width: `${100 / diagramZoom.value}%`,
}));

const activeConnections = computed(() => {
    const connections = [];

    for (const relation of activeRelations.value) {
        const fromFieldLayout = fieldRowLayouts.value.get(
            fieldRowId(relation.fromTable, relation.fromField),
        );
        const toFieldLayout = fieldRowLayouts.value.get(
            fieldRowId(relation.toTable, relation.toField),
        );

        if (!fromFieldLayout || !toFieldLayout) {
            continue;
        }

        const leftToRight = fromFieldLayout.left <= toFieldLayout.left;
        const startX = leftToRight
            ? fromFieldLayout.right
            : fromFieldLayout.left;
        const endX = leftToRight ? toFieldLayout.left : toFieldLayout.right;
        const startY = fromFieldLayout.centerY;
        const endY = toFieldLayout.centerY;

        const horizontalDistance = Math.abs(endX - startX);
        const verticalDistance = Math.abs(endY - startY);
        const curveOffset = Math.max(
            horizontalDistance * 0.5,
            verticalDistance * 0.25,
            120,
        );

        const c1x = leftToRight ? startX + curveOffset : startX - curveOffset;
        const c2x = leftToRight ? endX - curveOffset : endX + curveOffset;

        connections.push({
            key: relation.key,
            d: `M ${startX} ${startY} C ${c1x} ${startY}, ${c2x} ${endY}, ${endX} ${endY}`,
            startBadge: {
                x: startX,
                y: fromFieldLayout.top,
                label: formatRelationEndpointLabel(relation.startType),
            },
            endBadge: {
                x: endX,
                y: toFieldLayout.top,
                label: formatRelationEndpointLabel(relation.endType),
            },
        });
    }

    return connections;
});

watch(
    filteredTables,
    (tables) => {
        if (!tables.length) {
            selectedTableName.value = '';
            activeRelations.value = [];

            return;
        }

        const hasSelected = tables.some(
            (table) => table.name === selectedTableName.value,
        );
        if (!hasSelected) {
            selectedTableName.value = String(tables[0].name || '');
        }

        const availableTableNames = new Set(
            tables.map((table) => String(table.name || '')),
        );
        activeRelations.value = activeRelations.value.filter((relation) => {
            return (
                availableTableNames.has(relation.fromTable) &&
                availableTableNames.has(relation.toTable)
            );
        });

        refreshDiagramLayout();
    },
    { immediate: true },
);

watch(viewMode, () => {
    refreshDiagramLayout();
});

watch(sortDirection, (direction) => {
    if (!['asc', 'desc', 'manual'].includes(direction)) {
        sortDirection.value = 'asc';

        return;
    }

    saveLocalStorage(sortStorageKey, direction);
    refreshDiagramLayout();
});

watch(diagramZoom, (value) => {
    const normalized = clampZoom(value);
    if (normalized !== value) {
        diagramZoom.value = normalized;

        return;
    }

    saveLocalStorage(zoomStorageKey, String(diagramZoom.value));
    refreshDiagramLayout();
});

watch(tableNames, (names) => {
    const fallback = [...sortedTableNames.value];
    tableOrder.value = normalizeOrder(tableOrder.value, fallback);
    manualOrder.value = normalizeOrder(manualOrder.value, fallback);

    saveLocalStorage(orderStorageKey, JSON.stringify(tableOrder.value));
    saveLocalStorage(manualOrderStorageKey, JSON.stringify(manualOrder.value));

    if (!names.includes(selectedTableName.value)) {
        selectedTableName.value = names[0] || '';
    }
});

watch(modelCodeDialogOpen, (open) => {
    if (!open) {
        closeModelCodeDialog();
    }
});

watch(canUseEditModeOnSelected, (canEdit) => {
    if (!canEdit) {
        listMode.value = 'view';
    }
});

watch(
    () => props.sharedTableAccessByTable,
    (value) => {
        sharedAccessState.value = {
            ...(value || {}),
        };
    },
    { deep: true },
);

watch(viewMode, (value) => {
    saveLocalStorage(viewModeStorageKey, value);
});

watch(search, (value) => {
    saveLocalStorage(searchStorageKey, String(value || ''));
});

onMounted(() => {
    const defaultOrder = [...sortedTableNames.value];
    tableOrder.value = normalizeOrder(
        loadLocalStorageArray(orderStorageKey),
        defaultOrder,
    );
    manualOrder.value = normalizeOrder(
        loadLocalStorageArray(manualOrderStorageKey),
        defaultOrder,
    );

    const storedSortDirection = loadLocalStorage(sortStorageKey);
    if (storedSortDirection === 'asc' || storedSortDirection === 'desc') {
        sortDirection.value = storedSortDirection;
    } else if (storedSortDirection === 'manual' && hasManualOrder.value) {
        sortDirection.value = 'manual';
    }

    const storedZoom = Number(loadLocalStorage(zoomStorageKey) || '1');
    diagramZoom.value = clampZoom(Number.isNaN(storedZoom) ? 1 : storedZoom);

    const storedViewMode = loadLocalStorage(viewModeStorageKey);
    if (storedViewMode === 'diagram' || storedViewMode === 'list') {
        viewMode.value = storedViewMode;
    }

    search.value = loadLocalStorage(searchStorageKey);

    refreshDiagramLayout();
    attachResizeObserver();
    window.addEventListener('resize', refreshDiagramLayout);
});

onBeforeUnmount(() => {
    if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver = null;
    }
    window.removeEventListener('resize', refreshDiagramLayout);
});

function inferTableColumnType(column) {
    const dbType = String(
        column?.data_type || column?.type || '',
    ).toLowerCase();

    if (
        dbType.includes('int') ||
        dbType.includes('decimal') ||
        dbType.includes('float')
    ) {
        return 'number';
    }

    if (dbType.includes('bool')) {
        return 'boolean';
    }

    if (
        dbType.includes('date') ||
        dbType.includes('time') ||
        dbType.includes('year')
    ) {
        return 'date';
    }

    return 'text';
}

function setTableCardRef(tableName) {
    return (element) => {
        if (!element) {
            tableCardRefs.delete(tableName);

            return;
        }

        const resolved = Array.isArray(element) ? element[0] : element;
        const node = resolved?.$el || resolved;
        if (!node) {
            tableCardRefs.delete(tableName);

            return;
        }

        tableCardRefs.set(tableName, node);
    };
}

function attachResizeObserver() {
    if (
        typeof window === 'undefined' ||
        typeof window.ResizeObserver === 'undefined'
    ) {
        return;
    }

    resizeObserver = new window.ResizeObserver(() => {
        refreshDiagramLayout();
    });

    if (diagramContainerRef.value) {
        resizeObserver.observe(diagramContainerRef.value);
    }
}

function refreshDiagramLayout() {
    if (viewMode.value !== 'diagram') {
        return;
    }

    nextTick(() => {
        const container = diagramContainerRef.value;
        if (!container) {
            return;
        }

        const containerRect = container.getBoundingClientRect();
        const scale = diagramZoom.value || 1;
        const nodeMap = new Map();

        for (const [tableName, element] of tableCardRefs.entries()) {
            const rect = element.getBoundingClientRect();

            nodeMap.set(tableName, {
                left: (rect.left - containerRect.left) / scale,
                right: (rect.right - containerRect.left) / scale,
                top: (rect.top - containerRect.top) / scale,
                bottom: (rect.bottom - containerRect.top) / scale,
                width: rect.width / scale,
                height: rect.height / scale,
            });
        }

        const rowMap = new Map();
        const fieldRows = container.querySelectorAll('[data-field-row]');
        for (const fieldRow of fieldRows) {
            if (!isElementVisibleInOpenedDetails(fieldRow)) {
                continue;
            }

            const fieldKey = String(fieldRow.dataset.fieldRow || '');
            if (fieldKey === '') {
                continue;
            }

            const rect = fieldRow.getBoundingClientRect();
            rowMap.set(fieldKey, {
                left: (rect.left - containerRect.left) / scale,
                right: (rect.right - containerRect.left) / scale,
                top: (rect.top - containerRect.top) / scale,
                bottom: (rect.bottom - containerRect.top) / scale,
                centerY:
                    (rect.top - containerRect.top + rect.height / 2) / scale,
                width: rect.width / scale,
                height: rect.height / scale,
            });
        }

        tableCardLayouts.value = nodeMap;
        fieldRowLayouts.value = rowMap;
    });
}

function isElementVisibleInOpenedDetails(element) {
    let current = element;

    while (current) {
        if (
            current.tagName === 'DETAILS' &&
            current.hasAttribute('data-columns-table') &&
            !current.open
        ) {
            return false;
        }

        current = current.parentElement;
    }

    return true;
}

function sanitizeDiagramId(value) {
    return String(value || '').replace(/[^a-zA-Z0-9_-]/g, '_');
}

function fieldRowId(tableName, fieldName) {
    return `db-field-${sanitizeDiagramId(tableName)}-${sanitizeDiagramId(fieldName)}`;
}

function sectionKey(tableName, section) {
    return `table|${String(tableName)}|${String(section)}`;
}

function isSectionOpen(key) {
    return Boolean(openSections.value[key]);
}

function onSectionToggle(key, event) {
    const isOpen = Boolean(event?.target?.open);
    openSections.value = {
        ...openSections.value,
        [key]: isOpen,
    };

    refreshDiagramLayout();
}

function getAllSectionKeysForCurrentTables() {
    const keys = [];
    for (const table of filteredTables.value) {
        const tableName = String(table?.name || '');
        if (tableName === '') {
            continue;
        }

        if (Array.isArray(table?.columns) && table.columns.length > 0) {
            keys.push(sectionKey(tableName, 'columns'));
        }

        if (
            Array.isArray(table?.foreign_keys) &&
            table.foreign_keys.length > 0
        ) {
            keys.push(sectionKey(tableName, 'relations'));
        }

        if (Array.isArray(table?.indexes) && table.indexes.length > 0) {
            keys.push(sectionKey(tableName, 'indexes'));
        }
    }

    return keys;
}

function ensureColumnsSectionOpen(tableName) {
    const key = sectionKey(tableName, 'columns');
    openSections.value = {
        ...openSections.value,
        [key]: true,
    };
}

function formatRelationEndpointLabel(type) {
    return type === 'many' ? '∞' : '1';
}

function relationKey(fromTable, foreignKey) {
    return `${fromTable}.${String(foreignKey?.column || '')}->${String(
        foreignKey?.referenced_table || '',
    )}.${String(foreignKey?.referenced_column || '')}`;
}

function toggleTableRelation(fromTable, foreignKey) {
    const fromField = String(foreignKey?.column || '');
    const toTable = String(foreignKey?.referenced_table || '');
    const toField = String(foreignKey?.referenced_column || '');
    if (toTable === '') {
        return;
    }

    const key = relationKey(fromTable, foreignKey);
    const index = activeRelations.value.findIndex(
        (relation) => relation.key === key,
    );
    if (index >= 0) {
        activeRelations.value.splice(index, 1);

        return;
    }

    activeRelations.value.push({
        key,
        fromTable,
        fromField,
        toTable,
        toField,
        startType: 'many',
        endType: 'one',
    });

    ensureColumnsSectionOpen(fromTable);
    ensureColumnsSectionOpen(toTable);

    refreshDiagramLayout();
}

function isRelationActive(fromTable, foreignKey) {
    const key = relationKey(fromTable, foreignKey);

    return activeRelations.value.some((relation) => relation.key === key);
}

function isFieldRowActive(tableName, fieldName) {
    return activeRelations.value.some((relation) => {
        return (
            (relation.fromTable === tableName &&
                relation.fromField === fieldName) ||
            (relation.toTable === tableName && relation.toField === fieldName)
        );
    });
}

function formatIndex(index) {
    const columns = Array.isArray(index?.columns)
        ? index.columns.join(', ')
        : '';
    const type = index?.type ? String(index.type).toUpperCase() : '';

    if (type && columns) {
        return `${type}: ${columns}`;
    }

    return columns || type || '-';
}

function isEditBlockedTable(tableName) {
    return props.editBlockedTables.includes(tableName);
}

function setSortDirection(direction) {
    if (!['asc', 'desc', 'manual'].includes(direction)) {
        return;
    }

    sortDirection.value = direction;

    if (direction === 'manual') {
        if (!manualOrder.value.length) {
            manualOrder.value = [...tableOrder.value];
            saveLocalStorage(
                manualOrderStorageKey,
                JSON.stringify(manualOrder.value),
            );
        }

        return;
    }

    const nextOrder =
        direction === 'desc'
            ? [...sortedTableNamesDesc.value]
            : [...sortedTableNames.value];

    tableOrder.value = nextOrder;
    saveLocalStorage(orderStorageKey, JSON.stringify(nextOrder));
}

function zoomIn() {
    diagramZoom.value = clampZoom(
        Math.round((diagramZoom.value + 0.1) * 10) / 10,
    );
}

function zoomOut() {
    diagramZoom.value = clampZoom(
        Math.round((diagramZoom.value - 0.1) * 10) / 10,
    );
}

function collapseAllSections() {
    activeRelations.value = [];
    openSections.value = {};
}

function expandAllSections() {
    const keys = getAllSectionKeysForCurrentTables();
    const nextSections = {};
    for (const key of keys) {
        nextSections[key] = true;
    }
    openSections.value = nextSections;

    const nextRelations = [];
    for (const table of filteredTables.value) {
        const fromTable = String(table?.name || '');
        if (!fromTable || !Array.isArray(table?.foreign_keys)) {
            continue;
        }

        for (const foreignKey of table.foreign_keys) {
            const toTable = String(foreignKey?.referenced_table || '');
            const toField = String(foreignKey?.referenced_column || '');
            const fromField = String(foreignKey?.column || '');
            if (toTable === '' || fromField === '') {
                continue;
            }

            nextRelations.push({
                key: relationKey(fromTable, foreignKey),
                fromTable,
                fromField,
                toTable,
                toField,
                startType: 'many',
                endType: 'one',
            });
        }
    }

    activeRelations.value = nextRelations;
}

function toggleAllSections() {
    if (hasOpenSections.value) {
        collapseAllSections();
    } else {
        expandAllSections();
    }

    refreshDiagramLayout();
}

function onCardDragStart(tableName, event) {
    if (!canDragCards.value) {
        event.preventDefault();

        return;
    }

    draggingTableName.value = String(tableName || '');

    if (event?.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', draggingTableName.value);
    }
}

function onCardDragOver(tableName, event) {
    if (!canDragCards.value || draggingTableName.value === '') {
        return;
    }

    if (event?.preventDefault) {
        event.preventDefault();
    }

    if (
        String(tableName || '') !== draggingTableName.value &&
        event?.dataTransfer
    ) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onCardDrop(targetTableName, event) {
    if (!canDragCards.value) {
        return;
    }

    if (event?.preventDefault) {
        event.preventDefault();
    }

    const sourceName =
        draggingTableName.value ||
        String(event?.dataTransfer?.getData('text/plain') || '');
    const targetName = String(targetTableName || '');

    if (!sourceName || !targetName || sourceName === targetName) {
        return;
    }

    const baseOrder = normalizeOrder(
        manualOrder.value.length ? manualOrder.value : tableOrder.value,
        sortedTableNames.value,
    );

    const sourceIndex = baseOrder.indexOf(sourceName);
    const targetIndex = baseOrder.indexOf(targetName);
    if (sourceIndex < 0 || targetIndex < 0) {
        return;
    }

    const nextOrder = [...baseOrder];
    const [moved] = nextOrder.splice(sourceIndex, 1);
    nextOrder.splice(targetIndex, 0, moved);

    tableOrder.value = nextOrder;
    manualOrder.value = nextOrder;
    sortDirection.value = 'manual';

    saveLocalStorage(orderStorageKey, JSON.stringify(nextOrder));
    saveLocalStorage(manualOrderStorageKey, JSON.stringify(nextOrder));
}

function onCardDragEnd() {
    draggingTableName.value = '';
}

function goBack() {
    router.visit(route('admin'));
}

function toggleViewMode() {
    viewMode.value = viewMode.value === 'diagram' ? 'list' : 'diagram';
}

function toggleTableScope() {
    if (!props.canSwitchTableScope) {
        return;
    }

    router.get(
        route('admin.db-diagram'),
        {
            table_scope:
                props.tableScope === 'shared' ? 'application' : 'shared',
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function setListMode(mode) {
    if (mode === 'edit' && !canUseEditModeOnSelected.value) {
        return;
    }

    listMode.value = mode === 'edit' ? 'edit' : 'view';
}

function toggleListMode() {
    if (!canUseEditModeOnSelected.value) {
        listMode.value = 'view';

        return;
    }

    setListMode(listMode.value === 'edit' ? 'view' : 'edit');
}

function openSqlEditor() {
    router.visit(route('admin.db-diagram.sql-editor'));
}

function openTableBuilder() {
    return;
}

function openTableBuilderEdit(tableName) {
    const normalized = String(tableName || '').trim();
    if (!hasEditTableAction(normalized)) {
        return;
    }

    return;
}

async function openModelCodeDialog(tableName) {
    const normalized = String(tableName || '').trim();
    if (!hasModelCodeAction(normalized)) {
        return;
    }

    modelCodeDialogOpen.value = true;
    modelCodeTableName.value = normalized;
    modelCodeActiveTab.value = 'model';
    modelCodeFlash.value = { type: '', message: '' };

    await loadModelCode(normalized, '');
}

function closeModelCodeDialog() {
    if (modelCodeLoading.value || modelCodeSaving.value) {
        return;
    }

    modelCodeDialogOpen.value = false;
    modelCodeLoading.value = false;
    modelCodeSaving.value = false;
    modelCodeTableName.value = '';
    modelCodeActiveTab.value = 'model';
    modelCodeModels.value = [];
    modelCodeSelectedClass.value = '';
    modelCodeEditor.value = '';
    modelCodeLoaded.value = '';
    generatedCodeEditor.value = '';
    modelCodeModelPath.value = '';
    modelCodeGeneratedPath.value = '';
    modelCodeFlash.value = { type: '', message: '' };
}

async function loadModelCode(tableName, modelClass = '') {
    modelCodeLoading.value = true;
    modelCodeTableName.value = String(tableName || '');
    modelCodeModels.value = [];
    modelCodeSelectedClass.value = String(modelClass || '');
    modelCodeEditor.value = '';
    modelCodeLoaded.value = '';
    generatedCodeEditor.value = '';
    modelCodeModelPath.value = '';
    modelCodeGeneratedPath.value = '';
    modelCodeFlash.value = {
        type: 'warning',
        message: t(
            'feedback.model_code_disabled',
            'Modelcode beheren is uitgeschakeld.',
        ),
    };
    modelCodeLoading.value = false;
}

async function onModelCodeSelectionChange() {
    const tableName = String(modelCodeTableName.value || '').trim();
    const modelClass = String(modelCodeSelectedClass.value || '').trim();
    if (tableName === '' || modelClass === '') {
        return;
    }

    await loadModelCode(tableName, modelClass);
}

async function saveModelCode() {
    const tableName = String(modelCodeTableName.value || '').trim();
    const modelClass = String(modelCodeSelectedClass.value || '').trim();
    if (tableName === '' || modelClass === '' || !modelCodeCanSave.value) {
        return;
    }

    modelCodeSaving.value = true;
    modelCodeFlash.value = { type: '', message: '' };

    modelCodeFlash.value = {
        type: 'warning',
        message: t(
            'feedback.model_code_disabled',
            'Modelcode beheren is uitgeschakeld.',
        ),
    };
    modelCodeSaving.value = false;
}

async function copyGeneratedCode() {
    const content = String(generatedCodeEditor.value || '');
    if (content.trim() === '') {
        return;
    }

    try {
        await navigator.clipboard.writeText(content);
        modelCodeFlash.value = {
            type: 'success',
            message: t(
                'feedback.generated_code_copied',
                'Generated code werd gekopieerd.',
            ),
        };
    } catch {
        modelCodeFlash.value = {
            type: 'danger',
            message: t(
                'feedback.generated_code_copy_failed',
                'Kopieren van generated code is mislukt.',
            ),
        };
    }
}

function openCreateFormForSelected() {
    const tableName = String(selectedTable.value?.name || '');
    if (!tableName || !canAddOnSelected.value) {
        return;
    }

    router.visit(route('admin.db-diagram.table-create', { table: tableName }));
}

function isSharedTableName(tableName) {
    const normalized = String(tableName || '').trim();
    if (!normalized) {
        return false;
    }

    const prefixes = Array.isArray(props.applicationPrefixes)
        ? props.applicationPrefixes
        : [];

    for (const prefixValue of prefixes) {
        const prefix = String(prefixValue || '').trim();
        if (prefix && normalized.startsWith(`${prefix}_`)) {
            return false;
        }
    }

    return true;
}

function hasTableMenuActions(tableName) {
    const normalized = String(tableName || '').trim();
    if (!normalized) {
        return false;
    }

    if (hasEditTableAction(normalized)) {
        return true;
    }

    if (hasModelCodeAction(normalized)) {
        return true;
    }

    if (props.canExportDatabaseContents) {
        return true;
    }

    if (hasDeleteTableAction(normalized)) {
        return true;
    }

    return false;
}

function hasEditTableAction(tableName) {
    const normalized = String(tableName || '').trim();
    if (!normalized || !props.canEditTableDefinition) {
        return false;
    }

    if (isEditBlockedTable(normalized)) {
        return false;
    }

    return true;
}

function hasModelCodeAction(tableName) {
    const normalized = String(tableName || '').trim();
    if (!normalized || !props.canManageModelCode) {
        return false;
    }

    if (isEditBlockedTable(normalized)) {
        return false;
    }

    const modelOptions = modelOptionsByTable.value.get(normalized);

    return Array.isArray(modelOptions) && modelOptions.length > 0;
}

function hasDeleteTableAction(tableName) {
    return false;
}

function hasSharedAssignments(tableName) {
    const normalized = String(tableName || '').trim();
    if (!normalized || !isSharedTableName(normalized)) {
        return false;
    }

    const tableAccessMap = sharedAccessState.value?.[normalized] || {};

    return Object.keys(tableAccessMap).length > 0;
}

function openApplicationAccessDialog(tableName) {
    const normalized = String(tableName || '').trim();
    if (!normalized || !isSharedTableName(normalized)) {
        return;
    }

    applicationAccessSuccessMessage.value = '';
    applicationAccessErrorMessage.value = '';
    accessTableName.value = normalized;

    const tableAccessMap = sharedAccessState.value?.[normalized] || {};
    const targets = Array.isArray(props.applicationAccessTargets)
        ? props.applicationAccessTargets
        : [];

    applicationAccessRows.value = targets.map((application) => {
        const applicationId = Number(application?.id || 0);
        const access = tableAccessMap?.[String(applicationId)] || {};

        return {
            application_id: applicationId,
            name: String(application?.name || ''),
            slug: String(application?.slug || ''),
            show_in_application_scope: Boolean(
                access?.show_in_application_scope,
            ),
            allow_fk: Boolean(access?.allow_fk),
        };
    });

    applicationAccessDialogOpen.value = true;
}

function closeApplicationAccessDialog() {
    if (applicationAccessSaving.value) {
        return;
    }

    applicationAccessDialogOpen.value = false;
    accessTableName.value = '';
    applicationAccessRows.value = [];
    applicationAccessSuccessMessage.value = '';
    applicationAccessErrorMessage.value = '';
}

async function saveApplicationAccess() {
    return;
}

function exportTableSql(tableName) {
    window.location.assign(
        route('admin.db-diagram.table-export-sql', { table: tableName }),
    );
}

function openDeleteTableDialog(tableName) {
    const normalized = String(tableName || '').trim();
    if (!hasDeleteTableAction(normalized) || deletingTableName.value !== '') {
        return;
    }

    pendingDeleteTableName.value = normalized;
    deleteTableDialogOpen.value = true;
}

function closeDeleteTableDialog() {
    if (deletingTableName.value !== '') {
        return;
    }

    deleteTableDialogOpen.value = false;
    pendingDeleteTableName.value = '';
}

function confirmDeleteTable() {
    const normalized = String(pendingDeleteTableName.value || '').trim();
    if (!hasDeleteTableAction(normalized) || deletingTableName.value !== '') {
        return;
    }

    return;
}

function modelCodeCompletionSource(context) {
    const word = context.matchBefore(/[A-Za-z_][A-Za-z0-9_]*/);
    const explicit = context.explicit;

    if (!word && !explicit) {
        return null;
    }

    const from = word ? word.from : context.pos;
    const to = word ? word.to : context.pos;
    const searchValue = word ? String(word.text || '').toLowerCase() : '';

    const options = modelCodeCompletionOptions.filter((option) => {
        if (!searchValue) {
            return true;
        }

        return String(option.label || '')
            .toLowerCase()
            .startsWith(searchValue);
    });

    return {
        from,
        to,
        options,
    };
}

function normalizeOrder(order, fallback) {
    const source = Array.isArray(order)
        ? order.map((value) => String(value)).filter((value) => value !== '')
        : [];
    const baseline = Array.isArray(fallback)
        ? fallback.map((value) => String(value)).filter((value) => value !== '')
        : [];

    const baselineSet = new Set(baseline);
    const result = [];

    for (const name of source) {
        if (baselineSet.has(name) && !result.includes(name)) {
            result.push(name);
        }
    }

    for (const name of baseline) {
        if (!result.includes(name)) {
            result.push(name);
        }
    }

    return result;
}

function loadLocalStorage(key) {
    if (typeof window === 'undefined' || !window.localStorage) {
        return '';
    }

    try {
        const value = window.localStorage.getItem(key);

        return typeof value === 'string' ? value : '';
    } catch {
        return '';
    }
}

function loadLocalStorageArray(key) {
    const rawValue = loadLocalStorage(key);
    if (rawValue === '') {
        return [];
    }

    try {
        const parsed = JSON.parse(rawValue);

        return Array.isArray(parsed)
            ? parsed
                  .map((value) => String(value))
                  .filter((value) => value !== '')
            : [];
    } catch {
        return [];
    }
}

function saveLocalStorage(key, value) {
    if (typeof window === 'undefined' || !window.localStorage) {
        return;
    }

    try {
        window.localStorage.setItem(key, value);
    } catch {
        // no-op
    }
}

function clampZoom(value) {
    const numeric = Number(value);

    if (Number.isNaN(numeric)) {
        return 1;
    }

    return Math.min(2, Math.max(0.5, numeric));
}

async function beforeListInlineUpdate() {
    if (listMode.value !== 'edit' || !canUseEditModeOnSelected.value) {
        return { proceed: false };
    }

    return { proceed: true };
}

async function beforeListInlineDelete(context) {
    const tableName = String(selectedTable.value?.name || '');
    if (
        listMode.value !== 'edit' ||
        !tableName ||
        !canUseEditModeOnSelected.value ||
        !props.canDeleteDatabaseContents
    ) {
        return { proceed: false };
    }

    const id = context?.id;
    if (id === null || id === undefined) {
        return { proceed: false };
    }

    try {
        const response = await window.axios.post(
            route('admin.db-diagram.table-analyze-delete', {
                table: tableName,
                id,
            }),
        );

        if (!response?.data?.requiresConfirmation) {
            return { proceed: true };
        }

        const confirmed = window.confirm(
            t(
                'confirm.delete_with_relations',
                'Deze verwijdering heeft relationele impact. Wil je doorgaan?',
            ),
        );
        if (!confirmed) {
            return { proceed: false };
        }

        return {
            proceed: true,
            payload: {
                relation_confirmed: true,
            },
        };
    } catch {
        return { proceed: false };
    }
}
</script>

<style scoped>
.diagram-scroll {
    overflow: auto;
    padding-bottom: 0.25rem;
}

.diagram-canvas {
    position: relative;
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    min-width: 0;
    padding: 0.25rem;
    will-change: transform;
}

@media (min-width: 900px) {
    .diagram-canvas {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 1400px) {
    .diagram-canvas {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.diagram-connections {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    overflow: visible;
    z-index: 30;
}

.connection-line {
    fill: none;
    stroke: rgb(185 28 28);
    stroke-width: 2;
}

.connection-badge-circle {
    fill: rgb(255 255 255);
    stroke: rgb(185 28 28);
    stroke-width: 1.5;
}

.connection-badge-text {
    fill: rgb(185 28 28);
    font-size: 9px;
    font-family: Arial, sans-serif;
    font-weight: 700;
}

.diagram-node {
    position: relative;
    z-index: 10;
}

.diagram-node-draggable {
    cursor: move;
}

.diagram-node-dragging {
    opacity: 0.45;
}

.drag-handle {
    height: 1.45rem;
    width: 1.45rem;
    min-width: 1.45rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.35rem;
    border: 1px solid rgb(226 232 240);
    color: rgb(100 116 139);
    background-color: rgb(255 255 255);
    transition: background-color 120ms ease;
}

.drag-handle:hover {
    background-color: rgb(241 245 249);
}

.drag-handle-disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.node-summary {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    cursor: pointer;
    color: rgb(51 65 85);
    font-size: 0.72rem;
    font-weight: 600;
    border: 1px solid rgb(226 232 240);
    border-radius: 0.35rem;
    padding: 0.2rem 0.35rem;
    background-color: rgb(248 250 252);
}

.node-summary::-webkit-details-marker {
    display: none;
}

.summary-chevron {
    font-size: 0.9rem;
    color: rgb(71 85 105);
    transition: transform 120ms ease;
    transform: rotate(0deg);
}

details[open] > .node-summary .summary-chevron {
    transform: rotate(90deg);
}

.node-list {
    display: grid;
    gap: 0.25rem;
    margin-top: 0.375rem;
}

.readonly-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border-color: rgb(153 27 27);
    background-color: rgb(220 38 38);
    color: rgb(255 255 255);
}

.shared-chip {
    display: inline-flex;
    align-items: center;
    border-color: rgb(194 65 12);
    background-color: rgb(234 88 12);
    color: rgb(255 255 255);
    font-size: 0.66rem;
}

.node-row {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.12rem;
    padding: 0.2rem 0.35rem;
    border-radius: 0.25rem;
    border: 1px solid rgb(241 245 249);
}

.node-row-title {
    width: 100%;
    font-size: 0.68rem;
    line-height: 0.95rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.node-row-meta {
    width: 100%;
    font-size: 0.62rem;
    line-height: 0.88rem;
}

.relation-row {
    cursor: pointer;
    transition: background-color 120ms ease;
}

.relation-row:hover {
    background-color: rgb(248 250 252);
}

.relation-row-active {
    background-color: rgb(254 242 242);
    border-color: rgb(248 113 113);
}

.field-row-active {
    background-color: rgb(254 226 226);
    border-color: rgb(248 113 113);
}

.field-row-active .field-name {
    color: rgb(185 28 28);
    font-weight: 700;
}

.list-view-container {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: 280px 1fr;
    align-items: stretch;
}

.list-sidebar {
    height: 100%;
    min-height: 0;
    max-height: none;
    align-self: stretch;
    display: flex;
    flex-direction: column;
}

.list-content {
    min-width: 0;
}

.list-sidebar-content {
    flex: 1;
    min-height: 0;
    overflow: auto;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.25rem;
}

.table-list-item {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: flex-start;
    gap: 0;
    border: 1px solid rgb(226 232 240);
    border-radius: 0.375rem;
    padding: 0.22rem 0.4rem;
    text-align: left;
    transition: background-color 120ms ease;
}

.table-list-title {
    min-width: 0;
    font-size: 0.68rem;
    line-height: 0.82rem;
    font-weight: 600;
    color: rgb(30 41 59);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.table-list-details {
    display: inline-flex;
    align-items: center;
    gap: 0.18rem;
    line-height: 0.75rem;
}

.table-list-meta {
    flex-shrink: 0;
    font-size: 0.58rem;
    line-height: 0.72rem;
    color: rgb(100 116 139);
}

.table-list-chip {
    font-size: 0.54rem;
    line-height: 0.72rem;
    padding: 0 0.2rem;
}

.table-list-item:hover {
    background-color: rgb(248 250 252);
}

.table-list-item-active {
    background-color: rgb(239 246 255);
    border-color: rgb(147 197 253);
}

.model-code-tab-btn {
    border-radius: 0.25rem;
    padding: 0.28rem 0.6rem;
    font-size: 0.75rem;
    line-height: 1rem;
    font-weight: 600;
    color: rgb(71 85 105);
}

.model-code-tab-btn:hover {
    background-color: rgb(241 245 249);
}

.model-code-tab-btn-active {
    background-color: rgb(37 99 235);
    color: rgb(255 255 255);
}

@media (max-width: 1200px) {
    .list-view-container {
        grid-template-columns: 1fr;
    }

    .list-sidebar {
        width: 100%;
        height: 320px;
    }
}
</style>
