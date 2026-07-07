<template>
    <Head :title="t('media.page_title', 'CMS media')" />

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
                            <span class="mdi mdi-image-multiple text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('media.title', 'Media library') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'media.description',
                                        'Manage image uploads, folders and metadata for the public website.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

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
                            <div>
                                <h2
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{ t('media.folders.title', 'Folders') }}
                                </h2>
                                <p class="mt-1 text-xs text-slate-600">
                                    {{
                                        t(
                                            'media.folders.description',
                                            'Select a folder or create a nested structure.',
                                        )
                                    }}
                                </p>
                            </div>

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
                                                'media.folders.without_folder',
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
                                                    'media.folders.all_media',
                                                    'All media',
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
                                                        'media.folders.actions',
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
                                                        'media.folders.create_root',
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
                                                    : folderDropClass(folder)
                                            "
                                            @click="
                                                selectFolder(String(folder.id))
                                            "
                                            @dragenter.prevent="
                                                onFolderDragOver(folder)
                                            "
                                            @dragover.prevent="
                                                onFolderDragOver(folder, $event)
                                            "
                                            @dragleave="
                                                onFolderDragLeave(folder)
                                            "
                                            @drop.prevent="onFolderDrop(folder)"
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
                                                            'media.folders.actions',
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
                                                            'media.folders.create_subfolder',
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
                                                            'media.folders.rename',
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
                                                            'media.folders.move',
                                                            'Move folder',
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
                                    accept="image/jpeg,image/png,image/gif,image/webp"
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
                                                'media.upload.title',
                                                'Upload images',
                                            )
                                        }}
                                    </h2>
                                    <p class="text-sm text-slate-600">
                                        {{
                                            t(
                                                'media.upload.dropzone',
                                                'Click or drop files here. They are saved directly in the media library.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs font-medium text-blue-700"
                                    >
                                        {{
                                            t(
                                                'media.upload.target_folder_inline',
                                                'Target folder: :folder',
                                                { folder: selectedFolderLabel },
                                            )
                                        }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'media.upload.description',
                                                'Allowed: jpg, jpeg, png, gif and webp. Maximum 20MB.',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="visibleUploadItems.length > 0"
                                class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
                            >
                                <div
                                    v-for="item in visibleUploadItems"
                                    :key="item.key"
                                    class="overflow-hidden rounded-lg border bg-white"
                                    :class="
                                        item.status === 'error'
                                            ? 'border-red-200'
                                            : 'border-slate-200'
                                    "
                                >
                                    <div class="aspect-video bg-slate-100">
                                        <img
                                            :src="uploadPreview(item)"
                                            alt=""
                                            class="h-full w-full object-cover"
                                        />
                                    </div>
                                    <div class="grid gap-2 p-3 text-sm">
                                        <div
                                            class="truncate font-medium text-slate-900"
                                        >
                                            {{ item.fileName }}
                                        </div>
                                        <progress
                                            v-if="item.status === 'uploading'"
                                            class="h-2 w-full"
                                            :value="item.progress"
                                            max="100"
                                        />
                                        <p
                                            class="text-xs"
                                            :class="uploadStatusClass(item)"
                                        >
                                            {{ uploadStatusLabel(item) }}
                                        </p>
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
                                                        'media.upload.retry',
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
                                                        'media.upload.remove',
                                                        'Remove',
                                                    )
                                                }}
                                            </Button>
                                        </div>
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
                                        for="media_search"
                                        class="text-[11px] text-slate-600"
                                    >
                                        {{
                                            t('media.filters.search', 'Search')
                                        }}
                                    </Label>
                                    <Input
                                        id="media_search"
                                        v-model="searchTerm"
                                        type="search"
                                        class="w-full sm:w-80"
                                        :placeholder="
                                            t(
                                                'media.filters.search_placeholder',
                                                'Search filename, alt text or caption',
                                            )
                                        "
                                    />
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-[11px] text-slate-600">
                                        {{ t('media.sort.title', 'Sort') }}
                                    </Label>
                                    <div
                                        class="inline-flex rounded-md border border-slate-200 bg-white p-1"
                                    >
                                        <button
                                            type="button"
                                            :class="sortButtonClass('az')"
                                            :aria-label="
                                                t('media.sort.az', 'A-Z')
                                            "
                                            :title="t('media.sort.az', 'A-Z')"
                                            @click="sortMode = 'az'"
                                        >
                                            <span
                                                class="mdi mdi-sort-alphabetical-ascending text-base"
                                                aria-hidden="true"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            :class="sortButtonClass('za')"
                                            :aria-label="
                                                t('media.sort.za', 'Z-A')
                                            "
                                            :title="t('media.sort.za', 'Z-A')"
                                            @click="sortMode = 'za'"
                                        >
                                            <span
                                                class="mdi mdi-sort-alphabetical-descending text-base"
                                                aria-hidden="true"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            :class="sortButtonClass('custom')"
                                            :aria-label="
                                                t(
                                                    'media.sort.custom',
                                                    'Custom order',
                                                )
                                            "
                                            :title="
                                                t(
                                                    'media.sort.custom',
                                                    'Custom order',
                                                )
                                            "
                                            @click="sortMode = 'custom'"
                                        >
                                            <span
                                                class="mdi mdi-sort-variant-remove text-base"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="border-b border-slate-200">
                            <div class="flex flex-wrap gap-4 px-4 sm:px-5">
                                <button
                                    v-for="tab in tabs"
                                    :key="tab.value"
                                    type="button"
                                    class="-mb-px border-b-2 px-1 py-2 text-sm font-medium transition"
                                    :class="
                                        activeTab === tab.value
                                            ? 'border-blue-600 text-blue-700'
                                            : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'
                                    "
                                    @click="activeTab = tab.value"
                                >
                                    {{ tab.label }}
                                </button>
                            </div>
                        </div>

                        <section
                            v-if="activeTab === 'grid'"
                            class="grid gap-4 px-4 py-4 sm:px-5"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600"
                            >
                                <span>
                                    {{
                                        t(
                                            'media.folders.items_count',
                                            ':shown of :total items',
                                            {
                                                shown: visibleAssets.length,
                                                total: localAssets.length,
                                            },
                                        )
                                    }}
                                </span>
                                <span
                                    v-if="sortMode === 'custom'"
                                    class="text-xs text-slate-500"
                                >
                                    {{
                                        t(
                                            'media.sort.drag_help',
                                            'Drag cards to save a custom order.',
                                        )
                                    }}
                                </span>
                            </div>

                            <div
                                class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-8"
                            >
                                <div
                                    v-for="asset in visibleAssets"
                                    :key="asset.id"
                                    class="group overflow-hidden rounded-md border bg-white transition hover:border-blue-300 hover:shadow-sm"
                                    :class="
                                        draggedAssetId === asset.id
                                            ? 'border-blue-400 ring-2 ring-blue-100'
                                            : 'border-slate-200'
                                    "
                                    :draggable="sortMode === 'custom'"
                                    @dragstart="onAssetDragStart(asset, $event)"
                                    @dragover.prevent="
                                        onAssetDragOver(asset, $event)
                                    "
                                    @drop.prevent="onAssetDrop(asset)"
                                    @dragend="onAssetDragEnd"
                                >
                                    <div
                                        class="flex h-8 items-center justify-between gap-1 border-b border-slate-100 bg-slate-50 px-2 text-[11px]"
                                    >
                                        <span
                                            class="mdi mdi-drag text-base text-slate-500"
                                            :title="
                                                t(
                                                    'media.sort.drag_handle',
                                                    'Drag to move or reorder',
                                                )
                                            "
                                            aria-hidden="true"
                                        />
                                        <div
                                            class="flex shrink-0 items-center gap-1"
                                        >
                                            <span
                                                v-if="assetNeedsMetadata(asset)"
                                                class="mdi mdi-alert-circle text-sm text-red-600"
                                                :title="
                                                    metadataIssueMessages(
                                                        asset,
                                                    ).join(' ')
                                                "
                                                aria-hidden="true"
                                            />
                                            <button
                                                type="button"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded text-slate-500 hover:bg-white hover:text-blue-700"
                                                :aria-label="
                                                    t(
                                                        'media.download',
                                                        'Download',
                                                    )
                                                "
                                                :title="
                                                    t(
                                                        'media.download',
                                                        'Download',
                                                    )
                                                "
                                                @click.stop="
                                                    downloadAsset(asset)
                                                "
                                            >
                                                <span
                                                    class="mdi mdi-download text-sm"
                                                    aria-hidden="true"
                                                />
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded text-slate-500 hover:bg-white hover:text-red-700"
                                                :aria-label="
                                                    t(
                                                        'media.delete.open',
                                                        'Delete image',
                                                    )
                                                "
                                                :title="
                                                    t(
                                                        'media.delete.open',
                                                        'Delete image',
                                                    )
                                                "
                                                @click.stop="
                                                    openDeleteAssetDialog(asset)
                                                "
                                            >
                                                <span
                                                    class="mdi mdi-delete text-sm"
                                                    aria-hidden="true"
                                                />
                                            </button>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="block w-full text-left"
                                        @click="openAsset(asset)"
                                    >
                                        <div class="aspect-[4/3] bg-slate-100">
                                            <img
                                                :src="asset.url"
                                                :alt="assetLabel(asset)"
                                                class="h-full w-full object-cover transition group-hover:scale-[1.02]"
                                            />
                                        </div>
                                        <div
                                            class="grid gap-0.5 p-1.5 text-[10px]"
                                        >
                                            <div
                                                class="truncate font-medium text-slate-800"
                                            >
                                                {{ assetLabel(asset) }}
                                            </div>
                                            <div class="text-slate-500">
                                                {{ asset.width || '?' }} x
                                                {{ asset.height || '?' }} px
                                            </div>
                                            <div
                                                class="truncate text-slate-500"
                                            >
                                                {{ asset.size_kb }} KB ·
                                                {{ asset.extension }}
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <p
                                v-if="localAssets.length === 0"
                                class="text-sm text-slate-500"
                            >
                                {{
                                    t(
                                        'media.grid.empty',
                                        'No media uploaded yet.',
                                    )
                                }}
                            </p>
                            <p
                                v-else-if="visibleAssets.length === 0"
                                class="text-sm text-slate-500"
                            >
                                {{
                                    t(
                                        'media.grid.empty_filter',
                                        'No media found for this folder filter.',
                                    )
                                }}
                            </p>
                        </section>

                        <section v-if="activeTab === 'table'">
                            <RwTable
                                table-id="admin-cms-media-table"
                                :data="tableData"
                                :columns="columns"
                                id-key="id"
                                :initial-height="'calc(100vh - 380px)'"
                                :rows-per-page="25"
                                sort-field="id"
                                sort-order="desc"
                                :row-options="[25, 50, 100, 250]"
                                :cell-class="cellClass"
                                excel="true"
                                @on-cell-click="onCellClick"
                            />
                        </section>
                    </main>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="renameDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="border-b border-slate-200 pb-4">
                    <DialogTitle>{{
                        t('media.folders.rename', 'Rename folder')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'media.folders.rename_description',
                                'Update the folder name. The slug is adjusted automatically.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitRenameFolder">
                    <div class="grid gap-2">
                        <Label for="rename_folder_name">{{
                            t('media.folders.new_name', 'New name')
                        }}</Label>
                        <Input
                            id="rename_folder_name"
                            v-model="renameFolderName"
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
                                class="mdi mdi-content-save text-base text-green-700"
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
                        t('media.folders.create_dialog_title', 'Create folder')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{ createFolderDescription }}
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitCreateFolder">
                    <div class="grid gap-2">
                        <Label for="create_folder_name">{{
                            t('media.folders.name', 'Folder name')
                        }}</Label>
                        <Input
                            id="create_folder_name"
                            v-model="createFolderName"
                            :placeholder="
                                t(
                                    'media.folders.placeholder',
                                    'For example: News',
                                )
                            "
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
                        t('media.folders.move', 'Move folder')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'media.folders.move_description',
                                'Choose a new parent folder without creating loops.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submitMoveFolder">
                    <div class="grid gap-2">
                        <Label for="move_folder_parent">{{
                            t('media.folders.parent', 'Parent folder')
                        }}</Label>
                        <select
                            id="move_folder_parent"
                            v-model="moveFolderParentId"
                            class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="">
                                {{ t('media.folders.root', 'Root') }}
                            </option>
                            <option
                                v-for="folder in moveTargetOptions"
                                :key="folder.id"
                                :value="folder.id"
                            >
                                {{ folder.indentedName }}
                            </option>
                        </select>
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
                                class="mdi mdi-content-save text-base text-green-700"
                                aria-hidden="true"
                            />
                            {{ commonT('actions.save', 'Save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="metadataDialogOpen">
            <DialogContent class="max-w-5xl">
                <DialogHeader class="border-b border-slate-200 pb-4">
                    <DialogTitle>{{
                        t('media.metadata', 'Metadata')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'media.metadata_description',
                                'Edit folder assignment, alt text and captions for each active language.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <form
                    v-if="metadataAsset"
                    class="grid gap-5 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]"
                    @submit.prevent="submitMetadata"
                >
                    <div class="grid content-start gap-3">
                        <div
                            class="overflow-hidden rounded-lg border border-slate-200 bg-slate-100"
                        >
                            <img
                                :src="metadataAsset.url"
                                :alt="assetLabel(metadataAsset)"
                                class="max-h-[60vh] w-full object-contain"
                            />
                        </div>
                        <dl
                            class="grid grid-cols-2 gap-2 text-sm text-slate-600"
                        >
                            <div>
                                <dt class="font-semibold text-slate-900">ID</dt>
                                <dd>{{ metadataAsset.id }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-900">
                                    MIME
                                </dt>
                                <dd>{{ metadataAsset.mime_type }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-900">
                                    {{ t('media.dimensions', 'Dimensions') }}
                                </dt>
                                <dd>
                                    {{ metadataAsset.width || '?' }} x
                                    {{ metadataAsset.height || '?' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-900">
                                    {{ t('media.size', 'Size') }}
                                </dt>
                                <dd>{{ metadataAsset.size_kb }} KB</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="grid content-start gap-4">
                        <div
                            v-if="metadataDialogError"
                            class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                        >
                            {{ metadataDialogError }}
                        </div>

                        <div
                            v-if="metadataDialogIssueMessages.length > 0"
                            class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                        >
                            <div class="flex items-start gap-2">
                                <span
                                    class="mdi mdi-alert-circle mt-0.5 text-base"
                                    aria-hidden="true"
                                />
                                <div class="grid gap-1">
                                    <p class="font-medium">
                                        {{
                                            t(
                                                'media.metadata_issue_title',
                                                'Metadata attention required',
                                            )
                                        }}
                                    </p>
                                    <ul class="list-disc pl-4">
                                        <li
                                            v-for="message in metadataDialogIssueMessages"
                                            :key="message"
                                        >
                                            {{ message }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="metadata_folder_id">
                                {{ t('media.folder', 'Folder') }}
                            </Label>
                            <select
                                id="metadata_folder_id"
                                v-model="metadataForm.folder_id"
                                class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="">
                                    {{ t('media.no_folder', 'No folder') }}
                                </option>
                                <option
                                    v-for="folder in folderRows"
                                    :key="folder.id"
                                    :value="folder.id"
                                >
                                    {{
                                        `${'-- '.repeat(folder.depth)}${folder.name}`
                                    }}
                                </option>
                            </select>
                            <p
                                v-if="metadataErrors.folder_id"
                                class="text-sm text-red-600"
                            >
                                {{ metadataErrors.folder_id[0] }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label>{{ t('media.language', 'Language') }}</Label>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="language in activeLanguages"
                                    :key="language.locale"
                                    type="button"
                                    class="rounded-full border px-2 py-1 text-xs font-medium transition"
                                    :class="
                                        metadataLanguageChipClass(
                                            language.locale,
                                        )
                                    "
                                    @click="metadataLocale = language.locale"
                                >
                                    {{ language.locale.toUpperCase() }}
                                </button>
                            </div>
                            <p class="text-xs text-slate-500">
                                {{
                                    t(
                                        'media.active_language',
                                        'Active language: :language',
                                        {
                                            language:
                                                activeMetadataLanguageLabel,
                                        },
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            v-if="metadataForm.translations[metadataLocale]"
                            class="grid gap-4"
                        >
                            <div class="grid gap-2">
                                <Label :for="`metadata_alt_${metadataLocale}`">
                                    {{
                                        t('media.columns.alt_text', 'Alt text')
                                    }}
                                </Label>
                                <Input
                                    :id="`metadata_alt_${metadataLocale}`"
                                    v-model="
                                        metadataForm.translations[
                                            metadataLocale
                                        ].alt_text
                                    "
                                />
                                <p
                                    v-if="metadataTranslationError('alt_text')"
                                    class="text-sm text-red-600"
                                >
                                    {{ metadataTranslationError('alt_text') }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    :for="`metadata_caption_${metadataLocale}`"
                                >
                                    {{ t('media.caption', 'Caption') }}
                                </Label>
                                <textarea
                                    :id="`metadata_caption_${metadataLocale}`"
                                    v-model="
                                        metadataForm.translations[
                                            metadataLocale
                                        ].caption
                                    "
                                    rows="4"
                                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                                <p
                                    v-if="metadataTranslationError('caption')"
                                    class="text-sm text-red-600"
                                >
                                    {{ metadataTranslationError('caption') }}
                                </p>
                            </div>
                        </div>

                        <DialogFooter class="gap-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        class="h-9 w-9 shadow-none"
                                        :aria-label="
                                            commonT(
                                                'actions.more',
                                                'More actions',
                                            )
                                        "
                                        :title="
                                            commonT(
                                                'actions.more',
                                                'More actions',
                                            )
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
                                                openDeleteAssetDialog(
                                                    metadataAsset,
                                                )
                                            "
                                        >
                                            <span
                                                class="mdi mdi-delete"
                                                aria-hidden="true"
                                            />
                                            {{
                                                t(
                                                    'actions.delete',
                                                    'Verwijderen',
                                                )
                                            }}
                                        </button>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Button
                                type="submit"
                                variant="outline"
                                class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                                :disabled="metadataSaving"
                            >
                                <span
                                    :class="
                                        metadataSaving
                                            ? 'mdi mdi-loading animate-spin text-base text-green-700'
                                            : 'mdi mdi-content-save text-base text-green-700'
                                    "
                                    aria-hidden="true"
                                />
                                {{ commonT('actions.save', 'Save') }}
                            </Button>
                        </DialogFooter>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="border-b border-slate-200 pb-4">
                    <DialogTitle>{{
                        t('media.delete.title', 'Delete image')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'media.delete.description',
                                'This removes the image from the media library.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3">
                    <div
                        v-if="deleteDialogError"
                        class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                    >
                        {{ deleteDialogError }}
                    </div>
                    <p class="text-sm text-slate-600">
                        {{
                            t('media.delete.confirm_text', 'Delete :name?', {
                                name: assetLabel(deleteAsset),
                            })
                        }}
                    </p>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            class="gap-2 border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                            :disabled="deleteProcessing"
                            @click="confirmDeleteAsset"
                        >
                            <span
                                :class="
                                    deleteProcessing
                                        ? 'mdi mdi-loading animate-spin text-base text-red-700'
                                        : 'mdi mdi-delete text-base text-red-700'
                                "
                                aria-hidden="true"
                            />
                            {{ t('actions.delete', 'Verwijderen') }}
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>

<script setup>
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
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

const props = defineProps({
    assets: { type: Array, required: true },
    folders: { type: Array, required: true },
    activeLanguages: { type: Array, default: () => [] },
    defaultLocale: { type: String, required: true },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const locale = computed(() => page.props?.app?.locale || 'nl-BE');

const activeTab = ref('grid');
const selectedFolderId = ref('all');
const sortMode = ref('custom');
const searchTerm = ref('');
const dragOver = ref(false);
const fileInput = ref(null);
const uploadItems = ref([]);
const activeUploads = ref(0);
const localAssets = ref([...props.assets]);
const localFolders = ref([...props.folders]);
const localFlash = ref({ type: '', message: '' });
const draggedAssetId = ref(null);
const dragOverFolderId = ref(null);
const sortSaveTimer = ref(null);
const createDialogOpen = ref(false);
const createFolderParentId = ref(null);
const createFolderName = ref('');
const renameDialogOpen = ref(false);
const moveDialogOpen = ref(false);
const selectedDialogFolder = ref(null);
const renameFolderName = ref('');
const moveFolderParentId = ref('');
const folderDialogProcessing = ref(false);
const folderDialogError = ref('');
const metadataDialogOpen = ref(false);
const metadataAsset = ref(null);
const metadataLocale = ref(props.defaultLocale);
const metadataSaving = ref(false);
const metadataErrors = ref({});
const metadataDialogError = ref('');
const metadataDialogIssueMessages = ref([]);
const deleteDialogOpen = ref(false);
const deleteAsset = ref(null);
const deleteProcessing = ref(false);
const deleteDialogError = ref('');
const maxConcurrentUploads = 3;

const metadataForm = reactive({
    folder_id: '',
    translations: {},
});

function openFilePicker() {
    fileInput.value?.click();
}

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

onBeforeUnmount(() => {
    uploadItems.value.forEach((item) => {
        if (item.localUrl) {
            URL.revokeObjectURL(item.localUrl);
        }
    });

    if (sortSaveTimer.value) {
        clearTimeout(sortSaveTimer.value);
    }
});

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

const tabs = computed(() => [
    { value: 'grid', label: t('media.tabs.grid', 'Grid') },
    { value: 'table', label: t('media.tabs.table', 'Table') },
]);

const activeLanguages = computed(() =>
    props.activeLanguages.length > 0
        ? props.activeLanguages
        : [{ locale: props.defaultLocale, native_name: props.defaultLocale }],
);

const activeMetadataLanguageLabel = computed(() => {
    const language = activeLanguages.value.find(
        (item) => item.locale === metadataLocale.value,
    );

    return language
        ? `${language.native_name || language.name || language.locale} (${language.locale})`
        : metadataLocale.value;
});

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
        return t('media.folders.all_media', 'All media');
    }

    if (selectedFolderId.value === 'none') {
        return t('media.folders.without_folder', 'Without folder');
    }

    return (
        folderById(Number(selectedFolderId.value))?.name ??
        t('media.no_folder', 'No folder')
    );
});

const createFolderDescription = computed(() => {
    if (!createFolderParentId.value) {
        return t(
            'media.folders.create_root_description',
            'Create a new folder at root level.',
        );
    }

    return t(
        'media.folders.create_child_description',
        'Create a subfolder under :name.',
        {
            name: folderById(Number(createFolderParentId.value))?.name ?? '-',
        },
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
                asset.alt_text,
                asset.caption,
                asset.original_filename,
                asset.filename,
                asset.path,
            ]
                .filter(Boolean)
                .some((value) => value.toString().toLowerCase().includes(term)),
        );
    }

    return [...assets].sort((left, right) => compareAssets(left, right));
});

const moveTargetOptions = computed(() =>
    folderRows.value
        .filter((folder) => folder.id !== selectedDialogFolder.value?.id)
        .map((folder) => ({
            ...folder,
            indentedName: `${'-- '.repeat(folder.depth)}${folder.name}`,
        })),
);

function folderDropClass(folder) {
    if (dragOverFolderId.value === folder.id) {
        return 'bg-blue-50 text-blue-800 ring-1 ring-blue-200';
    }

    return folderClass;
}

function sortButtonClass(mode) {
    const base =
        'inline-flex h-8 w-8 items-center justify-center rounded text-sm transition';

    if (sortMode.value === mode) {
        return `${base} bg-blue-100 text-blue-700`;
    }

    return `${base} text-slate-600 hover:bg-slate-50 hover:text-slate-950`;
}

const tableData = computed(() => ({
    data: visibleAssets.value.map((asset) => ({
        ...asset,
        dimensions: `${asset.width || '?'} x ${asset.height || '?'}`,
        updated_at_display: formatDate(asset.updated_at),
    })),
    total: visibleAssets.value.length,
}));

const columns = computed(() => [
    {
        key: 'id',
        label: t('common.columns.id', 'ID'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        clickable: true,
        width: 90,
    },
    {
        key: 'original_filename',
        label: t('media.columns.file', 'File'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'folder_name',
        label: t('media.columns.folder', 'Folder'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'alt_text',
        label: t('media.columns.alt_text', 'Alt text'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'mime_type',
        label: 'MIME',
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'dimensions',
        label: t('media.columns.dimensions', 'Dimensions'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'size_kb',
        label: 'KB',
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'updated_at_display',
        label: t('common.columns.updated_at', 'Updated'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
]);

function compareAssets(left, right) {
    if (sortMode.value === 'az' || sortMode.value === 'za') {
        const compared = assetLabel(left).localeCompare(
            assetLabel(right),
            undefined,
            {
                sensitivity: 'base',
            },
        );

        return sortMode.value === 'za' ? compared * -1 : compared;
    }

    return (
        (left.sort_order ?? 0) - (right.sort_order ?? 0) ||
        (right.id ?? 0) - (left.id ?? 0)
    );
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
            route('admin.cms.media-folders.store'),
            {
                name: createFolderName.value,
                parent_id: createFolderParentId.value,
            },
            {
                headers: { Accept: 'application/json' },
            },
        );

        updateLocalFolder(response.data.folder);
        createDialogOpen.value = false;
        localFlash.value = {
            type: 'success',
            message: t(
                'media.folders.created_flash',
                'Folder created successfully.',
            ),
        };
    } catch (error) {
        folderDialogError.value =
            error?.response?.data?.errors?.name?.[0] ||
            error?.response?.data?.message ||
            t('media.folders.create_failed', 'Creating folder failed.');
    } finally {
        folderDialogProcessing.value = false;
    }
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
    const imageFiles = files.filter((file) => file.type.startsWith('image/'));

    imageFiles.forEach((file) => {
        uploadItems.value.push({
            key: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
            file,
            fileName: file.name,
            localUrl: URL.createObjectURL(file),
            previewUrl: '',
            asset: null,
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
    if (item.folderId) {
        payload.append('folder_id', item.folderId);
    }

    try {
        const response = await axios.post(
            route('admin.cms.media.store'),
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
        const asset = response.data?.asset ?? null;
        item.asset = asset;
        item.previewUrl = asset?.url ?? '';
        item.status = response.data?.already_exists ? 'duplicate' : 'saved';
        item.progress = 100;
        if (asset) {
            updateLocalAsset(asset);
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
            'media.upload.too_large_server',
            'The file is larger than the server upload limit.',
        );
    }

    return (
        error?.response?.data?.message ||
        t('media.upload.failed_message', 'Upload failed.')
    );
}

function uploadPreview(item) {
    return item.previewUrl || item.localUrl || '';
}

function uploadStatusLabel(item) {
    return (
        {
            queued: t('media.upload.status_queued', 'Queued'),
            uploading: t('media.upload.status_uploading', 'Uploading'),
            saved: t('media.upload.status_saved', 'Saved'),
            duplicate: t('media.upload.status_duplicate', 'Already in library'),
            error: item.error || t('media.upload.status_error', 'Failed'),
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
    if (item.localUrl) {
        URL.revokeObjectURL(item.localUrl);
    }

    uploadItems.value = uploadItems.value.filter(
        (uploadItem) => uploadItem.key !== item.key,
    );
}

function assetLabel(asset) {
    if (!asset) {
        return '-';
    }

    return (
        asset.alt_text ||
        asset.original_filename ||
        asset.filename ||
        asset.path ||
        `#${asset.id}`
    );
}

function assetNeedsMetadata(asset) {
    return metadataIssueMessages(asset).length > 0;
}

function openAsset(asset) {
    metadataAsset.value = asset;
    metadataLocale.value = props.defaultLocale;
    metadataForm.folder_id = asset.folder_id ?? '';
    metadataForm.translations = normalizeMetadataTranslations(asset);
    metadataErrors.value = {};
    metadataDialogError.value = '';
    metadataDialogIssueMessages.value = metadataIssueMessages(asset);
    metadataDialogOpen.value = true;
}

function metadataIssueMessages(asset) {
    if (!asset) {
        return [];
    }

    const messages = [];
    const defaultTranslation = asset.translations?.[props.defaultLocale] ?? {};
    const defaultAltText = asset.alt_text || defaultTranslation.alt_text || '';

    if (!String(defaultAltText).trim()) {
        messages.push(
            t(
                'media.metadata_issue_default_alt_missing',
                'Alt text is missing for the default language (:locale).',
                { locale: props.defaultLocale },
            ),
        );
    }

    const missingLocales = activeLanguages.value
        .map((language) => language.locale)
        .filter((locale) => locale !== props.defaultLocale)
        .filter(
            (locale) =>
                !String(asset.translations?.[locale]?.alt_text || '').trim(),
        );

    if (missingLocales.length > 0) {
        messages.push(
            t(
                'media.metadata_issue_translation_alt_missing',
                'Alt text is missing for translations: :locales.',
                { locales: missingLocales.join(', ') },
            ),
        );
    }

    return messages;
}

function downloadAsset(asset) {
    if (!asset?.url) {
        return;
    }

    window.open(asset.url, '_blank', 'noopener,noreferrer');
}

function openDeleteAssetDialog(asset) {
    if (!asset) {
        return;
    }

    deleteAsset.value = asset;
    deleteDialogError.value = '';
    deleteDialogOpen.value = true;
}

async function confirmDeleteAsset() {
    if (!deleteAsset.value) {
        return;
    }

    deleteProcessing.value = true;
    deleteDialogError.value = '';

    try {
        await axios.delete(
            route('admin.cms.media.destroy', { id: deleteAsset.value.id }),
            {
                headers: { Accept: 'application/json' },
            },
        );

        const deletedId = deleteAsset.value.id;
        localAssets.value = localAssets.value.filter(
            (asset) => Number(asset.id) !== Number(deletedId),
        );

        if (
            metadataAsset.value &&
            Number(metadataAsset.value.id) === Number(deletedId)
        ) {
            metadataDialogOpen.value = false;
            metadataAsset.value = null;
        }

        deleteDialogOpen.value = false;
        deleteAsset.value = null;
        localFlash.value = {
            type: 'success',
            message: t('media.delete.deleted', 'Image deleted successfully.'),
        };
    } catch (error) {
        deleteDialogError.value =
            error?.response?.data?.message ||
            t('media.delete.failed', 'Deleting image failed.');
    } finally {
        deleteProcessing.value = false;
    }
}

function normalizeMetadataTranslations(asset) {
    const existing = asset.translations ?? {};

    return Object.fromEntries(
        activeLanguages.value.map((language) => {
            const locale = language.locale;
            const translation = existing[locale] ?? {};
            const isDefaultLocale = locale === props.defaultLocale;

            return [
                locale,
                {
                    alt_text:
                        translation.alt_text ??
                        (isDefaultLocale ? asset.alt_text : '') ??
                        '',
                    caption:
                        translation.caption ??
                        (isDefaultLocale ? asset.caption : '') ??
                        '',
                },
            ];
        }),
    );
}

function metadataTranslationError(field) {
    return (
        metadataErrors.value?.[
            `translations.${metadataLocale.value}.${field}`
        ]?.[0] ?? ''
    );
}

function metadataLanguageChipClass(locale) {
    const isActive = metadataLocale.value === locale;
    const isFilled =
        String(metadataForm.translations?.[locale]?.alt_text ?? '').trim() !==
        '';

    if (isActive) {
        return isFilled
            ? 'border-green-700 bg-green-600 text-white'
            : 'border-red-700 bg-red-600 text-white';
    }

    return isFilled
        ? 'border-green-200 bg-green-50 text-green-800 hover:bg-green-100'
        : 'border-red-200 bg-red-50 text-red-800 hover:bg-red-100';
}

async function submitMetadata() {
    if (!metadataAsset.value) {
        return;
    }

    metadataSaving.value = true;
    metadataErrors.value = {};
    metadataDialogError.value = '';

    const defaultTranslation =
        metadataForm.translations[props.defaultLocale] ?? {};

    try {
        const response = await axios.patch(
            route('admin.cms.media.metadata', { id: metadataAsset.value.id }),
            {
                folder_id: metadataForm.folder_id || null,
                alt_text: defaultTranslation.alt_text || null,
                caption: defaultTranslation.caption || null,
                translations: metadataForm.translations,
                sort_order: metadataAsset.value.sort_order ?? 0,
            },
            {
                headers: { Accept: 'application/json' },
            },
        );

        updateLocalAsset(response.data.asset);
        metadataDialogIssueMessages.value = metadataIssueMessages(
            response.data.asset,
        );
        metadataDialogOpen.value = false;
        localFlash.value = {
            type: 'success',
            message: t(
                'media.metadata_saved',
                'Media metadata saved successfully.',
            ),
        };
    } catch (error) {
        metadataErrors.value = error?.response?.data?.errors || {};
        metadataDialogError.value =
            error?.response?.data?.message ||
            t('media.metadata_save_failed', 'Saving media metadata failed.');
    } finally {
        metadataSaving.value = false;
    }
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

function onAssetDragStart(asset, event) {
    if (sortMode.value !== 'custom') {
        return;
    }

    draggedAssetId.value = asset.id;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(asset.id));
}

function onAssetDragOver(asset, event) {
    if (!draggedAssetId.value || draggedAssetId.value === asset.id) {
        return;
    }

    event.dataTransfer.dropEffect = 'move';
}

function onAssetDrop(targetAsset) {
    if (!draggedAssetId.value || draggedAssetId.value === targetAsset.id) {
        onAssetDragEnd();
        return;
    }

    const orderedIds = visibleAssets.value.map((asset) => asset.id);
    const fromIndex = orderedIds.indexOf(draggedAssetId.value);
    const toIndex = orderedIds.indexOf(targetAsset.id);

    if (fromIndex === -1 || toIndex === -1) {
        onAssetDragEnd();
        return;
    }

    const [movedId] = orderedIds.splice(fromIndex, 1);
    orderedIds.splice(toIndex, 0, movedId);

    orderedIds.forEach((id, index) => {
        const asset = localAssets.value.find((item) => item.id === id);
        if (asset) {
            asset.sort_order = (index + 1) * 10;
        }
    });

    scheduleSortSave();
    onAssetDragEnd();
}

function onAssetDragEnd() {
    draggedAssetId.value = null;
    dragOverFolderId.value = null;
}

function onFolderDragOver(folder, event = null) {
    if (!draggedAssetId.value) {
        return;
    }

    dragOverFolderId.value = folder.id;

    if (event?.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onFolderDragLeave(folder) {
    if (dragOverFolderId.value === folder.id) {
        dragOverFolderId.value = null;
    }
}

async function onFolderDrop(folder) {
    if (!draggedAssetId.value) {
        return;
    }

    const asset = localAssets.value.find(
        (item) => Number(item.id) === Number(draggedAssetId.value),
    );

    dragOverFolderId.value = null;

    if (!asset || Number(asset.folder_id) === Number(folder.id)) {
        return;
    }

    try {
        await moveAssetToFolder(asset, folder.id);
    } finally {
        draggedAssetId.value = null;
    }
}

async function moveAssetToFolder(asset, folderId) {
    try {
        const response = await axios.patch(
            route('admin.cms.media.metadata', { id: asset.id }),
            {
                folder_id: folderId,
                alt_text: asset.alt_text || null,
                caption: asset.caption || null,
                translations: normalizeMetadataTranslations(asset),
                sort_order: asset.sort_order ?? 0,
            },
            {
                headers: { Accept: 'application/json' },
            },
        );

        updateLocalAsset(response.data.asset);
        localFlash.value = {
            type: 'success',
            message: t(
                'media.move_to_folder_saved',
                'Image moved to folder :folder.',
                { folder: folderById(Number(folderId))?.name ?? '-' },
            ),
        };
    } catch (error) {
        localFlash.value = {
            type: 'danger',
            message:
                error?.response?.data?.message ||
                t(
                    'media.move_to_folder_failed',
                    'Moving the image to this folder failed.',
                ),
        };
    }
}

function scheduleSortSave() {
    if (sortSaveTimer.value) {
        clearTimeout(sortSaveTimer.value);
    }

    sortSaveTimer.value = setTimeout(saveSortOrder, 500);
}

async function saveSortOrder() {
    const items = visibleAssets.value.map((asset) => ({
        id: asset.id,
        sort_order: asset.sort_order ?? 0,
    }));

    if (items.length === 0) {
        return;
    }

    try {
        await axios.post(
            route('admin.cms.media.sort'),
            { items },
            {
                headers: { Accept: 'application/json' },
            },
        );
    } catch (error) {
        localFlash.value = {
            type: 'danger',
            message:
                error?.response?.data?.message ||
                t('media.sort.save_failed', 'Saving sort order failed.'),
        };
    }
}

function openRenameFolder(folder) {
    selectedDialogFolder.value = folder;
    renameFolderName.value = folder.name;
    folderDialogError.value = '';
    renameDialogOpen.value = true;
}

function openMoveFolder(folder) {
    selectedDialogFolder.value = folder;
    moveFolderParentId.value = folder.parent_id ?? '';
    folderDialogError.value = '';
    moveDialogOpen.value = true;
}

async function submitRenameFolder() {
    if (!selectedDialogFolder.value) {
        return;
    }

    folderDialogProcessing.value = true;
    folderDialogError.value = '';

    try {
        const response = await axios.patch(
            route('admin.cms.media-folders.update', {
                folder: selectedDialogFolder.value.id,
            }),
            {
                name: renameFolderName.value,
            },
            {
                headers: { Accept: 'application/json' },
            },
        );
        updateLocalFolder(response.data.folder);
        renameDialogOpen.value = false;
    } catch (error) {
        folderDialogError.value =
            error?.response?.data?.message ||
            t('media.folders.rename_failed', 'Renaming folder failed.');
    } finally {
        folderDialogProcessing.value = false;
    }
}

async function submitMoveFolder() {
    if (!selectedDialogFolder.value) {
        return;
    }

    folderDialogProcessing.value = true;
    folderDialogError.value = '';

    try {
        const response = await axios.patch(
            route('admin.cms.media-folders.move', {
                folder: selectedDialogFolder.value.id,
            }),
            {
                parent_id: moveFolderParentId.value || null,
            },
            {
                headers: { Accept: 'application/json' },
            },
        );
        updateLocalFolder(response.data.folder);
        moveDialogOpen.value = false;
    } catch (error) {
        folderDialogError.value =
            error?.response?.data?.message ||
            t('media.folders.move_failed', 'Moving folder failed.');
    } finally {
        folderDialogProcessing.value = false;
    }
}

function updateLocalFolder(folder) {
    const index = localFolders.value.findIndex(
        (item) => Number(item.id) === Number(folder.id),
    );

    if (index === -1) {
        localFolders.value.push(folder);
        return;
    }

    localFolders.value.splice(index, 1, folder);
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function onCellClick(field, id) {
    if (field === 'id') {
        const asset = localAssets.value.find(
            (item) => Number(item.id) === Number(id),
        );

        if (asset) {
            openAsset(asset);
        }
    }
}

function cellClass({ col }) {
    return col.clickable ? 'cursor-pointer' : null;
}
</script>
