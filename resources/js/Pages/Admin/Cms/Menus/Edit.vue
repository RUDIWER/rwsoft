<template>
    <Head :title="pageTitle" />

    <AdminLayout :title="pageTitle">
        <div
            class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,420px)]"
        >
            <div class="grid min-w-0 gap-5">
                <Card>
                    <CardHeader>
                        <CardTitle>{{
                            isEditMode
                                ? t('menus.form.edit_title', 'Menu bewerken')
                                : t('menus.new', 'Nieuw menu')
                        }}</CardTitle>
                        <CardDescription>
                            {{
                                t(
                                    'menus.form.description',
                                    'Definieer een globaal navigatiemenu per locatie.',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-5" @submit.prevent="submitMenu">
                            <LocalizedFieldTabs
                                v-model="menuForm.translations"
                                field="title"
                                :label="t('content_form.title', 'Titel')"
                                input-id="title"
                                :languages="activeLanguages"
                                :default-locale="defaultLocale"
                                :error="
                                    localizedError(menuForm.errors, 'title') ||
                                    menuForm.errors.title
                                "
                            />

                            <section class="grid gap-3">
                                <div>
                                    <h2
                                        class="text-base font-semibold text-slate-900"
                                    >
                                        {{
                                            t('menus.form.placements', 'Places')
                                        }}
                                    </h2>
                                    <p class="mt-1 text-sm text-slate-600">
                                        {{
                                            t(
                                                'menus.form.placements_help',
                                                'Choose where this menu may be selected in menu blocks.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <label
                                        v-for="option in menuPlacementOptions"
                                        :key="option.value"
                                        class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm"
                                    >
                                        <input
                                            v-model="menuForm.placements"
                                            type="checkbox"
                                            :value="option.value"
                                            class="h-4 w-4 rounded border-slate-300 text-blue-600"
                                        />
                                        {{ option.label }}
                                    </label>
                                </div>
                                <p
                                    v-if="menuForm.errors.placements"
                                    class="text-sm text-red-600"
                                >
                                    {{ menuForm.errors.placements }}
                                </p>
                            </section>

                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="menuForm.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                />
                                {{ t('common.columns.active', 'Actief') }}
                            </label>

                            <div class="flex flex-wrap justify-end gap-2">
                                <AdminFormBackButton
                                    :href="backHref"
                                    :dirty="menuForm.isDirty"
                                    :processing="menuForm.processing"
                                    :label="t('actions.back', 'Terug')"
                                    @save="submitMenu"
                                />
                                <Button
                                    v-if="isEditMode"
                                    type="button"
                                    variant="outline"
                                    @click="showRevisionDialog = true"
                                >
                                    {{ t('revisions.open', 'Versies') }}
                                </Button>
                                <AdminFormSaveButton
                                    :dirty="menuForm.isDirty"
                                    :processing="menuForm.processing"
                                    :label="t('actions.save', 'Bewaren')"
                                />
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card v-if="isEditMode">
                    <CardHeader>
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <CardTitle>{{
                                    t('menus.form.menu_items', 'Menu-items')
                                }}</CardTitle>
                                <CardDescription>
                                    {{
                                        t(
                                            'menus.form.items_description',
                                            'Items worden genest op basis van hun bovenliggend item.',
                                        )
                                    }}
                                </CardDescription>
                            </div>
                            <Button
                                as-child
                                type="button"
                                variant="outline"
                                size="sm"
                            >
                                <Link :href="newItemUrl">{{
                                    t('menus.form.new_item', 'Nieuw item')
                                }}</Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <div
                            v-for="item in items"
                            :key="item.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3"
                        >
                            <div
                                class="min-w-0"
                                :style="{ paddingLeft: `${item.depth * 18}px` }"
                            >
                                <div
                                    class="truncate text-sm font-medium text-slate-900"
                                >
                                    {{ item.label }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ typeLabel(item.type) }} -
                                    {{ t('menus.form.order', 'volgorde') }}
                                    {{ item.sort_order }} -
                                    {{
                                        item.is_active
                                            ? t(
                                                  'common.columns.active',
                                                  'Actief',
                                              ).toLowerCase()
                                            : t(
                                                  'common.status.inactive',
                                                  'Inactief',
                                              ).toLowerCase()
                                    }}
                                    <span
                                        v-if="
                                            item.type === 'page' &&
                                            item.page_public_status_label
                                        "
                                    >
                                        -
                                        {{
                                            t(
                                                'menus.form.page_status_prefix',
                                                'pagina',
                                            )
                                        }}
                                        {{
                                            item.page_public_status_label.toLowerCase()
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            item.type === 'category' &&
                                            item.page_public_status_label
                                        "
                                    >
                                        -
                                        {{
                                            t(
                                                'menus.form.category_status_prefix',
                                                'categorie',
                                            )
                                        }}
                                        {{
                                            item.page_public_status_label.toLowerCase()
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            item.type === 'post' &&
                                            item.post_public_status_label
                                        "
                                    >
                                        -
                                        {{
                                            t(
                                                'menus.form.post_status_prefix',
                                                'bericht',
                                            )
                                        }}
                                        {{
                                            item.post_public_status_label.toLowerCase()
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button
                                    as-child
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                >
                                    <Link :href="itemEditUrl(item)">{{
                                        t('menus.form.edit', 'Bewerken')
                                    }}</Link>
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    @click="deleteItem(item)"
                                >
                                    {{ t('actions.delete', 'Verwijderen') }}
                                </Button>
                            </div>
                        </div>

                        <p
                            v-if="items.length === 0"
                            class="text-sm text-slate-500"
                        >
                            {{
                                t(
                                    'menus.form.empty_items',
                                    'Nog geen menu-items.',
                                )
                            }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="isEditMode" class="h-fit min-w-0">
                <CardHeader>
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <CardTitle>{{
                            editingItem
                                ? t(
                                      'menus.form.item_edit_title',
                                      'Menu-item bewerken',
                                  )
                                : t(
                                      'menus.form.item_create_title',
                                      'Menu-item toevoegen',
                                  )
                        }}</CardTitle>
                        <Button
                            v-if="editingItem"
                            as-child
                            type="button"
                            variant="outline"
                            size="sm"
                        >
                            <Link :href="newItemUrl">{{
                                t('menus.form.new_item', 'Nieuw item')
                            }}</Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="min-w-0">
                    <form
                        class="grid min-w-0 gap-4"
                        @submit.prevent="submitItem"
                    >
                        <AiTranslationReviewBanner
                            v-if="editingItem"
                            type="menu_item"
                            :record-id="editingItem.id"
                            :review="editingItem.ai_translation_review"
                        />

                        <div class="grid gap-2">
                            <Label for="item_type">{{
                                t('common.columns.type', 'Type')
                            }}</Label>
                            <select
                                id="item_type"
                                v-model="itemForm.type"
                                class="h-10 w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option
                                    v-for="option in typeOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <p
                                v-if="itemForm.errors.type"
                                class="text-sm text-red-600"
                            >
                                {{ itemForm.errors.type }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="item_locale">{{
                                t('common.columns.locale', 'Taal')
                            }}</Label>
                            <select
                                id="item_locale"
                                v-model="itemForm.locale"
                                :disabled="Boolean(editingItem)"
                                class="h-10 w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100 disabled:text-slate-500"
                            >
                                <option
                                    v-for="language in activeLanguages"
                                    :key="language.locale"
                                    :value="language.locale"
                                >
                                    {{ languageLabel(language) }}
                                </option>
                            </select>
                            <p class="text-xs text-slate-500">
                                {{
                                    t(
                                        'menus.form.locale_help',
                                        'Kies de taal van dit item. Vertalingen worden als aparte menu-items gekoppeld.',
                                    )
                                }}
                            </p>
                            <p
                                v-if="itemForm.errors.locale"
                                class="text-sm text-red-600"
                            >
                                {{ itemForm.errors.locale }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="item_label">{{
                                t('forms.form.label', 'Label')
                            }}</Label>
                            <Input
                                id="item_label"
                                v-model="itemForm.label"
                                :placeholder="
                                    isExternalType
                                        ? t(
                                              'menus.form.menu_label',
                                              'Menu label',
                                          )
                                        : t(
                                              'menus.form.auto_label_placeholder',
                                              'Wordt ingevuld op basis van pagina/bericht/categorie indien leeg',
                                          )
                                "
                            />
                            <p
                                v-if="itemForm.errors.label"
                                class="text-sm text-red-600"
                            >
                                {{ itemForm.errors.label }}
                            </p>
                        </div>

                        <div v-if="isExternalType" class="grid gap-2">
                            <Label for="item_url">{{
                                t('menus.form.external_url', 'Externe URL')
                            }}</Label>
                            <Input
                                id="item_url"
                                v-model="itemForm.url"
                                :placeholder="
                                    t(
                                        'menus.form.external_url_placeholder',
                                        'https://www.voorbeeld.be',
                                    )
                                "
                            />
                            <p
                                v-if="itemForm.errors.url"
                                class="text-sm text-red-600"
                            >
                                {{ itemForm.errors.url }}
                            </p>
                        </div>

                        <div v-if="itemForm.type === 'page'" class="grid gap-2">
                            <Label for="cms_page_id">{{
                                t('menus.form.page', 'Pagina')
                            }}</Label>
                            <select
                                id="cms_page_id"
                                v-model="itemForm.cms_page_id"
                                class="h-10 w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="">
                                    {{
                                        t(
                                            'menus.form.choose_page',
                                            'Kies pagina',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="page in filteredPageOptions"
                                    :key="page.id"
                                    :value="page.id"
                                >
                                    {{ targetOptionLabel(page) }}
                                </option>
                            </select>
                            <p
                                v-if="
                                    selectedPageOption &&
                                    !selectedPageOption.is_public
                                "
                                class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-xs text-orange-800"
                            >
                                {{
                                    t(
                                        'menus.form.page_not_public',
                                        'Deze pagina staat op :status en wordt daarom niet in het publieke menu getoond.',
                                        {
                                            status: selectedPageOption.public_status_label.toLowerCase(),
                                        },
                                    )
                                }}
                            </p>
                            <p
                                v-if="filteredPageOptions.length === 0"
                                class="text-xs text-orange-700"
                            >
                                {{
                                    t(
                                        'menus.form.no_pages',
                                        "Geen pagina's beschikbaar voor :language.",
                                        { language: selectedItemLanguageLabel },
                                    )
                                }}
                            </p>
                            <p
                                v-if="itemForm.errors.cms_page_id"
                                class="text-sm text-red-600"
                            >
                                {{ itemForm.errors.cms_page_id }}
                            </p>
                        </div>

                        <div v-if="itemForm.type === 'post'" class="grid gap-2">
                            <Label for="cms_post_id">{{
                                t('menus.form.post', 'Bericht')
                            }}</Label>
                            <select
                                id="cms_post_id"
                                v-model="itemForm.cms_post_id"
                                class="h-10 w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="">
                                    {{
                                        t(
                                            'menus.form.choose_post',
                                            'Kies bericht',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="post in filteredPostOptions"
                                    :key="post.id"
                                    :value="post.id"
                                >
                                    {{ targetOptionLabel(post) }}
                                </option>
                            </select>
                            <p
                                v-if="
                                    selectedPostOption &&
                                    !selectedPostOption.is_public
                                "
                                class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-xs text-orange-800"
                            >
                                {{
                                    t(
                                        'menus.form.post_not_public',
                                        'Dit bericht staat op :status en wordt daarom niet in het publieke menu getoond.',
                                        {
                                            status: selectedPostOption.public_status_label.toLowerCase(),
                                        },
                                    )
                                }}
                            </p>
                            <p
                                v-if="filteredPostOptions.length === 0"
                                class="text-xs text-orange-700"
                            >
                                {{
                                    t(
                                        'menus.form.no_posts',
                                        'Geen berichten beschikbaar voor :language.',
                                        { language: selectedItemLanguageLabel },
                                    )
                                }}
                            </p>
                            <p
                                v-if="itemForm.errors.cms_post_id"
                                class="text-sm text-red-600"
                            >
                                {{ itemForm.errors.cms_post_id }}
                            </p>
                        </div>

                        <div
                            v-if="itemForm.type === 'category'"
                            class="grid gap-2"
                        >
                            <Label for="cms_category_page_id">{{
                                t('menus.form.category', 'Categorie')
                            }}</Label>
                            <select
                                id="cms_category_page_id"
                                v-model="itemForm.cms_page_id"
                                class="h-10 w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="">
                                    {{
                                        t(
                                            'menus.form.choose_category',
                                            'Kies categorie',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="category in filteredCategoryOptions"
                                    :key="category.category_id"
                                    :value="category.id"
                                >
                                    {{ targetOptionLabel(category) }}
                                </option>
                            </select>
                            <p
                                v-if="
                                    selectedCategoryOption &&
                                    !selectedCategoryOption.is_public
                                "
                                class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-xs text-orange-800"
                            >
                                {{
                                    t(
                                        'menus.form.category_not_public',
                                        'Deze categoriepagina staat op :status en wordt daarom niet in het publieke menu getoond.',
                                        {
                                            status: selectedCategoryOption.public_status_label.toLowerCase(),
                                        },
                                    )
                                }}
                            </p>
                            <p
                                v-if="filteredCategoryOptions.length === 0"
                                class="text-xs text-orange-700"
                            >
                                {{
                                    t(
                                        'menus.form.no_categories',
                                        "Geen categoriepagina's beschikbaar voor :language.",
                                        { language: selectedItemLanguageLabel },
                                    )
                                }}
                            </p>
                            <p
                                v-if="itemForm.errors.cms_page_id"
                                class="text-sm text-red-600"
                            >
                                {{ itemForm.errors.cms_page_id }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="parent_id">{{
                                t('menus.form.parent_item', 'Bovenliggend item')
                            }}</Label>
                            <select
                                id="parent_id"
                                v-model="itemForm.parent_id"
                                class="h-10 w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="">
                                    {{ t('content_form.none', 'Geen') }}
                                </option>
                                <option
                                    v-for="item in parentOptions"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ '-'.repeat(item.depth) }}
                                    {{ item.label }}
                                </option>
                            </select>
                            <p
                                v-if="itemForm.errors.parent_id"
                                class="text-sm text-red-600"
                            >
                                {{ itemForm.errors.parent_id }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="sort_order">{{
                                    t('content_form.sort_order', 'Volgorde')
                                }}</Label>
                                <Input
                                    id="sort_order"
                                    v-model="itemForm.sort_order"
                                    type="number"
                                    min="0"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="target">{{
                                    t('menus.form.target', 'Doel')
                                }}</Label>
                                <select
                                    id="target"
                                    v-model="itemForm.target"
                                    class="h-10 w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                >
                                    <option
                                        v-for="option in targetOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="rel">Rel</Label>
                            <Input
                                id="rel"
                                v-model="itemForm.rel"
                                :placeholder="
                                    t(
                                        'menus.form.rel_placeholder',
                                        'noopener noreferrer',
                                    )
                                "
                            />
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="itemForm.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300"
                            />
                            {{ t('common.columns.active', 'Actief') }}
                        </label>

                        <div
                            v-if="editingItem"
                            class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'content_form.translations',
                                                'Vertalingen',
                                            )
                                        }}
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'menus.form.item_translations_description',
                                                'Maak en open gekoppelde taalversies van dit menu-item.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="
                                        missingItemLanguages.length === 0
                                    "
                                    @click="openItemTranslationDialog"
                                >
                                    {{
                                        t(
                                            'content_form.make_translation',
                                            'Maak vertaling',
                                        )
                                    }}
                                </Button>
                            </div>

                            <div
                                v-if="otherItemTranslations.length > 0"
                                class="grid gap-2"
                            >
                                <div
                                    v-for="translation in otherItemTranslations"
                                    :key="translation.id"
                                    class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm"
                                    :class="
                                        itemTranslationCardClass(translation)
                                    "
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="font-semibold uppercase text-slate-700"
                                            >
                                                {{ translation.locale }}
                                            </span>
                                            <span
                                                class="truncate text-slate-900"
                                            >
                                                {{ translation.label }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ typeLabel(translation.type) }}
                                            <span
                                                v-if="
                                                    translation.type ===
                                                        'category' &&
                                                    translation.category_title
                                                "
                                            >
                                                ·
                                                {{ translation.category_title }}
                                            </span>
                                            <span
                                                v-else-if="
                                                    translation.page_title
                                                "
                                                >·
                                                {{
                                                    translation.page_title
                                                }}</span
                                            >
                                            <span
                                                v-if="
                                                    translation.type ===
                                                        'category' &&
                                                    translation.page_public_status_label
                                                "
                                            >
                                                ·
                                                {{
                                                    t(
                                                        'menus.form.category_status_prefix',
                                                        'categorie',
                                                    )
                                                }}
                                                {{
                                                    translation.page_public_status_label.toLowerCase()
                                                }}
                                            </span>
                                            <span
                                                v-else-if="
                                                    translation.page_public_status_label
                                                "
                                            >
                                                ·
                                                {{
                                                    t(
                                                        'menus.form.page_status_prefix',
                                                        'pagina',
                                                    )
                                                }}
                                                {{
                                                    translation.page_public_status_label.toLowerCase()
                                                }}
                                            </span>
                                            <span v-if="translation.post_title"
                                                >·
                                                {{
                                                    translation.post_title
                                                }}</span
                                            >
                                            <span
                                                v-if="
                                                    translation.post_public_status_label
                                                "
                                            >
                                                ·
                                                {{
                                                    t(
                                                        'menus.form.post_status_prefix',
                                                        'bericht',
                                                    )
                                                }}
                                                {{
                                                    translation.post_public_status_label.toLowerCase()
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                    <Button
                                        as-child
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                    >
                                        <Link
                                            :href="itemEditUrl(translation)"
                                            >{{
                                                t('content_form.open', 'Open')
                                            }}</Link
                                        >
                                    </Button>
                                </div>
                            </div>

                            <p v-else class="text-sm text-slate-500">
                                {{
                                    t(
                                        'menus.form.no_item_translations',
                                        'Nog geen andere gekoppelde menu-itemvertalingen.',
                                    )
                                }}
                            </p>

                            <div
                                v-if="missingItemLanguages.length > 0"
                                class="grid gap-2"
                            >
                                <div
                                    v-for="language in missingItemLanguages"
                                    :key="language.locale"
                                    class="flex items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900"
                                >
                                    <div>
                                        <div class="font-semibold">
                                            {{
                                                t(
                                                    'content_form.missing_prefix',
                                                    'Ontbreekt: :language',
                                                    {
                                                        language:
                                                            languageLabel(
                                                                language,
                                                            ),
                                                    },
                                                )
                                            }}
                                        </div>
                                        <div class="text-xs text-red-700">
                                            <span v-if="language.can_create">
                                                {{
                                                    t(
                                                        'menus.form.ready_to_create_translation',
                                                        'Klaar om als menu-itemvertaling aan te maken.',
                                                    )
                                                }}
                                            </span>
                                            <span v-else>
                                                {{
                                                    t(
                                                        'menus.form.create_linked_content_first',
                                                        'Maak eerst de gekoppelde pagina of het bericht in deze taal.',
                                                    )
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        :disabled="
                                            !language.can_create ||
                                            translationForm.processing
                                        "
                                        @click="
                                            openItemTranslationDialog(
                                                language.locale,
                                            )
                                        "
                                    >
                                        {{
                                            t(
                                                'content_form.create_translation',
                                                'Vertaling maken',
                                            )
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <Button
                                v-if="editingItem"
                                as-child
                                type="button"
                                variant="outline"
                            >
                                <Link
                                    :href="
                                        route('admin.cms.menus.edit', {
                                            id: menu.id,
                                        })
                                    "
                                >
                                    {{ t('menus.form.cancel', 'Annuleren') }}
                                </Link>
                            </Button>
                            <Button
                                type="submit"
                                :disabled="itemForm.processing"
                            >
                                {{
                                    editingItem
                                        ? t(
                                              'menus.form.save_item',
                                              'Item bewaren',
                                          )
                                        : t(
                                              'menus.form.add_item',
                                              'Item toevoegen',
                                          )
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="showItemTranslationDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        t(
                            'menus.form.translation_dialog_title',
                            'Maak menu-itemvertaling',
                        )
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'menus.form.translation_dialog_description',
                                'Kies of het menu-item met AI vertaald wordt of eerst als kopie wordt aangemaakt.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 py-2">
                    <div class="grid gap-2">
                        <Label>{{
                            t('content_form.chosen_language', 'Gekozen taal')
                        }}</Label>
                        <div
                            class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-900"
                        >
                            {{ selectedItemTranslationLanguageLabel }}
                        </div>
                        <p
                            v-if="translationForm.errors.target_locale"
                            class="text-sm text-red-600"
                        >
                            {{ translationForm.errors.target_locale }}
                        </p>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:justify-between">
                    <Button
                        type="button"
                        variant="outline"
                        @click="showItemTranslationDialog = false"
                    >
                        {{ t('actions.back', 'Terug') }}
                    </Button>
                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="
                                translationForm.processing ||
                                !translationForm.target_locale
                            "
                            @click="createItemTranslation(false)"
                        >
                            {{
                                t(
                                    'content_form.copy_original',
                                    'Origineel kopieren',
                                )
                            }}
                        </Button>
                        <Button
                            type="button"
                            :disabled="
                                translationForm.processing ||
                                !translationForm.target_locale
                            "
                            @click="createItemTranslation(true)"
                        >
                            {{
                                t(
                                    'content_form.translate_ai',
                                    'Met AI vertalen',
                                )
                            }}
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <CmsRevisionHistoryDialog
            v-if="isEditMode"
            v-model:open="showRevisionDialog"
            subject-type="menu"
            restore-route-name="admin.cms.menus.revisions.restore"
            :restore-route-params="{ menu: menu.id }"
            :revisions="revisions"
        />
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import CmsRevisionHistoryDialog from '@/Pages/Admin/Cms/Components/CmsRevisionHistoryDialog.vue';
import LocalizedFieldTabs from '@/Pages/Admin/Cms/Components/LocalizedFieldTabs.vue';
import { resolveReturnToUrl } from '@/composables/useReturnToUrl';
import AiTranslationReviewBanner from '@/Pages/Admin/Cms/Partials/AiTranslationReviewBanner.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');

const props = defineProps({
    menu: { type: Object, default: null },
    revisions: { type: Array, default: () => [] },
    items: { type: Array, required: true },
    parentItemOptions: { type: Array, default: () => [] },
    editingItem: { type: Object, default: null },
    itemTranslations: { type: Array, default: () => [] },
    itemMissingLanguages: { type: Array, default: () => [] },
    pageOptions: { type: Array, required: true },
    categoryOptions: { type: Array, required: true },
    postOptions: { type: Array, required: true },
    activeLanguages: { type: Array, required: true },
    defaultLocale: { type: String, required: true },
    typeOptions: { type: Array, required: true },
    targetOptions: { type: Array, required: true },
    menuPlacementOptions: { type: Array, default: () => [] },
});

const emptyTranslations = () =>
    Object.fromEntries(
        props.activeLanguages.map((language) => [language.locale, {}]),
    );

const menuForm = useForm({
    title: props.menu?.title ?? '',
    translations: props.menu?.translations ?? emptyTranslations(),
    placements: Array.isArray(props.menu?.placements)
        ? [...props.menu.placements]
        : [],
    is_active: Boolean(props.menu?.is_active ?? true),
});

const itemForm = useForm({
    parent_id: props.editingItem?.parent_id ?? '',
    locale: props.editingItem?.locale ?? props.defaultLocale,
    translation_key: props.editingItem?.translation_key ?? '',
    type: initialItemType(),
    label: props.editingItem?.label ?? '',
    url: props.editingItem?.url ?? '',
    cms_page_id: props.editingItem?.cms_page_id ?? '',
    cms_post_id: props.editingItem?.cms_post_id ?? '',
    target: props.editingItem?.target ?? '',
    rel: props.editingItem?.rel ?? '',
    sort_order: props.editingItem?.sort_order ?? 0,
    is_active: Boolean(props.editingItem?.is_active ?? true),
});

const isEditMode = computed(() => Boolean(props.menu?.id));
const pageTitle = computed(() =>
    isEditMode.value
        ? t('menus.form.edit_title', 'Menu bewerken')
        : t('menus.form.create_title', 'Menu toevoegen'),
);
const backHref = computed(() =>
    resolveReturnToUrl(route('admin.cms.menus.index')),
);
const activeLanguages = computed(() => props.activeLanguages);
const defaultLocale = computed(() => props.defaultLocale);
const showItemTranslationDialog = ref(false);
const showRevisionDialog = ref(false);
const filteredPageOptions = computed(() =>
    props.pageOptions.filter(
        (page) => !itemForm.locale || page.locale === itemForm.locale,
    ),
);
const filteredCategoryOptions = computed(() =>
    props.categoryOptions.filter(
        (category) => !itemForm.locale || category.locale === itemForm.locale,
    ),
);
const filteredPostOptions = computed(() =>
    props.postOptions.filter(
        (post) => !itemForm.locale || post.locale === itemForm.locale,
    ),
);
const selectedPageOption = computed(
    () =>
        props.pageOptions.find(
            (page) => Number(page.id) === Number(itemForm.cms_page_id),
        ) ?? null,
);
const selectedCategoryOption = computed(
    () =>
        props.categoryOptions.find(
            (category) => Number(category.id) === Number(itemForm.cms_page_id),
        ) ?? null,
);
const selectedPostOption = computed(
    () =>
        props.postOptions.find(
            (post) => Number(post.id) === Number(itemForm.cms_post_id),
        ) ?? null,
);
const newItemUrl = computed(() =>
    route('admin.cms.menus.edit', { id: props.menu?.id }),
);
const isExternalType = computed(() =>
    ['custom', 'external'].includes(itemForm.type),
);
const parentOptions = computed(() =>
    props.parentItemOptions.filter(
        (item) =>
            item.id !== props.editingItem?.id &&
            (!itemForm.locale || item.locale === itemForm.locale),
    ),
);
const otherItemTranslations = computed(() =>
    props.itemTranslations.filter((translation) => !translation.is_current),
);
const missingItemLanguages = computed(() => props.itemMissingLanguages);
const translationForm = useForm({
    target_locale: '',
    use_ai: true,
});
const selectedItemTranslationLanguageLabel = computed(() => {
    const language = missingItemLanguages.value.find(
        (item) => item.locale === translationForm.target_locale,
    );

    return language
        ? languageLabel(language)
        : t('content_form.no_language_selected', 'Geen taal gekozen');
});
const selectedItemLanguageLabel = computed(() => {
    if (!itemForm.locale) {
        return t('menus.form.all_languages', 'alle talen');
    }

    const language = props.activeLanguages.find(
        (item) => item.locale === itemForm.locale,
    );

    return language ? languageLabel(language) : itemForm.locale;
});

watch(
    () => itemForm.locale,
    () => {
        if (props.editingItem) {
            return;
        }

        if (
            itemForm.type === 'page' &&
            !filteredPageOptions.value.some(
                (page) => Number(page.id) === Number(itemForm.cms_page_id),
            )
        ) {
            itemForm.cms_page_id = '';
        }

        if (
            itemForm.type === 'category' &&
            !filteredCategoryOptions.value.some(
                (category) =>
                    Number(category.id) === Number(itemForm.cms_page_id),
            )
        ) {
            itemForm.cms_page_id = '';
        }

        if (
            !filteredPostOptions.value.some(
                (post) => Number(post.id) === Number(itemForm.cms_post_id),
            )
        ) {
            itemForm.cms_post_id = '';
        }

        if (
            !parentOptions.value.some(
                (item) => Number(item.id) === Number(itemForm.parent_id),
            )
        ) {
            itemForm.parent_id = '';
        }
    },
);

watch(
    () => itemForm.type,
    () => {
        if (!['page', 'category'].includes(itemForm.type)) {
            itemForm.cms_page_id = '';
        }

        if (itemForm.type !== 'post') {
            itemForm.cms_post_id = '';
        }

        if (!isExternalType.value) {
            itemForm.url = null;
        }
    },
);

function submitMenu() {
    const fallback = menuForm.translations?.[props.defaultLocale] ?? {};
    menuForm.title = fallback.title || menuForm.title;
    menuForm.post(route('admin.cms.menus.store', { id: props.menu?.id ?? 0 }));
}

function submitItem() {
    itemForm.parent_id = itemForm.parent_id || null;
    itemForm.target = itemForm.target || null;

    if (!['custom', 'external'].includes(itemForm.type)) {
        itemForm.url = null;
    }

    if (!['page', 'category'].includes(itemForm.type)) {
        itemForm.cms_page_id = null;
    }

    if (itemForm.type !== 'post') {
        itemForm.cms_post_id = null;
    }

    itemForm.post(
        route('admin.cms.menu-items.store', {
            menu: props.menu.id,
            item: props.editingItem?.id ?? 0,
        }),
    );
}

function openItemTranslationDialog(locale = '') {
    translationForm.clearErrors();
    translationForm.target_locale =
        locale || missingItemLanguages.value[0]?.locale || '';
    translationForm.use_ai = true;
    showItemTranslationDialog.value = true;
}

function createItemTranslation(useAi) {
    if (!props.menu?.id || !props.editingItem?.id) {
        return;
    }

    translationForm.use_ai = useAi;
    translationForm.post(
        route('admin.cms.menu-items.translations.store', {
            menu: props.menu.id,
            item: props.editingItem.id,
        }),
    );
}

function itemEditUrl(item) {
    return `${route('admin.cms.menus.edit', { id: props.menu.id })}?item=${item.id}`;
}

function deleteItem(item) {
    if (
        !window.confirm(
            t(
                'menus.form.delete_confirm',
                'Dit menu-item verwijderen? Child items worden ook verwijderd.',
            ),
        )
    ) {
        return;
    }

    router.delete(
        route('admin.cms.menu-items.destroy', {
            menu: props.menu.id,
            item: item.id,
        }),
    );
}

function typeLabel(type) {
    if (type === 'custom') {
        return t('menus.form.external_url', 'Externe URL');
    }

    return (
        props.typeOptions.find((option) => option.value === type)?.label ?? type
    );
}

function initialItemType() {
    return props.editingItem?.type === 'custom'
        ? 'external'
        : (props.editingItem?.type ?? 'external');
}

function languageLabel(language) {
    return `${language.native_name || language.name || language.locale} (${language.locale})`;
}

function targetOptionLabel(target) {
    const status = target.public_status_label
        ? ` - ${target.public_status_label}`
        : '';

    return `${target.title}${status}`;
}

function itemTranslationCardClass(translation) {
    const targetIsPublic = ['page', 'category'].includes(translation.type)
        ? translation.page_is_public !== false
        : translation.type === 'post'
          ? translation.post_is_public !== false
          : true;

    return translation.is_active && targetIsPublic
        ? 'border-green-200 bg-green-50'
        : 'border-orange-200 bg-orange-50';
}

function localizedError(errors, field) {
    return errors?.[`translations.${props.defaultLocale}.${field}`] ?? '';
}
</script>
