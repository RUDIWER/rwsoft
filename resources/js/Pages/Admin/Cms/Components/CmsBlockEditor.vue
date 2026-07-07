<template>
    <div class="grid gap-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="grid gap-1">
                <Label>{{ editorLabel }}</Label>
                <p class="text-xs text-slate-500">
                    {{
                        t(
                            'components.block_editor.description',
                            'Bouw content op uit veilige, herbruikbare blokken.',
                        )
                    }}
                </p>
            </div>
            <div class="grid w-full gap-3 lg:w-auto">
                <div
                    v-for="group in blockButtonGroups"
                    :key="group.key"
                    class="grid gap-1"
                >
                    <span
                        class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"
                    >
                        {{ group.label }}
                    </span>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="definition in group.definitions"
                            :key="definition.id"
                            type="button"
                            variant="outline"
                            @click="addBlock(definition.id)"
                        >
                            {{ blockDefinitionButtonLabel(definition) }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="blocks.length > 0" class="grid gap-4">
            <div
                v-for="(block, index) in blocks"
                :key="block.uid"
                class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium uppercase tracking-wide text-slate-600"
                        >
                            {{ placeableBlockLabel(block) }}
                        </span>
                        <span class="text-xs text-slate-400"
                            >#{{ index + 1 }}</span
                        >
                        <span
                            v-if="blockPreviewTitle(block)"
                            class="text-xs text-slate-500"
                        >
                            {{ blockPreviewTitle(block) }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="index === 0"
                            @click="moveBlock(index, -1)"
                        >
                            {{ t('components.block_editor.up', 'Omhoog') }}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="index === blocks.length - 1"
                            @click="moveBlock(index, 1)"
                        >
                            {{ t('components.block_editor.down', 'Omlaag') }}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="removeBlock(index)"
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

                <div
                    class="grid gap-2 rounded-md border border-slate-100 bg-slate-50 p-3 md:max-w-xs"
                >
                    <Label>{{
                        t('layouts.sections.width_mode', 'Breedte')
                    }}</Label>
                    <select
                        v-model="block.width_mode"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="content">
                            {{
                                t(
                                    'layouts.sections.width_content',
                                    'Contentbreedte',
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
                    v-if="blockRendererKey(block) === 'text'"
                    class="grid gap-3"
                >
                    <div class="grid gap-2">
                        <Label>{{
                            t('components.block_editor.title', 'Titel')
                        }}</Label>
                        <Input
                            v-model="block.title"
                            :placeholder="
                                t(
                                    'components.block_editor.optional_title',
                                    'Optionele titel',
                                )
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            t('components.block_editor.text', 'Tekst')
                        }}</Label>
                        <textarea
                            v-model="block.text"
                            rows="6"
                            class="min-h-32 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            :placeholder="
                                t(
                                    'components.block_editor.text_placeholder',
                                    'Schrijf de tekst van dit blok',
                                )
                            "
                        ></textarea>
                    </div>
                </div>

                <div
                    v-else-if="blockRendererKey(block) === 'quote'"
                    class="grid gap-3"
                >
                    <div class="grid gap-2">
                        <Label>{{
                            t('components.block_editor.quote', 'Quote')
                        }}</Label>
                        <textarea
                            v-model="block.text"
                            rows="4"
                            class="min-h-24 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            :placeholder="
                                t(
                                    'components.block_editor.quote_placeholder',
                                    'Quote of opvallende tekst',
                                )
                            "
                        ></textarea>
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            t('components.block_editor.source', 'Bron')
                        }}</Label>
                        <Input
                            v-model="block.source"
                            :placeholder="
                                t(
                                    'components.block_editor.optional_source',
                                    'Optionele bron',
                                )
                            "
                        />
                    </div>
                </div>

                <div
                    v-else-if="blockRendererKey(block) === 'image'"
                    class="grid gap-3"
                >
                    <CmsMediaPicker
                        v-model="block.media_asset_id"
                        :assets="localAssets"
                        :folders="localFolders"
                        uploaded-from="cms_block_image"
                        :upload-context-type="uploadContextType"
                        :upload-context-id="uploadContextId"
                        @update:assets="updateAssets"
                        @update:folders="updateFolders"
                    />
                    <div class="grid gap-2">
                        <Label>{{
                            t('components.block_editor.caption', 'Bijschrift')
                        }}</Label>
                        <Input
                            v-model="block.caption"
                            :placeholder="
                                t(
                                    'components.block_editor.optional_caption',
                                    'Optioneel bijschrift',
                                )
                            "
                        />
                    </div>
                </div>

                <div
                    v-else-if="blockRendererKey(block) === 'button'"
                    class="grid gap-3 md:grid-cols-2"
                >
                    <div class="grid gap-2">
                        <Label>{{
                            t('components.block_editor.label', 'Label')
                        }}</Label>
                        <Input
                            v-model="block.label"
                            :placeholder="
                                t(
                                    'components.block_editor.button_label_placeholder',
                                    'Bijvoorbeeld: Lees meer',
                                )
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            t('components.block_editor.url', 'URL')
                        }}</Label>
                        <Input
                            v-model="block.url"
                            :placeholder="
                                t(
                                    'components.block_editor.url_placeholder',
                                    '/contact of https://...',
                                )
                            "
                        />
                    </div>
                </div>

                <div
                    v-else-if="blockRendererKey(block) === 'form'"
                    class="grid gap-3"
                >
                    <div class="grid gap-2">
                        <Label>{{
                            t('components.block_editor.form', 'Formulier')
                        }}</Label>
                        <select
                            v-model="block.form_translation_key"
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="">
                                {{
                                    t(
                                        'components.block_editor.choose_form',
                                        'Kies een formulier',
                                    )
                                }}
                            </option>
                            <option
                                v-for="formItem in forms"
                                :key="`${formItem.translation_key}-${formItem.locale}`"
                                :value="formItem.translation_key"
                            >
                                {{ formItem.title }} ({{ formItem.locale }})
                            </option>
                        </select>
                    </div>
                </div>

                <div
                    v-else-if="blockRendererKey(block) === 'breadcrumb'"
                    class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
                >
                    <p class="text-sm text-slate-600">
                        {{
                            t(
                                'components.block_editor.breadcrumb_help',
                                'The breadcrumb is automatically built from the current page, category or blog structure.',
                            )
                        }}
                    </p>
                    <div class="grid gap-2 md:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="block.show_current"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300"
                            />
                            {{
                                t(
                                    'components.block_editor.show_current_page',
                                    'Show current page',
                                )
                            }}
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="block.show_on_home"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300"
                            />
                            {{
                                t(
                                    'components.block_editor.show_on_home',
                                    'Show on homepage',
                                )
                            }}
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="block.compact"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300"
                            />
                            {{
                                t(
                                    'components.block_editor.compact_display',
                                    'Compact display',
                                )
                            }}
                        </label>
                    </div>
                    <div class="grid gap-2 md:grid-cols-2">
                        <div class="grid gap-1">
                            <Label>{{
                                t(
                                    'components.block_editor.home_icon',
                                    'Home icon',
                                )
                            }}</Label>
                            <Input
                                v-model="block.home_icon"
                                :placeholder="
                                    t(
                                        'components.block_editor.home_icon_placeholder',
                                        'mdi-home',
                                    )
                                "
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label>{{
                                t(
                                    'components.block_editor.breadcrumb_separator',
                                    'Separator',
                                )
                            }}</Label>
                            <select
                                v-model="block.separator"
                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="›">›</option>
                                <option value=">">&gt;</option>
                                <option value="/">/</option>
                                <option value="•">•</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div v-else-if="isListBlock(block)" class="grid gap-4">
                    <div class="grid gap-2">
                        <Label>{{
                            t('components.block_editor.title', 'Titel')
                        }}</Label>
                        <Input
                            v-model="block.title"
                            :placeholder="
                                t(
                                    'components.block_editor.optional_list_title',
                                    'Optionele lijsttitel',
                                )
                            "
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{
                            t('components.block_editor.source_type', 'Bron')
                        }}</Label>
                        <select
                            v-model="block.source_type"
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="category">
                                {{
                                    t(
                                        'components.block_editor.source_category',
                                        'Categorieen',
                                    )
                                }}
                            </option>
                            <option value="tag">
                                {{
                                    t(
                                        'components.block_editor.source_tag',
                                        'Tags',
                                    )
                                }}
                            </option>
                        </select>
                    </div>

                    <div
                        v-if="block.source_type === 'category'"
                        class="grid gap-2"
                    >
                        <Label>{{
                            t(
                                'components.block_editor.category_source',
                                'Categoriebron',
                            )
                        }}</Label>
                        <select
                            v-model="block.category_source"
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="current">
                                {{
                                    t(
                                        'components.block_editor.current_category',
                                        'Huidige categoriepagina',
                                    )
                                }}
                            </option>
                            <option value="fixed">
                                {{
                                    t(
                                        'components.block_editor.fixed_category',
                                        'Vaste categorie',
                                    )
                                }}
                            </option>
                            <option value="all">
                                {{
                                    t(
                                        'components.block_editor.all_articles',
                                        'Alle artikelen',
                                    )
                                }}
                            </option>
                        </select>
                    </div>

                    <div
                        v-if="
                            block.source_type === 'category' &&
                            block.category_source === 'fixed'
                        "
                        class="grid gap-2"
                    >
                        <Label>{{
                            t(
                                'components.block_editor.fixed_category',
                                'Vaste categorie',
                            )
                        }}</Label>
                        <select
                            v-model="block.category_id"
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="">
                                {{
                                    t(
                                        'components.block_editor.choose_category',
                                        'Kies categorie',
                                    )
                                }}
                            </option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.title }} ({{ category.locale }})
                            </option>
                        </select>
                    </div>

                    <div v-if="block.source_type === 'tag'" class="grid gap-2">
                        <Label>{{
                            t('components.block_editor.tag_source', 'Tagbron')
                        }}</Label>
                        <select
                            v-model="block.tag_source"
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="current">
                                {{
                                    t(
                                        'components.block_editor.current_tag',
                                        'Huidige tagpagina',
                                    )
                                }}
                            </option>
                            <option value="fixed">
                                {{
                                    t(
                                        'components.block_editor.fixed_tag',
                                        'Vaste tag',
                                    )
                                }}
                            </option>
                            <option value="all">
                                {{
                                    t(
                                        'components.block_editor.all_tagged_articles',
                                        'Alle artikelen met tags',
                                    )
                                }}
                            </option>
                        </select>
                    </div>

                    <div
                        v-if="
                            block.source_type === 'tag' &&
                            block.tag_source === 'fixed'
                        "
                        class="grid gap-2"
                    >
                        <Label>{{
                            t('components.block_editor.fixed_tag', 'Vaste tag')
                        }}</Label>
                        <select
                            v-model="block.tag_id"
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="">
                                {{
                                    t(
                                        'components.block_editor.choose_tag',
                                        'Kies tag',
                                    )
                                }}
                            </option>
                            <option
                                v-for="tag in tags"
                                :key="tag.id"
                                :value="tag.id"
                            >
                                {{ tag.title }} ({{ tag.locale }})
                            </option>
                        </select>
                    </div>

                    <label
                        v-if="block.source_type === 'category'"
                        class="flex items-start gap-2 rounded-md border border-slate-200 p-3 text-sm"
                    >
                        <input
                            v-model="block.show_only_subcategories"
                            type="checkbox"
                            class="mt-1 h-4 w-4 rounded border-slate-300"
                        />
                        <span>
                            <span class="block font-medium text-slate-900">{{
                                t(
                                    'components.block_editor.show_subcategories_only',
                                    'Toon enkel subcategorieen',
                                )
                            }}</span>
                            <span class="block text-xs text-slate-500">
                                {{
                                    t(
                                        'components.block_editor.show_subcategories_help',
                                        'Als er geen subcategorieen zijn, toont de lijst automatisch artikelen.',
                                    )
                                }}
                            </span>
                        </span>
                    </label>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="grid gap-2">
                            <Label>{{
                                t('components.block_editor.limit', 'Aantal')
                            }}</Label>
                            <Input
                                v-model="block.limit"
                                type="number"
                                min="1"
                                max="100"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>{{
                                t(
                                    'components.block_editor.sort_by',
                                    'Sorteer op',
                                )
                            }}</Label>
                            <select
                                v-model="block.sort_field"
                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="published_at">
                                    {{
                                        t(
                                            'components.block_editor.published_at',
                                            'Publicatiedatum',
                                        )
                                    }}
                                </option>
                                <option value="title">
                                    {{
                                        t(
                                            'components.block_editor.title',
                                            'Titel',
                                        )
                                    }}
                                </option>
                                <option value="created_at">
                                    {{
                                        t(
                                            'components.block_editor.created_at',
                                            'Creatiedatum',
                                        )
                                    }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label>{{
                                t(
                                    'components.block_editor.direction',
                                    'Richting',
                                )
                            }}</Label>
                            <select
                                v-model="block.sort_direction"
                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="desc">
                                    {{
                                        t(
                                            'components.block_editor.descending',
                                            'Aflopend',
                                        )
                                    }}
                                </option>
                                <option value="asc">
                                    {{
                                        t(
                                            'components.block_editor.ascending',
                                            'Oplopend',
                                        )
                                    }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div
                        class="grid gap-2 rounded-md border border-slate-200 p-3"
                    >
                        <Label>{{
                            t(
                                'components.block_editor.display_options',
                                'Weergave opties',
                            )
                        }}</Label>
                        <div class="grid gap-2 md:grid-cols-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="block.show_search"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                />
                                {{
                                    t(
                                        'components.block_editor.show_search',
                                        'Zoekveld tonen',
                                    )
                                }}
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="block.show_image"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                />
                                {{
                                    t(
                                        'components.block_editor.show_image',
                                        'Afbeelding tonen',
                                    )
                                }}
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="block.show_excerpt"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                />
                                {{
                                    t(
                                        'components.block_editor.show_excerpt',
                                        'Omschrijving tonen',
                                    )
                                }}
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="block.show_date"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                />
                                {{
                                    t(
                                        'components.block_editor.show_date',
                                        'Datum tonen',
                                    )
                                }}
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="block.show_categories"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                />
                                {{
                                    block.source_type === 'tag'
                                        ? t(
                                              'components.block_editor.show_tags',
                                              'Tags tonen',
                                          )
                                        : t(
                                              'components.block_editor.show_categories',
                                              'Categorieen tonen',
                                          )
                                }}
                            </label>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>{{
                            t(
                                'components.block_editor.empty_text_label',
                                'Tekst bij geen resultaten',
                            )
                        }}</Label>
                        <Input
                            v-model="block.empty_text"
                            :placeholder="
                                t(
                                    'components.block_editor.empty_text_placeholder',
                                    'Er zijn nog geen resultaten.',
                                )
                            "
                        />
                    </div>
                </div>

                <div
                    v-else-if="hasRegistryEditorFields(block)"
                    :class="registryEditorGridClasses(block)"
                >
                    <template
                        v-for="field in registryEditorFields(block)"
                        :key="field.name"
                    >
                        <label
                            v-if="field.type === 'checkbox'"
                            :class="registryFieldWrapperClasses(field)"
                        >
                            <input
                                v-model="block[field.name]"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300"
                            />
                            {{ registryFieldLabel(field) }}
                        </label>

                        <CmsRepeaterFieldEditor
                            v-else-if="field.type === 'repeater'"
                            :field="field"
                            :items="repeaterItems(block, field)"
                            :label="registryFieldLabel(field)"
                            :item-preview-title="
                                (item) => repeaterItemPreviewTitle(item, field)
                            "
                            @add="addRepeaterItem(block, field)"
                            @update:items="block[field.name] = $event"
                        >
                            <template #default="{ item, childField }">
                                <div class="grid gap-2">
                                    <Label>{{
                                        registryFieldLabel(childField)
                                    }}</Label>
                                    <textarea
                                        v-if="childField.type === 'textarea'"
                                        v-model="item[childField.name]"
                                        :rows="childField.rows || 3"
                                        class="min-h-20 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        :placeholder="
                                            registryFieldPlaceholder(childField)
                                        "
                                    ></textarea>
                                    <Input
                                        v-else
                                        v-model="item[childField.name]"
                                        :type="childField.type || 'text'"
                                        :placeholder="
                                            registryFieldPlaceholder(childField)
                                        "
                                    />
                                </div>
                            </template>
                        </CmsRepeaterFieldEditor>

                        <div
                            v-else-if="field.type === 'select'"
                            :class="registryFieldWrapperClasses(field)"
                        >
                            <Label>{{ registryFieldLabel(field) }}</Label>
                            <select
                                v-model="block[field.name]"
                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option
                                    v-for="option in field.options || []"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ registryFieldOptionLabel(option) }}
                                </option>
                            </select>
                        </div>

                        <div
                            v-else-if="field.type === 'media_select'"
                            :class="registryFieldWrapperClasses(field)"
                        >
                            <CmsMediaPicker
                                v-model="block[field.name]"
                                :assets="localAssets"
                                :folders="localFolders"
                                uploaded-from="cms_block_media_field"
                                :upload-context-type="uploadContextType"
                                :upload-context-id="uploadContextId"
                                @update:assets="updateAssets"
                                @update:folders="updateFolders"
                            />
                        </div>

                        <div
                            v-else-if="field.type === 'form_select'"
                            :class="registryFieldWrapperClasses(field)"
                        >
                            <Label>{{ registryFieldLabel(field) }}</Label>
                            <select
                                v-model="block[field.name]"
                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="">
                                    {{
                                        t(
                                            'components.block_editor.choose_form',
                                            'Kies een formulier',
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="formItem in forms"
                                    :key="`${formItem.translation_key}-${formItem.locale}`"
                                    :value="formItem.translation_key"
                                >
                                    {{ formItem.title }} ({{ formItem.locale }})
                                </option>
                            </select>
                        </div>

                        <div
                            v-else-if="field.type === 'download_select'"
                            :class="registryFieldWrapperClasses(field)"
                        >
                            <Label>{{ registryFieldLabel(field) }}</Label>
                            <RwAutoCompleteInput
                                v-model="block[field.name]"
                                :items="downloads"
                                item-title="title"
                                item-value="id"
                                :search-fields="[
                                    'title',
                                    'filename',
                                    'original_filename',
                                ]"
                                :placeholder="
                                    t(
                                        'components.block_editor.choose_download',
                                        'Choose a download',
                                    )
                                "
                            />
                        </div>

                        <div
                            v-else-if="field.type === 'download_list'"
                            :class="registryFieldWrapperClasses(field)"
                        >
                            <Label>{{ registryFieldLabel(field) }}</Label>
                            <RwAutoCompleteInput
                                v-model="block[field.name]"
                                :items="downloads"
                                item-title="title"
                                item-value="id"
                                :search-fields="[
                                    'title',
                                    'filename',
                                    'original_filename',
                                ]"
                                multiple
                                :selection-chips="true"
                            />
                        </div>

                        <div
                            v-else-if="field.type === 'download_folder_select'"
                            :class="registryFieldWrapperClasses(field)"
                        >
                            <Label>{{ registryFieldLabel(field) }}</Label>
                            <RwAutoCompleteInput
                                v-model="block[field.name]"
                                :items="downloadFolders"
                                item-title="name"
                                item-value="id"
                                :search-fields="['name']"
                                :placeholder="
                                    t(
                                        'components.block_editor.choose_download_folder',
                                        'Choose a download folder',
                                    )
                                "
                            />
                        </div>

                        <div
                            v-else-if="field.type === 'download_folder_list'"
                            :class="registryFieldWrapperClasses(field)"
                        >
                            <Label>{{ registryFieldLabel(field) }}</Label>
                            <RwAutoCompleteInput
                                v-model="block[field.name]"
                                :items="downloadFolders"
                                item-title="name"
                                item-value="id"
                                :search-fields="['name']"
                                multiple
                                :selection-chips="true"
                            />
                        </div>

                        <div
                            v-else-if="field.type === 'rich_text'"
                            :class="registryFieldWrapperClasses(field)"
                        >
                            <Label>{{ registryFieldLabel(field) }}</Label>
                            <CmsRichTextEditor
                                v-model="block[field.name]"
                                :placeholder="registryFieldPlaceholder(field)"
                                :media-options="localAssets"
                                :media-folders="localFolders"
                                :upload-context-type="uploadContextType"
                                :upload-context-id="uploadContextId"
                                @update:media-options="updateAssets"
                                @update:media-folders="updateFolders"
                            />
                        </div>

                        <div
                            v-else-if="field.type === 'markdown'"
                            :class="registryFieldWrapperClasses(field)"
                        >
                            <Label>{{ registryFieldLabel(field) }}</Label>
                            <RwCodeEditor
                                v-model="block[field.name]"
                                language="markdown"
                                theme="graphite"
                                height="260px"
                                :line-wrapping="true"
                                :placeholder="registryFieldPlaceholder(field)"
                            />
                        </div>

                        <div
                            v-else-if="
                                ['textarea', 'code'].includes(field.type)
                            "
                            :class="registryFieldWrapperClasses(field)"
                        >
                            <Label>{{ registryFieldLabel(field) }}</Label>
                            <textarea
                                v-model="block[field.name]"
                                :rows="field.rows || 4"
                                class="min-h-24 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                :placeholder="registryFieldPlaceholder(field)"
                            ></textarea>
                        </div>

                        <div v-else :class="registryFieldWrapperClasses(field)">
                            <Label>{{ registryFieldLabel(field) }}</Label>
                            <Input
                                v-model="block[field.name]"
                                :type="field.type || 'text'"
                                :min="field.min"
                                :max="field.max"
                                :placeholder="registryFieldPlaceholder(field)"
                            />
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div
            v-else
            class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500"
        >
            {{
                t(
                    'components.block_editor.empty_blocks',
                    'Nog geen content blocks. Voeg een tekst-, quote-, afbeelding- of knopblok toe.',
                )
            }}
        </div>
    </div>
</template>

<script setup>
import RwCodeEditor from '@/Components/RwCodeEditor.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import CmsRichTextEditor from '@/Pages/Admin/Cms/Components/CmsRichTextEditor.vue';
import CmsMediaPicker from '@/Pages/Admin/Cms/Components/CmsMediaPicker.vue';
import CmsRepeaterFieldEditor from '@/Pages/Admin/Cms/Components/CmsRepeaterFieldEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { computed, ref, watch } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    assets: {
        type: Array,
        default: () => [],
    },
    folders: {
        type: Array,
        default: () => [],
    },
    downloads: {
        type: Array,
        default: () => [],
    },
    downloadFolders: {
        type: Array,
        default: () => [],
    },
    forms: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    tags: {
        type: Array,
        default: () => [],
    },
    label: {
        type: String,
        default: '',
    },
    placeableBlocks: {
        type: Array,
        default: () => [],
    },
    contactSettings: {
        type: Object,
        default: () => ({}),
    },
    locale: {
        type: String,
        default: 'en',
    },
    uploadContextType: {
        type: String,
        default: '',
    },
    uploadContextId: {
        type: [Number, String],
        default: null,
    },
});

const emit = defineEmits([
    'update:modelValue',
    'update:assets',
    'update:folders',
]);

const localAssets = ref([...props.assets]);
const localFolders = ref([...props.folders]);

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

const knownBlockRendererKeys = [
    'breadcrumb',
    'text',
    'quote',
    'image',
    'button',
    'form',
    'list_rows',
    'list_grid',
];
const supportedRegistryFieldTypes = [
    'text',
    'textarea',
    'number',
    'checkbox',
    'select',
    'media_select',
    'download_select',
    'download_list',
    'download_folder_select',
    'download_folder_list',
    'rich_text',
    'markdown',
    'form_select',
    'repeater',
];
const blocks = ref(props.modelValue.map((block) => normalizeBlock(block)));
const editorLabel = computed(
    () =>
        props.label ||
        t('components.block_editor.default_label', 'Content blocks'),
);
const catalogBlocks = computed(() =>
    props.placeableBlocks.filter(
        (definition) =>
            Array.isArray(definition?.allowed_zones) &&
            definition.allowed_zones.includes('content') &&
            !definition.requires_permission,
    ),
);
const registryFallbackDefinitions = computed(() =>
    catalogBlocks.value.filter(
        (definition) =>
            blockCategory(definition) === 'content' &&
            !knownBlockRendererKeys.includes(definition?.renderer_key) &&
            placeableBlockEditorFields(definition).length > 0 &&
            placeableBlockEditorFields(definition).every((field) =>
                supportedRegistryFieldTypes.includes(field.type || 'text'),
            ),
    ),
);
const blockButtonGroups = computed(() => {
    const groupedRendererKeys = [
        'text',
        'quote',
        'image',
        'button',
        'form',
        'breadcrumb',
        'list_rows',
        'list_grid',
        'download_list',
        'download_browser',
        'accordion',
        'tabs',
        'carousel',
        'faq',
        'steps',
        'icon_list',
    ];
    const groups = [
        {
            key: 'basic',
            label: t('components.block_editor.group_basic', 'Basis'),
            rendererKeys: [
                'text',
                'rich_text',
                'markdown_text',
                'quote',
                'image',
                'button',
                'form',
                'breadcrumb',
            ],
        },
        {
            key: 'lists',
            label: t('components.block_editor.group_lists', 'Lijsten'),
            rendererKeys: [
                'list_rows',
                'list_grid',
                'download_list',
                'download_browser',
            ],
        },
        {
            key: 'interactive',
            label: t(
                'components.block_editor.group_interactive',
                'Interactief',
            ),
            rendererKeys: ['accordion', 'tabs', 'carousel', 'faq'],
        },
        {
            key: 'structured',
            label: t('components.block_editor.group_structured', 'Structuur'),
            rendererKeys: ['steps', 'icon_list'],
        },
        {
            key: 'other',
            label: t('components.block_editor.group_other', 'Overige'),
            definitions: registryFallbackDefinitions.value.filter(
                (definition) =>
                    !groupedRendererKeys.includes(definition.renderer_key),
            ),
        },
    ];

    return groups
        .map((group) => ({
            ...group,
            definitions:
                group.definitions ||
                group.rendererKeys
                    .map((rendererKey) =>
                        buttonDefinitionForRendererKey(rendererKey),
                    )
                    .filter(Boolean),
        }))
        .filter((group) => group.definitions.length > 0);
});

watch(
    blocks,
    () => {
        applyAddressBlockContactDefaultsToBlocks();

        emit(
            'update:modelValue',
            blocks.value.map((block) => serializeBlock(block)),
        );
    },
    { deep: true },
);

watch(
    () => props.contactSettings,
    () => {
        if (applyAddressBlockContactDefaultsToBlocks()) {
            emit(
                'update:modelValue',
                blocks.value.map((block) => serializeBlock(block)),
            );
        }
    },
    { deep: true, immediate: true },
);

function addBlock(placeableBlockId) {
    blocks.value.push(defaultBlock(placeableBlockId));
}

function removeBlock(index) {
    blocks.value = blocks.value.filter(
        (block, blockIndex) => blockIndex !== index,
    );
}

function moveBlock(index, direction) {
    const nextIndex = index + direction;

    if (nextIndex < 0 || nextIndex >= blocks.value.length) {
        return;
    }

    const nextBlocks = [...blocks.value];
    const [block] = nextBlocks.splice(index, 1);
    nextBlocks.splice(nextIndex, 0, block);
    blocks.value = nextBlocks;
}

function defaultBlock(placeableBlockId = defaultPlaceableBlockId()) {
    return normalizeBlock({ cms_placeable_block_id: placeableBlockId });
}

function normalizeBlock(block) {
    const placeableBlockId = Number(block?.cms_placeable_block_id || 0);
    const definition = placeableBlockDefinitionById(placeableBlockId);
    const rendererKey = definition?.renderer_key || 'block';

    const normalized = {
        uid:
            block.uid ||
            `${rendererKey}-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        cms_placeable_block_id: placeableBlockId,
        placeable_block_revision_id:
            block.placeable_block_revision_id ||
            definition?.revision_id ||
            null,
        width_mode: ['content', 'display'].includes(block.width_mode)
            ? block.width_mode
            : 'content',
        title: block.title || block.heading || '',
        text: block.text || block.content || block.body || '',
        source: block.source || '',
        media_asset_id: block.media_asset_id || '',
        caption: block.caption || '',
        label: block.label || '',
        url: block.url || '',
        form_translation_key: block.form_translation_key || '',
        show_current: Boolean(block.show_current ?? true),
        show_on_home: Boolean(block.show_on_home ?? true),
        compact: Boolean(block.compact ?? false),
        home_icon: block.home_icon || 'mdi-home',
        separator: ['›', '>', '/', '•'].includes(block.separator)
            ? block.separator
            : '›',
        source_type: block.source_type || 'category',
        category_source: block.category_source || 'all',
        category_id: block.category_id || '',
        tag_source: block.tag_source || 'all',
        tag_id: block.tag_id || '',
        show_only_subcategories: Boolean(
            block.show_only_subcategories ?? false,
        ),
        limit: block.limit || 24,
        sort_field: block.sort_field || 'published_at',
        sort_direction: block.sort_direction || 'desc',
        show_search: Boolean(block.show_search ?? false),
        show_excerpt: Boolean(block.show_excerpt ?? true),
        show_image: Boolean(block.show_image ?? true),
        show_date: Boolean(block.show_date ?? true),
        show_categories: Boolean(block.show_categories ?? true),
        empty_text: block.empty_text || '',
        _contact_defaults_applied: truthyBlockValue(
            block._contact_defaults_applied,
        ),
    };

    registryEditorFields(normalized).forEach((field) => {
        if (Object.prototype.hasOwnProperty.call(normalized, field.name)) {
            return;
        }

        normalized[field.name] = registryFieldDefaultValue(
            field,
            block[field.name],
        );
    });

    return normalized;
}

function applyAddressBlockContactDefaults(block) {
    if (blockRendererKey(block) !== 'address_block') {
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

function applyAddressBlockContactDefaultsToBlocks() {
    let changed = false;

    blocks.value.forEach((block) => {
        if (applyAddressBlockContactDefaults(block)) {
            changed = true;
        }
    });

    return changed;
}

function isEmptyAddressBlockValue(value) {
    return value === null || value === undefined || String(value).trim() === '';
}

function truthyBlockValue(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
}

function serializeBlock(block) {
    const rendererKey = blockRendererKey(block);
    const serialized = {
        cms_placeable_block_id: Number(block.cms_placeable_block_id || 0),
        placeable_block_revision_id: block.placeable_block_revision_id || null,
        width_mode: ['content', 'display'].includes(block.width_mode)
            ? block.width_mode
            : 'content',
    };

    if (rendererKey === 'text') {
        serialized.title = block.title || null;
        serialized.text = block.text || null;
    }

    if (rendererKey === 'quote') {
        serialized.text = block.text || null;
        serialized.source = block.source || null;
    }

    if (rendererKey === 'image') {
        serialized.media_asset_id = block.media_asset_id || null;
        serialized.caption = block.caption || null;
    }

    if (rendererKey === 'button') {
        serialized.label = block.label || null;
        serialized.url = block.url || null;
    }

    if (rendererKey === 'form') {
        serialized.form_translation_key = block.form_translation_key || null;
    }

    if (rendererKey === 'breadcrumb') {
        serialized.show_current = Boolean(block.show_current);
        serialized.show_on_home = Boolean(block.show_on_home);
        serialized.compact = Boolean(block.compact);
        serialized.home_icon = block.home_icon || null;
        serialized.separator = ['›', '>', '/', '•'].includes(block.separator)
            ? block.separator
            : '›';
    }

    if (rendererKey === 'address_block') {
        serialized._contact_defaults_applied = Boolean(
            block._contact_defaults_applied,
        );
    }

    if (isListBlock(block)) {
        serialized.title = block.title || null;
        serialized.source_type = block.source_type || 'category';
        serialized.category_source =
            serialized.source_type === 'category'
                ? block.category_source || 'all'
                : 'all';
        serialized.category_id =
            serialized.source_type === 'category' &&
            block.category_source === 'fixed'
                ? block.category_id || null
                : null;
        serialized.tag_source =
            serialized.source_type === 'tag'
                ? block.tag_source || 'all'
                : 'all';
        serialized.tag_id =
            serialized.source_type === 'tag' && block.tag_source === 'fixed'
                ? block.tag_id || null
                : null;
        serialized.show_only_subcategories = Boolean(
            block.show_only_subcategories,
        );
        serialized.limit = Number(block.limit || 24);
        serialized.sort_field = block.sort_field || 'published_at';
        serialized.sort_direction = block.sort_direction || 'desc';
        serialized.show_search = Boolean(block.show_search);
        serialized.show_excerpt = Boolean(block.show_excerpt);
        serialized.show_image = Boolean(block.show_image);
        serialized.show_date = Boolean(block.show_date);
        serialized.show_categories = Boolean(block.show_categories);
        serialized.empty_text = block.empty_text || null;
    }

    if (
        hasRegistryEditorFields(block) &&
        !knownBlockRendererKeys.includes(rendererKey)
    ) {
        registryEditorFields(block).forEach((field) => {
            serialized[field.name] = serializedRegistryFieldValue(
                field,
                block[field.name],
            );
        });
    }

    return serialized;
}

function placeableBlockLabel(block) {
    const definition = placeableBlockDefinition(block);

    if (definition) {
        return blockDefinitionLabel(definition);
    }

    return t('components.block_editor.block_fallback', 'Blok');
}

function isListBlock(block) {
    return ['list_rows', 'list_grid'].includes(blockRendererKey(block));
}

function blockRendererKey(block) {
    return placeableBlockDefinition(block)?.renderer_key || '';
}

function placeableBlockDefinition(block) {
    return placeableBlockDefinitionById(block?.cms_placeable_block_id);
}

function placeableBlockDefinitionById(id) {
    return (
        catalogBlocks.value.find(
            (definition) => Number(definition.id) === Number(id),
        ) || null
    );
}

function blockDefinitionLabel(definition) {
    return (
        definition?.name || t('components.block_editor.block_fallback', 'Blok')
    );
}

function blockDefinitionButtonLabel(definition) {
    return definition?.name || blockDefinitionLabel(definition);
}

function updateAssets(assets) {
    localAssets.value = [...assets];
    emit('update:assets', localAssets.value);
}

function updateFolders(folders) {
    localFolders.value = [...folders];
    emit('update:folders', localFolders.value);
}

function buttonDefinitionForRendererKey(rendererKey) {
    return catalogBlocks.value.find(
        (definition) => definition.renderer_key === rendererKey,
    );
}

function registryEditorFields(block) {
    const fields = placeableBlockEditorFields(placeableBlockDefinition(block));

    return Array.isArray(fields)
        ? fields.filter((field) =>
              supportedRegistryFieldTypes.includes(field.type || 'text'),
          )
        : [];
}

function placeableBlockEditorFields(definition) {
    const fields =
        definition?.schema?.editor_fields || definition?.editor_fields;

    return Array.isArray(fields) ? fields : [];
}

function blockCategory(definition) {
    return definition?.schema?.category || definition?.category || 'content';
}

function defaultPlaceableBlockId() {
    return (
        buttonDefinitionForRendererKey('text')?.id ||
        catalogBlocks.value[0]?.id ||
        0
    );
}

function hasRegistryEditorFields(block) {
    return registryEditorFields(block).length > 0;
}

function registryEditorGridClasses(block) {
    return registryEditorFields(block).some(
        (field) => field.type === 'textarea',
    )
        ? 'grid gap-3 md:grid-cols-2'
        : 'grid gap-3';
}

function registryFieldWrapperClasses(field) {
    return ['textarea', 'code', 'repeater'].includes(field.type)
        ? 'grid gap-2 md:col-span-2'
        : 'grid gap-2';
}

function registryFieldLabel(field) {
    return (
        registryFieldTranslation(field, 'label') ||
        (field.label_key ? t(field.label_key, field.name) : field.name)
    );
}

function registryFieldPlaceholder(field) {
    return (
        registryFieldTranslation(field, 'placeholder') ||
        (field.placeholder_key ? t(field.placeholder_key, '') : '')
    );
}

function registryFieldTranslation(field, name) {
    const locale = String(props.locale || 'en');
    const baseLocale = locale.split(/[-_]/)[0];

    return (
        field.translations?.[locale]?.[name] ||
        field.translations?.[baseLocale]?.[name] ||
        field.translations?.en?.[name] ||
        ''
    );
}

function registryFieldOptionLabel(option) {
    return option.label_key ? t(option.label_key, option.label) : option.label;
}

function registryFieldDefaultValue(field, value) {
    if (field.type === 'checkbox') {
        return Boolean(value ?? false);
    }

    if (field.type === 'repeater') {
        return normalizeRepeaterItems(value, field);
    }

    return value ?? '';
}

function serializedRegistryFieldValue(field, value) {
    if (field.type === 'checkbox') {
        return Boolean(value);
    }

    if (field.type === 'repeater') {
        return normalizeRepeaterItems(value, field)
            .map((item) => serializedRepeaterItem(item, field))
            .filter((item) => repeaterItemHasValue(item, field));
    }

    return value || null;
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

function blockPreviewTitle(block) {
    const directTitle = block.title || block.label || block.caption || '';

    if (directTitle) {
        return directTitle;
    }

    const repeaterField = registryEditorFields(block).find(
        (field) => field.type === 'repeater',
    );
    const firstItem = repeaterField
        ? normalizeRepeaterItems(block[repeaterField.name], repeaterField).find(
              (item) => repeaterItemHasValue(item, repeaterField),
          )
        : null;

    if (!firstItem) {
        return '';
    }

    const previewField = repeaterChildFields(repeaterField).find(
        (childField) => firstItem[childField.name],
    );

    return previewField ? firstItem[previewField.name] : '';
}

function repeaterItemPreviewTitle(item, field) {
    const previewField = repeaterChildFields(field).find(
        (childField) => item[childField.name],
    );

    return previewField
        ? item[previewField.name]
        : t('components.block_editor.repeater_item_fallback', 'Nieuw item');
}
</script>
