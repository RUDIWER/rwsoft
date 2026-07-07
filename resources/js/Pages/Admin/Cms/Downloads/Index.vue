<template>
    <Head :title="t('downloads.page_title', 'CMS downloads')" />

    <AdminLayout :suppress-flash="true">
        <Card class="rounded-none shadow-none">
            <CardHeader class="gap-0 border-b border-slate-200 p-0">
                <div
                    class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-5"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                            aria-hidden="true"
                        >
                            <span
                                class="mdi mdi-file-download-outline text-2xl"
                            />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">{{
                                t('downloads.title', 'Downloads')
                            }}</CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'downloads.description',
                                        'Manage protected public website documents and access rules.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            as-child
                            variant="outline"
                            size="icon"
                            class="text-slate-950 shadow-none hover:bg-slate-50 hover:text-slate-950"
                        >
                            <Link
                                :href="route('admin')"
                                :aria-label="commonT('actions.back', 'Back')"
                                :title="commonT('actions.back', 'Back')"
                            >
                                <span
                                    class="mdi mdi-arrow-left-circle text-lg"
                                    aria-hidden="true"
                                />
                            </Link>
                        </Button>

                        <Button
                            as-child
                            variant="outline"
                            class="border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                        >
                            <Link
                                :href="route('admin.cms.download-groups.index')"
                                class="gap-2"
                            >
                                <span
                                    class="mdi mdi-account-group-outline text-base text-blue-700"
                                    aria-hidden="true"
                                />
                                {{ t('downloads.groups.title', 'Groups') }}
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <div
                v-if="pageFlash.message || localFlash.message"
                class="border-b border-slate-200 px-4 py-3 sm:px-5"
            >
                <RwFlashMessage
                    :type="
                        localFlash.message ? localFlash.type : pageFlash.type
                    "
                    :message="localFlash.message || pageFlash.message"
                />
            </div>

            <CardContent class="p-0">
                <div
                    class="grid min-h-[calc(100vh-220px)] lg:grid-cols-[300px_minmax(0,1fr)]"
                >
                    <aside
                        class="border-b border-slate-200 bg-slate-50 p-4 sm:p-5 lg:border-b-0 lg:border-r"
                    >
                        <div class="grid gap-4">
                            <div class="grid gap-1">
                                <button
                                    v-if="withoutFolderCount > 0"
                                    type="button"
                                    class="flex w-full items-center justify-between rounded-md px-2 py-2 text-left text-sm transition"
                                    :class="
                                        selectedFolderId === 'none'
                                            ? selectedFolderClass
                                            : folderClass
                                    "
                                    @click="selectFolder('none')"
                                >
                                    <span
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <span
                                            class="mdi mdi-folder-off-outline text-base"
                                            aria-hidden="true"
                                        />
                                        <span class="truncate">{{
                                            t(
                                                'downloads.folders.without_folder',
                                                'Without folder',
                                            )
                                        }}</span>
                                    </span>
                                    <span class="text-xs">{{
                                        withoutFolderCount
                                    }}</span>
                                </button>

                                <div class="group flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="flex min-w-0 flex-1 items-center justify-between rounded-md px-2 py-2 text-left text-sm transition"
                                        :class="
                                            selectedFolderId === 'all'
                                                ? selectedFolderClass
                                                : folderClass
                                        "
                                        @click="selectFolder('all')"
                                    >
                                        <span
                                            class="flex min-w-0 items-center gap-2"
                                        >
                                            <span
                                                class="mdi mdi-folder-multiple-outline text-base"
                                                aria-hidden="true"
                                            />
                                            <span class="truncate">{{
                                                t(
                                                    'downloads.folders.all_downloads',
                                                    'All downloads',
                                                )
                                            }}</span>
                                        </span>
                                        <span class="text-xs">{{
                                            localAssets.length
                                        }}</span>
                                    </button>

                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 opacity-100 lg:opacity-0 lg:group-hover:opacity-100"
                                                :aria-label="
                                                    t(
                                                        'downloads.folders.actions',
                                                        'Folder actions',
                                                    )
                                                "
                                                :title="
                                                    t(
                                                        'downloads.folders.actions',
                                                        'Folder actions',
                                                    )
                                                "
                                            >
                                                <span
                                                    class="mdi mdi-dots-vertical text-base"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                @click="openCreateFolder(null)"
                                            >
                                                {{
                                                    t(
                                                        'downloads.folders.create_root',
                                                        'Create folder',
                                                    )
                                                }}
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>

                                <div class="mt-2 grid gap-1">
                                    <div
                                        v-for="folder in folderRows"
                                        :key="folder.id"
                                        class="group flex items-center gap-1"
                                        :class="depthClass(folder.depth)"
                                    >
                                        <button
                                            type="button"
                                            class="flex min-w-0 flex-1 items-center justify-between rounded-md px-2 py-2 text-left text-sm transition"
                                            :class="
                                                String(selectedFolderId) ===
                                                String(folder.id)
                                                    ? selectedFolderClass
                                                    : folderClass
                                            "
                                            @click="
                                                selectFolder(String(folder.id))
                                            "
                                        >
                                            <span
                                                class="flex min-w-0 items-center gap-2"
                                            >
                                                <span
                                                    class="mdi mdi-folder-outline text-base"
                                                    aria-hidden="true"
                                                />
                                                <span class="truncate">{{
                                                    folder.name
                                                }}</span>
                                                <span
                                                    v-if="folder.has_password"
                                                    class="mdi mdi-lock-outline text-xs text-orange-600"
                                                    :title="
                                                        t(
                                                            'downloads.folders.password_protected',
                                                            'Password protected',
                                                        )
                                                    "
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            <span class="text-xs">{{
                                                folderAssetCount(folder.id)
                                            }}</span>
                                        </button>

                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-8 w-8 opacity-100 lg:opacity-0 lg:group-hover:opacity-100"
                                                    :aria-label="
                                                        t(
                                                            'downloads.folders.actions',
                                                            'Folder actions',
                                                        )
                                                    "
                                                    :title="
                                                        t(
                                                            'downloads.folders.actions',
                                                            'Folder actions',
                                                        )
                                                    "
                                                >
                                                    <span
                                                        class="mdi mdi-dots-vertical text-base"
                                                        aria-hidden="true"
                                                    />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    @click="
                                                        openCreateFolder(folder)
                                                    "
                                                >
                                                    {{
                                                        t(
                                                            'downloads.folders.create_subfolder',
                                                            'Create subfolder',
                                                        )
                                                    }}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="
                                                        openRenameFolder(folder)
                                                    "
                                                >
                                                    {{
                                                        t(
                                                            'downloads.folders.rename',
                                                            'Rename folder',
                                                        )
                                                    }}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="
                                                        openMoveFolder(folder)
                                                    "
                                                >
                                                    {{
                                                        t(
                                                            'downloads.folders.move',
                                                            'Move folder',
                                                        )
                                                    }}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    @click="
                                                        openFolderSettings(
                                                            folder,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        t(
                                                            'downloads.folders.settings',
                                                            'Settings and access',
                                                        )
                                                    }}
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <main class="min-w-0">
                        <section
                            class="border-b border-slate-200 px-4 py-4 sm:px-5"
                        >
                            <div
                                class="grid cursor-pointer gap-3 rounded-lg border border-dashed p-5 text-center transition"
                                :class="
                                    dragOver
                                        ? 'border-blue-400 bg-blue-50'
                                        : 'border-slate-300 bg-slate-50 hover:border-blue-300 hover:bg-blue-50/50'
                                "
                                role="button"
                                tabindex="0"
                                @click="openFilePicker"
                                @keydown.enter.prevent="openFilePicker"
                                @keydown.space.prevent="openFilePicker"
                                @dragenter.prevent="dragOver = true"
                                @dragover.prevent="dragOver = true"
                                @dragleave.prevent="dragOver = false"
                                @drop.prevent="onDropFiles"
                            >
                                <input
                                    ref="fileInput"
                                    type="file"
                                    multiple
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.txt,.csv"
                                    class="sr-only"
                                    @click.stop
                                    @change="onFileInputChange"
                                />
                                <div class="grid gap-1">
                                    <span
                                        class="mdi mdi-cloud-upload-outline text-3xl text-blue-700"
                                        aria-hidden="true"
                                    />
                                    <h2
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'downloads.upload.title',
                                                'Upload documents',
                                            )
                                        }}
                                    </h2>
                                    <p class="text-sm text-slate-600">
                                        {{
                                            t(
                                                'downloads.upload.dropzone',
                                                'Click or drop files here. They are saved directly in the selected folder.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs font-medium text-blue-700"
                                    >
                                        {{
                                            t(
                                                'downloads.upload.target_folder_inline',
                                                'Target folder: :folder',
                                                { folder: selectedFolderLabel },
                                            )
                                        }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'downloads.upload.description',
                                                'Allowed: PDF, Office, OpenDocument, TXT and CSV. Maximum 20MB.',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="visibleUploadItems.length > 0"
                                class="mt-4 grid gap-2"
                            >
                                <div
                                    v-for="item in visibleUploadItems"
                                    :key="item.key"
                                    class="grid gap-2 rounded border bg-white p-3 text-sm sm:grid-cols-[minmax(0,1fr)_160px_auto] sm:items-center"
                                    :class="
                                        item.status === 'error'
                                            ? 'border-red-200'
                                            : 'border-slate-200'
                                    "
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="truncate font-medium text-slate-900"
                                        >
                                            {{ item.fileName }}
                                        </div>
                                        <p
                                            class="text-xs"
                                            :class="uploadStatusClass(item)"
                                        >
                                            {{ uploadStatusLabel(item) }}
                                        </p>
                                    </div>
                                    <progress
                                        v-if="item.status === 'uploading'"
                                        class="h-2 w-full"
                                        :value="item.progress"
                                        max="100"
                                    />
                                    <div
                                        v-else
                                        class="hidden text-xs text-slate-500 sm:block"
                                    >
                                        {{ selectedFolderLabel }}
                                    </div>
                                    <div
                                        class="flex flex-wrap justify-end gap-2"
                                    >
                                        <Button
                                            v-if="item.status === 'error'"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="retryUpload(item)"
                                        >
                                            {{
                                                t(
                                                    'downloads.upload.retry',
                                                    'Retry',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            @click="removeUploadItem(item)"
                                        >
                                            {{
                                                t(
                                                    'downloads.upload.remove',
                                                    'Remove',
                                                )
                                            }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section
                            class="border-b border-slate-200 px-4 py-3 sm:px-5"
                        >
                            <div
                                class="flex flex-wrap items-end justify-between gap-3"
                            >
                                <div class="grid gap-1">
                                    <Label
                                        for="download_search"
                                        class="text-[11px] text-slate-600"
                                    >
                                        {{
                                            t(
                                                'downloads.filters.search',
                                                'Search',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        id="download_search"
                                        v-model="searchTerm"
                                        type="search"
                                        class="w-full sm:w-80"
                                        :placeholder="
                                            t(
                                                'downloads.filters.search_placeholder',
                                                'Search title, filename, description or extension',
                                            )
                                        "
                                    />
                                </div>
                                <div class="text-sm font-medium text-slate-600">
                                    {{
                                        t(
                                            'downloads.folders.items_count',
                                            ':shown of :total items',
                                            {
                                                shown: visibleAssets.length,
                                                total: localAssets.length,
                                            },
                                        )
                                    }}
                                </div>
                            </div>
                        </section>

                        <RwTable
                            table-id="admin-cms-downloads-table"
                            :data="tableData"
                            :columns="columns"
                            :initial-height="'calc(100vh - 380px)'"
                            :rows-per-page="25"
                            sort-field="id"
                            sort-order="desc"
                            :row-options="[25, 50, 100, 250]"
                            :cell-class="cellClass"
                            excel="true"
                            @on-cell-click="onCellClick"
                        />
                    </main>
                </div>
            </CardContent>
        </Card>

        <Dialog :open="editDialogOpen" @update:open="onEditDialogOpenChange">
            <DialogContent
                class="flex max-h-[calc(100vh-2rem)] max-w-5xl flex-col overflow-hidden p-0 shadow-none"
            >
                <DialogHeader
                    class="shrink-0 border-b border-slate-200 px-6 py-5"
                >
                    <DialogTitle>{{
                        t('downloads.edit_title', 'Edit download')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{ editDialogDescription }}
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="editFlash.message"
                    class="shrink-0 border-b border-slate-200 px-6 py-4"
                >
                    <RwFlashMessage
                        :type="editFlash.type"
                        :message="editFlash.message"
                    />
                </div>

                <div
                    v-if="selectedEditAsset"
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-6 py-3 text-sm font-semibold text-slate-700"
                >
                    <div class="font-medium text-slate-700">
                        {{ commonT('record_meta.id', 'ID') }}:
                        <span class="ml-1 text-base font-bold text-slate-950">
                            {{ selectedEditAsset.id }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                        <div class="font-medium text-slate-700">
                            {{ commonT('record_meta.updated_at', 'Updated') }}:
                            <span
                                class="ml-1 text-base font-bold text-slate-950"
                            >
                                {{ formatDate(selectedEditAsset.updated_at) }}
                            </span>
                        </div>
                        <div class="font-medium text-slate-700">
                            {{ commonT('record_meta.created_at', 'Created') }}:
                            <span
                                class="ml-1 text-base font-bold text-slate-950"
                            >
                                {{ formatDate(selectedEditAsset.created_at) }}
                            </span>
                        </div>
                    </div>
                </div>

                <form
                    class="flex min-h-0 flex-1 flex-col overflow-hidden"
                    @submit.prevent="submitEditDownload"
                >
                    <div
                        class="grid min-h-0 flex-1 gap-5 overflow-y-auto px-6 py-5"
                    >
                        <section class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2 md:col-span-2">
                                <Label for="edit_download_title">{{
                                    t('downloads.fields.title', 'Title')
                                }}</Label>
                                <Input
                                    id="edit_download_title"
                                    v-model="editForm.title"
                                    type="text"
                                />
                                <p
                                    v-if="editErrors.title"
                                    class="text-sm text-red-600"
                                >
                                    {{ editErrors.title }}
                                </p>
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label for="edit_download_description">{{
                                    t(
                                        'downloads.fields.description',
                                        'Description',
                                    )
                                }}</Label>
                                <textarea
                                    id="edit_download_description"
                                    v-model="editForm.description"
                                    class="min-h-24 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                                <p
                                    v-if="editErrors.description"
                                    class="text-sm text-red-600"
                                >
                                    {{ editErrors.description }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit_download_folder_id">{{
                                    t('downloads.folder', 'Folder')
                                }}</Label>
                                <RwAutoCompleteInput
                                    id="edit_download_folder_id"
                                    v-model="editForm.folder_id"
                                    :items="downloadFolderOptions"
                                    item-title="name"
                                    item-value="id"
                                    :search-fields="['name']"
                                    :placeholder="
                                        t('downloads.no_folder', 'No folder')
                                    "
                                />
                                <p
                                    v-if="editErrors.folder_id"
                                    class="text-sm text-red-600"
                                >
                                    {{ editErrors.folder_id }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    for="edit_download_access_mode"
                                    class="flex items-center gap-1"
                                >
                                    <span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >
                                    {{
                                        t(
                                            'downloads.fields.access_mode',
                                            'Access mode',
                                        )
                                    }}
                                </Label>
                                <RwAutoCompleteInput
                                    id="edit_download_access_mode"
                                    v-model="editForm.access_mode"
                                    :items="accessModeOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label']"
                                    required
                                    required-highlight-color="#fefce8"
                                />
                                <p
                                    v-if="editErrors.access_mode"
                                    class="text-sm text-red-600"
                                >
                                    {{ editErrors.access_mode }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit_download_published_at">{{
                                    t(
                                        'downloads.fields.published_at',
                                        'Published at',
                                    )
                                }}</Label>
                                <Input
                                    id="edit_download_published_at"
                                    v-model="editForm.published_at"
                                    type="datetime-local"
                                />
                                <p
                                    v-if="editErrors.published_at"
                                    class="text-sm text-red-600"
                                >
                                    {{ editErrors.published_at }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="edit_download_expires_at">{{
                                    t(
                                        'downloads.fields.expires_at',
                                        'Expires at',
                                    )
                                }}</Label>
                                <Input
                                    id="edit_download_expires_at"
                                    v-model="editForm.expires_at"
                                    type="datetime-local"
                                />
                                <p
                                    v-if="editErrors.expires_at"
                                    class="text-sm text-red-600"
                                >
                                    {{ editErrors.expires_at }}
                                </p>
                            </div>
                        </section>

                        <section
                            v-if="editForm.access_mode === 'restricted'"
                            class="grid gap-3 rounded border border-slate-200 bg-slate-50 p-3"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'downloads.access_rules.title',
                                                'Access rules',
                                            )
                                        }}
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-600">
                                        {{
                                            t(
                                                'downloads.access_rules.description',
                                                'Allow access for specific users, download groups or profile field values.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="gap-2 border-blue-200 bg-white text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                                    @click="addEditAccessRule"
                                >
                                    <span
                                        class="mdi mdi-plus-circle text-base text-blue-700"
                                        aria-hidden="true"
                                    />
                                    {{ commonT('actions.new', 'New') }}
                                </Button>
                            </div>

                            <div
                                v-if="editForm.access_rules.length === 0"
                                class="rounded border border-dashed border-slate-300 bg-white p-3 text-sm text-slate-500"
                            >
                                {{
                                    t(
                                        'downloads.access_rules.empty',
                                        'No access rules have been added yet.',
                                    )
                                }}
                            </div>

                            <div v-else class="grid gap-3">
                                <div
                                    v-for="(
                                        rule, index
                                    ) in editForm.access_rules"
                                    :key="`edit-access-rule-${index}`"
                                    class="grid gap-3 rounded border border-slate-200 bg-white p-3 lg:grid-cols-[minmax(180px,1fr)_minmax(220px,2fr)_auto]"
                                >
                                    <div class="grid gap-1">
                                        <Label
                                            class="text-[11px] text-slate-600"
                                        >
                                            {{
                                                t(
                                                    'downloads.access_rules.rule_type',
                                                    'Rule type',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            v-model="rule.rule_type"
                                            :items="accessRuleTypeOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label']"
                                            @update:model-value="
                                                resetAccessRuleTarget(rule)
                                            "
                                        />
                                    </div>

                                    <div
                                        v-if="rule.rule_type === 'site_user'"
                                        class="grid gap-1"
                                    >
                                        <Label
                                            class="text-[11px] text-slate-600"
                                        >
                                            {{
                                                t(
                                                    'downloads.access_rules.site_user',
                                                    'Site user',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            v-model="rule.site_user_id"
                                            :items="siteUsers"
                                            item-title="label"
                                            item-value="id"
                                            :search-fields="[
                                                'name',
                                                'email',
                                                'label',
                                            ]"
                                        />
                                    </div>

                                    <div
                                        v-else-if="
                                            rule.rule_type === 'download_group'
                                        "
                                        class="grid gap-1"
                                    >
                                        <Label
                                            class="text-[11px] text-slate-600"
                                        >
                                            {{
                                                t(
                                                    'downloads.access_rules.group',
                                                    'Download group',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            v-model="rule.cms_download_group_id"
                                            :items="groups"
                                            item-title="name"
                                            item-value="id"
                                            :search-fields="['name', 'slug']"
                                        />
                                    </div>

                                    <div
                                        v-else
                                        class="grid gap-3 md:grid-cols-3"
                                    >
                                        <div class="grid gap-1">
                                            <Label
                                                class="text-[11px] text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'downloads.access_rules.profile_field_key',
                                                        'Profile field',
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                v-model="rule.profile_field_key"
                                                type="text"
                                            />
                                        </div>
                                        <div class="grid gap-1">
                                            <Label
                                                class="text-[11px] text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'downloads.access_rules.operator',
                                                        'Operator',
                                                    )
                                                }}
                                            </Label>
                                            <RwAutoCompleteInput
                                                v-model="rule.operator"
                                                :items="profileOperatorOptions"
                                                item-title="label"
                                                item-value="value"
                                                :search-fields="['label']"
                                            />
                                        </div>
                                        <div class="grid gap-1">
                                            <Label
                                                class="text-[11px] text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'downloads.access_rules.value',
                                                        'Value',
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                v-model="rule.value"
                                                type="text"
                                            />
                                        </div>
                                    </div>

                                    <div class="flex items-end justify-end">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            class="h-9 w-9 border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                                            :title="
                                                commonT(
                                                    'actions.delete',
                                                    'Delete',
                                                )
                                            "
                                            :aria-label="
                                                commonT(
                                                    'actions.delete',
                                                    'Delete',
                                                )
                                            "
                                            @click="removeEditAccessRule(index)"
                                        >
                                            <span
                                                class="mdi mdi-delete text-base"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section
                            class="grid gap-3 rounded border border-slate-200 bg-slate-50 p-3"
                        >
                            <div>
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'downloads.replace.title',
                                            'Replace file',
                                        )
                                    }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{
                                        t(
                                            'downloads.replace.description',
                                            'Replacing the file keeps the same download ID and removes the old stored file.',
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="grid min-w-64 flex-1 gap-1">
                                    <Label
                                        for="edit_download_replace_file"
                                        class="text-[11px] text-slate-600"
                                    >
                                        {{ t('downloads.upload.file', 'File') }}
                                    </Label>
                                    <Input
                                        id="edit_download_replace_file"
                                        ref="replaceFileInput"
                                        type="file"
                                        @change="onReplaceFileChange"
                                    />
                                    <p
                                        v-if="replaceErrors.file"
                                        class="text-sm text-red-600"
                                    >
                                        {{ replaceErrors.file }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="
                                        replaceProcessing || !replaceFileValue
                                    "
                                    class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                                    @click="replaceEditFile"
                                >
                                    <span
                                        v-if="replaceProcessing"
                                        class="mdi mdi-loading animate-spin text-base text-green-700"
                                        aria-hidden="true"
                                    />
                                    <span
                                        v-else
                                        class="mdi mdi-file-replace-outline text-base text-green-700"
                                        aria-hidden="true"
                                    />
                                    {{
                                        t(
                                            'downloads.replace.submit',
                                            'Replace file',
                                        )
                                    }}
                                </Button>
                            </div>
                        </section>
                    </div>

                    <DialogFooter
                        class="shrink-0 border-t border-slate-200 px-6 py-4"
                    >
                        <Button
                            type="submit"
                            variant="outline"
                            class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                            :disabled="editProcessing"
                        >
                            <span
                                v-if="editProcessing"
                                class="mdi mdi-loading animate-spin text-base text-green-700"
                                aria-hidden="true"
                            />
                            <span
                                v-else
                                :class="[
                                    'mdi mdi-content-save text-base',
                                    editFormDirty
                                        ? 'text-red-600'
                                        : 'text-green-700',
                                ]"
                                aria-hidden="true"
                            />
                            {{ commonT('actions.save', 'Save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="createDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="border-b border-slate-200 pb-4">
                    <DialogTitle>{{
                        t(
                            'downloads.folders.create_dialog_title',
                            'Create folder',
                        )
                    }}</DialogTitle>
                    <DialogDescription>
                        {{ createFolderDescription }}
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitCreateFolder">
                    <div class="grid gap-2">
                        <Label
                            for="create_download_folder_name"
                            class="flex items-center gap-1"
                        >
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            >
                            {{ t('downloads.folders.name', 'Folder name') }}
                        </Label>
                        <Input
                            id="create_download_folder_name"
                            v-model="createFolderName"
                            class="bg-yellow-50"
                            :placeholder="
                                t(
                                    'downloads.folders.placeholder',
                                    'For example: Policies',
                                )
                            "
                            required
                        />
                        <p
                            v-if="folderDialogError"
                            class="text-sm text-red-600"
                        >
                            {{ folderDialogError }}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            variant="outline"
                            class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                            :disabled="folderDialogProcessing"
                        >
                            <span
                                v-if="folderDialogProcessing"
                                class="mdi mdi-loading animate-spin text-base text-green-700"
                                aria-hidden="true"
                            />
                            <span
                                v-else
                                class="mdi mdi-content-save text-base text-green-700"
                                aria-hidden="true"
                            />
                            {{ commonT('actions.save', 'Save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="renameDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="border-b border-slate-200 pb-4">
                    <DialogTitle>{{
                        t('downloads.folders.rename', 'Rename folder')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'downloads.folders.rename_description',
                                'Update the folder name. The slug is adjusted automatically.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitRenameFolder">
                    <div class="grid gap-2">
                        <Label
                            for="rename_download_folder_name"
                            class="flex items-center gap-1"
                        >
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            >
                            {{ t('downloads.folders.new_name', 'New name') }}
                        </Label>
                        <Input
                            id="rename_download_folder_name"
                            v-model="renameFolderName"
                            class="bg-yellow-50"
                            required
                        />
                        <p
                            v-if="folderDialogError"
                            class="text-sm text-red-600"
                        >
                            {{ folderDialogError }}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            variant="outline"
                            class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                            :disabled="folderDialogProcessing"
                        >
                            <span
                                v-if="folderDialogProcessing"
                                class="mdi mdi-loading animate-spin text-base text-green-700"
                                aria-hidden="true"
                            />
                            <span
                                v-else
                                class="mdi mdi-content-save text-base text-green-700"
                                aria-hidden="true"
                            />
                            {{ commonT('actions.save', 'Save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="moveDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="border-b border-slate-200 pb-4">
                    <DialogTitle>{{
                        t('downloads.folders.move', 'Move folder')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'downloads.folders.move_description',
                                'Choose a new parent folder without creating loops.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitMoveFolder">
                    <div class="grid gap-2">
                        <Label for="move_download_folder_parent">{{
                            t('downloads.folders.parent', 'Parent folder')
                        }}</Label>
                        <RwAutoCompleteInput
                            id="move_download_folder_parent"
                            v-model="moveFolderParentId"
                            :items="moveTargetOptions"
                            item-title="indentedName"
                            item-value="id"
                            :search-fields="['name', 'indentedName']"
                        />
                        <p
                            v-if="folderDialogError"
                            class="text-sm text-red-600"
                        >
                            {{ folderDialogError }}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            variant="outline"
                            class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                            :disabled="folderDialogProcessing"
                        >
                            <span
                                v-if="folderDialogProcessing"
                                class="mdi mdi-loading animate-spin text-base text-green-700"
                                aria-hidden="true"
                            />
                            <span
                                v-else
                                class="mdi mdi-content-save text-base text-green-700"
                                aria-hidden="true"
                            />
                            {{ commonT('actions.save', 'Save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="settingsDialogOpen">
            <DialogContent class="max-h-[calc(100vh-2rem)] max-w-4xl p-0">
                <DialogHeader class="border-b border-slate-200 px-6 py-5">
                    <DialogTitle>{{
                        t('downloads.folders.settings', 'Settings and access')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{ settingsDialogDescription }}
                    </DialogDescription>
                </DialogHeader>

                <form
                    class="flex min-h-0 flex-1 flex-col overflow-hidden"
                    @submit.prevent="submitFolderSettings"
                >
                    <div
                        class="grid min-h-0 flex-1 gap-4 overflow-y-auto px-6 py-5"
                    >
                        <div
                            v-if="folderDialogError"
                            class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                        >
                            {{ folderDialogError }}
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <div class="grid gap-1">
                                <Label
                                    for="settings_download_folder_access_mode"
                                    class="text-[11px] text-slate-600"
                                >
                                    {{
                                        t(
                                            'downloads.fields.access_mode',
                                            'Access mode',
                                        )
                                    }}
                                </Label>
                                <RwAutoCompleteInput
                                    id="settings_download_folder_access_mode"
                                    v-model="folderSettingsForm.access_mode"
                                    :items="accessModeOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label']"
                                />
                            </div>

                            <div class="grid gap-1">
                                <Label
                                    for="settings_download_folder_password"
                                    class="text-[11px] text-slate-600"
                                >
                                    {{
                                        t(
                                            'downloads.folders.password',
                                            'Folder password',
                                        )
                                    }}
                                </Label>
                                <Input
                                    id="settings_download_folder_password"
                                    v-model="folderSettingsForm.password"
                                    type="password"
                                    autocomplete="new-password"
                                />
                            </div>

                            <div class="grid gap-1">
                                <Label
                                    for="settings_download_folder_password_minutes"
                                    class="text-[11px] text-slate-600"
                                >
                                    {{
                                        t(
                                            'downloads.folders.password_expires_minutes',
                                            'Password duration in minutes',
                                        )
                                    }}
                                </Label>
                                <Input
                                    id="settings_download_folder_password_minutes"
                                    v-model="
                                        folderSettingsForm.password_expires_minutes
                                    "
                                    type="number"
                                    min="1"
                                    max="10080"
                                />
                            </div>
                        </div>

                        <label
                            v-if="selectedDialogFolder?.has_password"
                            class="flex items-center gap-2 text-sm text-slate-700"
                        >
                            <input
                                v-model="folderSettingsForm.clear_password"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            {{
                                t(
                                    'downloads.folders.clear_password',
                                    'Clear existing password',
                                )
                            }}
                        </label>

                        <div
                            v-if="
                                folderSettingsForm.access_mode === 'restricted'
                            "
                            class="grid gap-3 rounded border border-slate-200 bg-slate-50 p-3"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'downloads.access_rules.title',
                                                'Access rules',
                                            )
                                        }}
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-600">
                                        {{
                                            t(
                                                'downloads.access_rules.description',
                                                'Allow access for specific users, download groups or profile field values.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="gap-2 border-blue-200 bg-white text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                                    @click="addFolderAccessRule"
                                >
                                    <span
                                        class="mdi mdi-plus-circle text-base text-blue-700"
                                        aria-hidden="true"
                                    />
                                    {{ commonT('actions.new', 'New') }}
                                </Button>
                            </div>

                            <div
                                v-if="
                                    folderSettingsForm.access_rules.length === 0
                                "
                                class="rounded border border-dashed border-slate-300 p-3 text-sm text-slate-500"
                            >
                                {{
                                    t(
                                        'downloads.access_rules.empty',
                                        'No access rules have been added yet.',
                                    )
                                }}
                            </div>

                            <div v-else class="grid gap-3">
                                <div
                                    v-for="(
                                        rule, index
                                    ) in folderSettingsForm.access_rules"
                                    :key="`folder-access-rule-${index}`"
                                    class="grid gap-3 rounded border border-slate-200 bg-white p-3 lg:grid-cols-[minmax(180px,1fr)_minmax(220px,2fr)_auto]"
                                >
                                    <div class="grid gap-1">
                                        <Label
                                            class="text-[11px] text-slate-600"
                                        >
                                            {{
                                                t(
                                                    'downloads.access_rules.rule_type',
                                                    'Rule type',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            v-model="rule.rule_type"
                                            :items="accessRuleTypeOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label']"
                                            @update:model-value="
                                                resetAccessRuleTarget(rule)
                                            "
                                        />
                                    </div>

                                    <div
                                        v-if="rule.rule_type === 'site_user'"
                                        class="grid gap-1"
                                    >
                                        <Label
                                            class="text-[11px] text-slate-600"
                                        >
                                            {{
                                                t(
                                                    'downloads.access_rules.site_user',
                                                    'Site user',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            v-model="rule.site_user_id"
                                            :items="siteUsers"
                                            item-title="label"
                                            item-value="id"
                                            :search-fields="[
                                                'name',
                                                'email',
                                                'label',
                                            ]"
                                        />
                                    </div>

                                    <div
                                        v-else-if="
                                            rule.rule_type === 'download_group'
                                        "
                                        class="grid gap-1"
                                    >
                                        <Label
                                            class="text-[11px] text-slate-600"
                                        >
                                            {{
                                                t(
                                                    'downloads.access_rules.group',
                                                    'Download group',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            v-model="rule.cms_download_group_id"
                                            :items="groups"
                                            item-title="name"
                                            item-value="id"
                                            :search-fields="['name', 'slug']"
                                        />
                                    </div>

                                    <div
                                        v-else
                                        class="grid gap-3 md:grid-cols-3"
                                    >
                                        <div class="grid gap-1">
                                            <Label
                                                class="text-[11px] text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'downloads.access_rules.profile_field_key',
                                                        'Profile field',
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                v-model="rule.profile_field_key"
                                                type="text"
                                            />
                                        </div>
                                        <div class="grid gap-1">
                                            <Label
                                                class="text-[11px] text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'downloads.access_rules.operator',
                                                        'Operator',
                                                    )
                                                }}
                                            </Label>
                                            <RwAutoCompleteInput
                                                v-model="rule.operator"
                                                :items="profileOperatorOptions"
                                                item-title="label"
                                                item-value="value"
                                                :search-fields="['label']"
                                            />
                                        </div>
                                        <div class="grid gap-1">
                                            <Label
                                                class="text-[11px] text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'downloads.access_rules.value',
                                                        'Value',
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                v-model="rule.value"
                                                type="text"
                                            />
                                        </div>
                                    </div>

                                    <div class="flex items-end justify-end">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            class="h-9 w-9 border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                                            :title="
                                                commonT(
                                                    'actions.delete',
                                                    'Delete',
                                                )
                                            "
                                            :aria-label="
                                                commonT(
                                                    'actions.delete',
                                                    'Delete',
                                                )
                                            "
                                            @click="
                                                removeFolderAccessRule(index)
                                            "
                                        >
                                            <span
                                                class="mdi mdi-delete text-base"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="border-t border-slate-200 px-6 py-4">
                        <Button
                            type="submit"
                            variant="outline"
                            class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                            :disabled="folderDialogProcessing"
                        >
                            <span
                                v-if="folderDialogProcessing"
                                class="mdi mdi-loading animate-spin text-base text-green-700"
                                aria-hidden="true"
                            />
                            <span
                                v-else
                                class="mdi mdi-content-save text-base text-green-700"
                                aria-hidden="true"
                            />
                            {{ commonT('actions.save', 'Save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>

<script setup>
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwTable from '@/Components/RwTable.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    assets: { type: Array, required: true },
    folders: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    siteUsers: { type: Array, default: () => [] },
    editAsset: { type: Object, default: null },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');

const selectedFolderId = ref('all');
const searchTerm = ref('');
const dragOver = ref(false);
const fileInput = ref(null);
const localAssets = ref([...props.assets]);
const localFolders = ref([...props.folders]);
const localFlash = ref({ type: '', message: '' });
const uploadItems = ref([]);
const activeUploads = ref(0);
const editDialogOpen = ref(Boolean(props.editAsset));
const selectedEditAsset = ref(props.editAsset ?? null);
const editForm = ref(defaultEditForm(props.editAsset));
const editBaseline = ref(defaultEditForm(props.editAsset));
const editErrors = ref({});
const editFlash = ref({ type: '', message: '' });
const editProcessing = ref(false);
const replaceFileInput = ref(null);
const replaceFileValue = ref(null);
const replaceErrors = ref({});
const replaceProcessing = ref(false);
const createDialogOpen = ref(false);
const createFolderParentId = ref(null);
const createFolderName = ref('');
const renameDialogOpen = ref(false);
const moveDialogOpen = ref(false);
const settingsDialogOpen = ref(false);
const selectedDialogFolder = ref(null);
const renameFolderName = ref('');
const moveFolderParentId = ref(null);
const folderDialogProcessing = ref(false);
const folderDialogError = ref('');
const folderSettingsForm = ref(defaultFolderSettingsForm());
const maxConcurrentUploads = 3;

watch(
    () => props.assets,
    (assets) => {
        localAssets.value = [...assets];
    },
);

watch(
    () => props.folders,
    (folders) => {
        localFolders.value = [...folders];
    },
);

watch(
    () => props.editAsset,
    (asset) => {
        initializeEditDialog(asset);
    },
);

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

const selectedFolderClass = 'bg-blue-100 text-blue-800 ring-1 ring-blue-200';
const folderClass = 'text-slate-700 hover:bg-white hover:text-slate-950';

const folderRows = computed(() => flattenFolders(null, 0));
const withoutFolderCount = computed(() => folderAssetCount(null));

watch(
    () => withoutFolderCount.value,
    (count) => {
        if (count === 0 && selectedFolderId.value === 'none') {
            selectedFolderId.value = 'all';
        }
    },
);

const selectedFolderLabel = computed(() => {
    if (selectedFolderId.value === 'all') {
        return t('downloads.folders.all_downloads', 'All downloads');
    }

    if (selectedFolderId.value === 'none') {
        return t('downloads.folders.without_folder', 'Without folder');
    }

    return (
        folderById(Number(selectedFolderId.value))?.name ??
        t('downloads.no_folder', 'No folder')
    );
});

const createFolderDescription = computed(() => {
    if (!createFolderParentId.value) {
        return t(
            'downloads.folders.create_root_description',
            'Create a new folder at root level.',
        );
    }

    return t(
        'downloads.folders.create_child_description',
        'Create a subfolder under :name.',
        {
            name: folderById(Number(createFolderParentId.value))?.name ?? '-',
        },
    );
});

const settingsDialogDescription = computed(() => {
    if (!selectedDialogFolder.value) {
        return t(
            'downloads.folders.settings_description',
            'Manage folder access, passwords and access rules.',
        );
    }

    return t(
        'downloads.folders.settings_description_named',
        'Manage access for :name.',
        { name: selectedDialogFolder.value.name },
    );
});

const visibleUploadItems = computed(() =>
    uploadItems.value.filter((item) =>
        ['queued', 'uploading', 'error'].includes(item.status),
    ),
);

const filteredAssets = computed(() => {
    if (selectedFolderId.value === 'none') {
        return localAssets.value.filter((asset) => !asset.folder_id);
    }

    if (selectedFolderId.value !== 'all') {
        return localAssets.value.filter(
            (asset) =>
                Number(asset.folder_id) === Number(selectedFolderId.value),
        );
    }

    return localAssets.value;
});

const visibleAssets = computed(() => {
    const term = searchTerm.value.trim().toLowerCase();
    let assets = filteredAssets.value;

    if (term) {
        assets = assets.filter((asset) =>
            [
                asset.title,
                asset.description,
                asset.original_filename,
                asset.filename,
                asset.extension,
                asset.folder_name,
            ]
                .filter(Boolean)
                .some((value) => value.toString().toLowerCase().includes(term)),
        );
    }

    return [...assets].sort(compareAssets);
});

const moveTargetOptions = computed(() => [
    {
        id: null,
        name: t('downloads.folders.root', 'Root'),
        indentedName: t('downloads.folders.root', 'Root'),
    },
    ...folderRows.value
        .filter((folder) => folder.id !== selectedDialogFolder.value?.id)
        .map((folder) => ({
            ...folder,
            indentedName: `${'-- '.repeat(folder.depth)}${folder.name}`,
        })),
]);

const accessModeOptions = computed(() => [
    { value: 'inherit', label: t('downloads.access.inherit', 'Inherit') },
    { value: 'public', label: t('downloads.access.public', 'Public') },
    {
        value: 'authenticated',
        label: t('downloads.access.authenticated', 'Authenticated'),
    },
    { value: 'password', label: t('downloads.access.password', 'Password') },
    {
        value: 'restricted',
        label: t('downloads.access.restricted', 'Restricted'),
    },
]);

const accessRuleTypeOptions = computed(() => [
    {
        value: 'site_user',
        label: t('downloads.access_rules.types.site_user', 'Site user'),
    },
    {
        value: 'download_group',
        label: t(
            'downloads.access_rules.types.download_group',
            'Download group',
        ),
    },
    {
        value: 'profile_field',
        label: t('downloads.access_rules.types.profile_field', 'Profile field'),
    },
]);

const profileOperatorOptions = computed(() => [
    { value: 'equals', label: t('downloads.operators.equals', 'Equals') },
    {
        value: 'not_equals',
        label: t('downloads.operators.not_equals', 'Does not equal'),
    },
    { value: 'in', label: t('downloads.operators.in', 'In list') },
    {
        value: 'not_in',
        label: t('downloads.operators.not_in', 'Not in list'),
    },
    { value: 'contains', label: t('downloads.operators.contains', 'Contains') },
    { value: 'filled', label: t('downloads.operators.filled', 'Is filled') },
]);

const tableData = computed(() => ({
    data: visibleAssets.value,
    total: visibleAssets.value.length,
}));

const downloadFolderOptions = computed(() => [
    { id: null, name: t('downloads.no_folder', 'No folder') },
    ...localFolders.value,
]);

const editDialogDescription = computed(
    () =>
        selectedEditAsset.value?.original_filename ||
        selectedEditAsset.value?.filename ||
        t('downloads.edit_description', 'Edit download metadata and access.'),
);

const editFormDirty = computed(
    () =>
        stableStringify(editForm.value) !== stableStringify(editBaseline.value),
);

const columns = computed(() => [
    {
        key: 'id',
        label: commonT('columns.id', 'ID'),
        type: 'number',
        clickable: true,
        filterable: true,
        width: 90,
    },
    {
        key: 'title',
        label: t('downloads.columns.title', 'Title'),
        type: 'text',
        filterable: true,
    },
    {
        key: 'original_filename',
        label: t('downloads.columns.file', 'File'),
        type: 'text',
        filterable: true,
    },
    {
        key: 'folder_name',
        label: t('downloads.columns.folder', 'Folder'),
        type: 'text',
        filterable: true,
    },
    {
        key: 'extension',
        label: t('downloads.columns.extension', 'Extension'),
        type: 'text',
        filterable: true,
    },
    {
        key: 'access_mode',
        label: t('downloads.columns.access_mode', 'Access'),
        type: 'text',
        filterable: true,
    },
    {
        key: 'size_kb',
        label: t('downloads.columns.size_kb', 'Size KB'),
        type: 'number',
    },
    {
        key: 'updated_at',
        label: commonT('record_meta.updated_at', 'Updated'),
        type: 'datetime',
    },
]);

function compareAssets(left, right) {
    return (
        (left.sort_order ?? 0) - (right.sort_order ?? 0) ||
        (right.id ?? 0) - (left.id ?? 0)
    );
}

function initializeEditDialog(asset) {
    selectedEditAsset.value = asset ?? null;
    editForm.value = defaultEditForm(asset);
    editBaseline.value = defaultEditForm(asset);
    editErrors.value = {};
    editFlash.value = { type: '', message: '' };
    replaceFileValue.value = null;
    replaceErrors.value = {};

    if (replaceFileInput.value) {
        resetReplaceFileInput();
    }

    editDialogOpen.value = Boolean(asset);
}

function defaultEditForm(asset = null) {
    return {
        folder_id: asset?.folder_id ?? null,
        title: asset?.title ?? '',
        description: asset?.description ?? '',
        access_mode: asset?.access_mode ?? 'inherit',
        published_at: toDateTimeLocal(asset?.published_at),
        expires_at: toDateTimeLocal(asset?.expires_at),
        sort_order: asset?.sort_order ?? 0,
        access_rules: normalizeAccessRules(asset?.access_rules),
    };
}

function onEditDialogOpenChange(open) {
    if (open) {
        editDialogOpen.value = true;
        return;
    }

    closeEditDialog();
}

function closeEditDialog() {
    if (editFormDirty.value) {
        const confirmed = window.confirm(
            t(
                'downloads.edit_discard_confirm',
                'Discard unsaved download changes?',
            ),
        );

        if (!confirmed) {
            editDialogOpen.value = true;
            return;
        }
    }

    editDialogOpen.value = false;
    router.visit(route('admin.cms.downloads.index'), {
        replace: true,
        preserveScroll: true,
    });
}

async function submitEditDownload() {
    if (!selectedEditAsset.value) {
        return;
    }

    editProcessing.value = true;
    editErrors.value = {};
    editFlash.value = { type: '', message: '' };

    try {
        const response = await axios.post(
            route('admin.cms.downloads.update', {
                download: selectedEditAsset.value.id,
            }),
            editPayload(),
            { headers: { Accept: 'application/json' } },
        );

        applyUpdatedEditAsset(response.data?.asset);
        editFlash.value = {
            type: 'success',
            message: t('downloads.saved_flash', 'Download saved.'),
        };
        localFlash.value = { type: '', message: '' };
    } catch (error) {
        editErrors.value = fieldErrors(error);
        editFlash.value = {
            type: 'danger',
            message: firstErrorMessage(
                error,
                t('downloads.save_failed', 'Saving the download failed.'),
            ),
        };
    } finally {
        editProcessing.value = false;
    }
}

function editPayload() {
    return {
        ...editForm.value,
        access_rules: normalizeAccessRules(editForm.value.access_rules).map(
            (rule) => ({
                ...rule,
                value: serializedAccessRuleValue(rule),
            }),
        ),
    };
}

function applyUpdatedEditAsset(asset) {
    if (!asset) {
        return;
    }

    updateLocalAsset(asset);
    selectedEditAsset.value = asset;
    editForm.value = defaultEditForm(asset);
    editBaseline.value = defaultEditForm(asset);
}

function onReplaceFileChange(event) {
    replaceFileValue.value = event.target.files?.[0] ?? null;
    replaceErrors.value = {};
}

async function replaceEditFile() {
    if (!selectedEditAsset.value || !replaceFileValue.value) {
        return;
    }

    replaceProcessing.value = true;
    replaceErrors.value = {};
    editFlash.value = { type: '', message: '' };

    const payload = new FormData();
    payload.append('file', replaceFileValue.value);

    try {
        const response = await axios.post(
            route('admin.cms.downloads.replace-file', {
                download: selectedEditAsset.value.id,
            }),
            payload,
            { headers: { Accept: 'application/json' } },
        );

        applyUpdatedEditAsset(response.data?.asset);
        replaceFileValue.value = null;
        resetReplaceFileInput();
        editFlash.value = {
            type: 'success',
            message: t(
                'downloads.replace.saved_flash',
                'Download file replaced.',
            ),
        };
    } catch (error) {
        replaceErrors.value = fieldErrors(error);
        editFlash.value = {
            type: 'danger',
            message: firstErrorMessage(
                error,
                t('downloads.replace.failed', 'Replacing the file failed.'),
            ),
        };
    } finally {
        replaceProcessing.value = false;
    }
}

function addEditAccessRule() {
    editForm.value.access_rules.push(defaultAccessRule());
}

function removeEditAccessRule(index) {
    editForm.value.access_rules.splice(index, 1);
}

function resetReplaceFileInput() {
    const input = replaceFileInput.value?.$el ?? replaceFileInput.value;

    if (input) {
        input.value = '';
    }
}

function fieldErrors(error) {
    const errors = error?.response?.data?.errors || {};
    const normalized = {};

    Object.entries(errors).forEach(([field, messages]) => {
        normalized[field] = Array.isArray(messages) ? messages[0] : messages;
    });

    return normalized;
}

function stableStringify(value) {
    return JSON.stringify(value ?? null);
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return new Intl.DateTimeFormat('nl-BE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
}

function toDateTimeLocal(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toISOString().slice(0, 16);
}

function flattenFolders(parentId, depth) {
    return localFolders.value
        .filter(
            (folder) => Number(folder.parent_id || 0) === Number(parentId || 0),
        )
        .sort(
            (left, right) =>
                (left.sort_order ?? 0) - (right.sort_order ?? 0) ||
                left.name.localeCompare(right.name),
        )
        .flatMap((folder) => [
            { ...folder, depth },
            ...flattenFolders(folder.id, depth + 1),
        ]);
}

function folderById(id) {
    return localFolders.value.find(
        (folder) => Number(folder.id) === Number(id),
    );
}

function folderAssetCount(folderId) {
    return localAssets.value.filter((asset) => {
        if (folderId === null) {
            return !asset.folder_id;
        }

        return Number(asset.folder_id) === Number(folderId);
    }).length;
}

function depthClass(depth) {
    return (
        ['pl-0', 'pl-4', 'pl-8', 'pl-12', 'pl-16', 'pl-20'][
            Math.min(depth, 5)
        ] ?? 'pl-20'
    );
}

function selectFolder(value) {
    selectedFolderId.value = value;
}

function openFilePicker() {
    fileInput.value?.click();
}

function onFileInputChange(event) {
    addFiles(Array.from(event.target.files || []));
    event.target.value = '';
}

function onDropFiles(event) {
    dragOver.value = false;
    addFiles(Array.from(event.dataTransfer?.files || []));
}

function addFiles(files) {
    files.forEach((file) => {
        uploadItems.value.push({
            key: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
            file,
            fileName: file.name,
            folderId: Number.isInteger(Number(selectedFolderId.value))
                ? Number(selectedFolderId.value)
                : null,
            status: 'queued',
            progress: 0,
            error: '',
        });
    });

    processUploadQueue();
}

function processUploadQueue() {
    while (activeUploads.value < maxConcurrentUploads) {
        const nextItem = uploadItems.value.find(
            (item) => item.status === 'queued',
        );

        if (!nextItem) {
            return;
        }

        startDirectUpload(nextItem);
    }
}

async function startDirectUpload(item) {
    activeUploads.value += 1;
    item.status = 'uploading';
    item.progress = 0;
    item.error = '';

    const payload = new FormData();
    payload.append('file', item.file);
    payload.append('access_mode', 'inherit');
    if (item.folderId) {
        payload.append('folder_id', item.folderId);
    }

    try {
        const response = await axios.post(
            route('admin.cms.downloads.store'),
            payload,
            {
                headers: { Accept: 'application/json' },
                onUploadProgress: (event) => {
                    if (event.total) {
                        item.progress = Math.round(
                            (event.loaded / event.total) * 100,
                        );
                    }
                },
            },
        );
        item.status = 'saved';
        item.progress = 100;
        if (response.data?.asset) {
            updateLocalAsset(response.data.asset);
        }
        if (Array.isArray(response.data?.folders)) {
            localFolders.value = response.data.folders;
        }
        removeUploadItem(item);
    } catch (error) {
        item.status = 'error';
        item.error = resolveUploadError(error);
    } finally {
        activeUploads.value -= 1;
        processUploadQueue();
    }
}

function resolveUploadError(error) {
    const errors = error?.response?.data?.errors || {};
    const fieldError = errors.file?.[0] || errors.folder_id?.[0];

    if (fieldError) {
        return fieldError;
    }

    if (error?.response?.status === 413) {
        return t(
            'downloads.upload.too_large_server',
            'The file is larger than the server upload limit.',
        );
    }

    return (
        error?.response?.data?.message ||
        t('downloads.upload.failed_message', 'Upload failed.')
    );
}

function uploadStatusLabel(item) {
    return (
        {
            queued: t('downloads.upload.status_queued', 'Queued'),
            uploading: t('downloads.upload.status_uploading', 'Uploading'),
            saved: t('downloads.upload.status_saved', 'Saved'),
            error: item.error || t('downloads.upload.status_error', 'Failed'),
        }[item.status] ?? item.status
    );
}

function uploadStatusClass(item) {
    return item.status === 'error' ? 'text-red-600' : 'text-slate-500';
}

function retryUpload(item) {
    item.status = 'queued';
    item.error = '';
    item.progress = 0;
    processUploadQueue();
}

function removeUploadItem(item) {
    uploadItems.value = uploadItems.value.filter(
        (uploadItem) => uploadItem.key !== item.key,
    );
}

function openCreateFolder(parentFolder = null) {
    createFolderParentId.value = parentFolder?.id ?? null;
    createFolderName.value = '';
    folderDialogError.value = '';
    createDialogOpen.value = true;
}

async function submitCreateFolder() {
    folderDialogProcessing.value = true;
    folderDialogError.value = '';

    try {
        const response = await axios.post(
            route('admin.cms.download-folders.store'),
            {
                name: createFolderName.value,
                parent_id: createFolderParentId.value,
                access_mode: 'inherit',
                access_rules: [],
            },
            { headers: { Accept: 'application/json' } },
        );

        updateLocalFolder(response.data.folder);
        if (Array.isArray(response.data.folders)) {
            localFolders.value = response.data.folders;
        }
        selectedFolderId.value = String(response.data.folder?.id ?? 'all');
        createDialogOpen.value = false;
        localFlash.value = {
            type: 'success',
            message: t(
                'downloads.folders.created_flash',
                'Folder created successfully.',
            ),
        };
    } catch (error) {
        folderDialogError.value = firstErrorMessage(
            error,
            t('downloads.folders.create_failed', 'Creating folder failed.'),
        );
    } finally {
        folderDialogProcessing.value = false;
    }
}

function openRenameFolder(folder) {
    selectedDialogFolder.value = folder;
    renameFolderName.value = folder.name;
    folderDialogError.value = '';
    renameDialogOpen.value = true;
}

async function submitRenameFolder() {
    if (!selectedDialogFolder.value) {
        return;
    }

    folderDialogProcessing.value = true;
    folderDialogError.value = '';

    try {
        const response = await axios.patch(
            route('admin.cms.download-folders.update', {
                folder: selectedDialogFolder.value.id,
            }),
            folderPayload({
                ...selectedDialogFolder.value,
                name: renameFolderName.value,
            }),
            { headers: { Accept: 'application/json' } },
        );

        updateFolderResponse(response);
        renameDialogOpen.value = false;
    } catch (error) {
        folderDialogError.value = firstErrorMessage(
            error,
            t('downloads.folders.rename_failed', 'Renaming folder failed.'),
        );
    } finally {
        folderDialogProcessing.value = false;
    }
}

function openMoveFolder(folder) {
    selectedDialogFolder.value = folder;
    moveFolderParentId.value = folder.parent_id ?? null;
    folderDialogError.value = '';
    moveDialogOpen.value = true;
}

async function submitMoveFolder() {
    if (!selectedDialogFolder.value) {
        return;
    }

    folderDialogProcessing.value = true;
    folderDialogError.value = '';

    try {
        const response = await axios.patch(
            route('admin.cms.download-folders.move', {
                folder: selectedDialogFolder.value.id,
            }),
            { parent_id: moveFolderParentId.value || null },
            { headers: { Accept: 'application/json' } },
        );

        updateFolderResponse(response);
        moveDialogOpen.value = false;
    } catch (error) {
        folderDialogError.value = firstErrorMessage(
            error,
            t('downloads.folders.move_failed', 'Moving folder failed.'),
        );
    } finally {
        folderDialogProcessing.value = false;
    }
}

function openFolderSettings(folder) {
    selectedDialogFolder.value = folder;
    folderSettingsForm.value = defaultFolderSettingsForm(folder);
    folderDialogError.value = '';
    settingsDialogOpen.value = true;
}

async function submitFolderSettings() {
    if (!selectedDialogFolder.value) {
        return;
    }

    folderDialogProcessing.value = true;
    folderDialogError.value = '';

    try {
        const response = await axios.patch(
            route('admin.cms.download-folders.update', {
                folder: selectedDialogFolder.value.id,
            }),
            folderPayload({
                ...selectedDialogFolder.value,
                ...folderSettingsForm.value,
            }),
            { headers: { Accept: 'application/json' } },
        );

        updateFolderResponse(response);
        settingsDialogOpen.value = false;
        localFlash.value = {
            type: 'success',
            message: t('downloads.folders.saved', 'Download folder saved.'),
        };
    } catch (error) {
        folderDialogError.value = firstErrorMessage(
            error,
            t('downloads.folders.save_failed', 'Saving the folder failed.'),
        );
    } finally {
        folderDialogProcessing.value = false;
    }
}

function folderPayload(folder) {
    return {
        name: folder.name,
        access_mode: folder.access_mode || 'inherit',
        password: folder.password || '',
        clear_password: Boolean(folder.clear_password),
        password_expires_minutes: folder.password_expires_minutes || null,
        access_rules: normalizeAccessRules(folder.access_rules).map((rule) => ({
            ...rule,
            value: serializedAccessRuleValue(rule),
        })),
    };
}

function addFolderAccessRule() {
    folderSettingsForm.value.access_rules.push(defaultAccessRule());
}

function removeFolderAccessRule(index) {
    folderSettingsForm.value.access_rules.splice(index, 1);
}

function resetAccessRuleTarget(rule) {
    rule.site_user_id = null;
    rule.cms_download_group_id = null;
    rule.profile_field_key = '';
    rule.operator = 'equals';
    rule.value = '';
}

function defaultFolderSettingsForm(folder = {}) {
    return {
        name: folder.name ?? '',
        access_mode: folder.access_mode ?? 'inherit',
        password: '',
        clear_password: false,
        password_expires_minutes: folder.password_expires_minutes ?? '',
        access_rules: normalizeAccessRules(folder.access_rules),
    };
}

function defaultAccessRule() {
    return {
        rule_type: 'site_user',
        site_user_id: null,
        cms_download_group_id: null,
        profile_field_key: '',
        operator: 'equals',
        value: '',
    };
}

function normalizeAccessRules(rules) {
    return Array.isArray(rules)
        ? rules.map((rule) => ({
              rule_type: rule.rule_type || 'site_user',
              site_user_id: rule.site_user_id ?? null,
              cms_download_group_id: rule.cms_download_group_id ?? null,
              profile_field_key: rule.profile_field_key ?? '',
              operator: rule.operator || 'equals',
              value: Array.isArray(rule.value)
                  ? rule.value.join(', ')
                  : (rule.value ?? ''),
          }))
        : [];
}

function serializedAccessRuleValue(rule) {
    if (!['in', 'not_in'].includes(rule.operator)) {
        return rule.value;
    }

    return String(rule.value || '')
        .split(',')
        .map((value) => value.trim())
        .filter(Boolean);
}

function updateLocalAsset(asset) {
    const index = localAssets.value.findIndex(
        (item) => Number(item.id) === Number(asset.id),
    );

    if (index === -1) {
        localAssets.value.unshift(asset);
        return;
    }

    localAssets.value.splice(index, 1, asset);
}

function updateLocalFolder(folder) {
    if (!folder) {
        return;
    }

    const index = localFolders.value.findIndex(
        (item) => Number(item.id) === Number(folder.id),
    );

    if (index === -1) {
        localFolders.value.push(folder);
        return;
    }

    localFolders.value.splice(index, 1, folder);
}

function updateFolderResponse(response) {
    if (Array.isArray(response.data?.folders)) {
        localFolders.value = response.data.folders;
        return;
    }

    updateLocalFolder(response.data?.folder);
}

function firstErrorMessage(error, fallback) {
    const errors = error?.response?.data?.errors || {};
    const firstError = Object.values(errors)[0]?.[0];

    return firstError || error?.response?.data?.message || fallback;
}

function onCellClick(field, id) {
    if (field !== 'id') {
        return;
    }

    router.visit(route('admin.cms.downloads.edit', { download: id }));
}

function cellClass({ col }) {
    return col.clickable ? 'cursor-pointer' : null;
}
</script>
