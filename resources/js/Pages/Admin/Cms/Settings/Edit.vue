<template>
    <Head :title="t('settings.page_title', 'CMS instellingen')" />

    <AdminLayout :suppress-flash="true">
        <Card
            class="flex h-[calc(100vh-8rem)] flex-col overflow-hidden rounded-none shadow-none"
        >
            <CardHeader class="shrink-0 gap-0 border-b border-slate-200 p-0">
                <div
                    class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-5"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                        >
                            <span class="mdi mdi-wrench-cog-outline text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('settings.page_title', 'Settings') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'settings.description',
                                        'Manage public CMS rendering, SEO, branding, AI and admin settings.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <AdminFormBackButton
                            :href="route('admin')"
                            :dirty="form.isDirty"
                            :processing="form.processing"
                            :label="t('actions.back', 'Back')"
                            @save="submit"
                        />
                        <AdminFormSaveButton
                            v-if="!['starter', 'modules'].includes(activeTab)"
                            form="cms-settings-form"
                            :dirty="form.isDirty"
                            :processing="form.processing"
                            :label="t('actions.save', 'Save')"
                        />
                    </div>
                </div>
            </CardHeader>
            <div
                class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 sm:px-5"
            >
                <div class="font-medium text-slate-700">
                    {{ commonT('record_meta.id', 'ID') }}:
                    <span class="ml-1 text-base font-bold text-slate-950">
                        {{ recordIdLabel }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                    <div class="font-medium text-slate-700">
                        {{ commonT('record_meta.updated_at', 'Updated') }}:
                        <span class="ml-1 text-base font-bold text-slate-950">
                            {{ updatedAtLabel }}
                        </span>
                    </div>
                    <div class="font-medium text-slate-700">
                        {{ commonT('record_meta.created_at', 'Created') }}:
                        <span class="ml-1 text-base font-bold text-slate-950">
                            {{ createdAtLabel }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="cardFlash.message" class="shrink-0 px-4 pt-4 sm:px-5">
                <RwFlashMessage
                    :type="cardFlash.type"
                    :message="cardFlash.message"
                />
            </div>

            <CardContent class="min-h-0 flex-1 overflow-y-auto p-0">
                <form id="cms-settings-form" @submit.prevent="submit">
                    <div
                        class="sticky top-0 z-10 border-b border-slate-200 bg-white"
                    >
                        <div class="flex flex-wrap gap-4 px-4 sm:px-5">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                type="button"
                                class="-mb-px border-b-2 px-1 py-2 text-sm font-medium transition"
                                :class="
                                    activeTab === tab.key
                                        ? 'border-blue-600 text-blue-700'
                                        : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'
                                "
                                @click="activeTab = tab.key"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-6 p-4 sm:p-5">
                        <section
                            v-if="activeTab === 'admin'"
                            class="grid gap-6"
                        >
                            <div
                                class="rounded border border-slate-200 bg-white p-4"
                            >
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ t('settings.admin.title', 'Admin') }}
                                </p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{
                                        t(
                                            'settings.admin.subtitle',
                                            'Configure defaults for the tenant admin environment.',
                                        )
                                    }}
                                </p>

                                <div class="mt-4 grid gap-2 md:max-w-md">
                                    <Label
                                        for="admin_default_locale"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{
                                            t(
                                                'settings.form.admin_default_locale',
                                                'Default admin language',
                                            )
                                        }}
                                    </Label>
                                    <select
                                        id="admin_default_locale"
                                        v-model="
                                            form.admin_settings
                                                .admin_default_locale
                                        "
                                        required
                                        class="h-10 rounded-md border border-slate-300 bg-yellow-50 px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option
                                            v-for="locale in adminLocaleOptions"
                                            :key="locale.value"
                                            :value="locale.value"
                                        >
                                            {{ locale.label }}
                                        </option>
                                    </select>
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'settings.form.admin_default_locale_help',
                                                'Used when a user has no personal admin language for this site.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            form.errors[
                                                'admin_settings.admin_default_locale'
                                            ]
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            form.errors[
                                                'admin_settings.admin_default_locale'
                                            ]
                                        }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'general'"
                            class="grid gap-6"
                        >
                            <div class="grid gap-4 md:grid-cols-2">
                                <LocalizedFieldTabs
                                    v-model="form.setting_translations"
                                    field="site_name"
                                    :label="
                                        t('settings.form.site_name', 'Sitenaam')
                                    "
                                    input-id="site_name"
                                    :languages="activeLanguages"
                                    :default-locale="form.default_locale"
                                    :error="
                                        localizedSettingError('site_name') ||
                                        form.errors.site_name
                                    "
                                />

                                <div class="grid gap-2">
                                    <Label for="default_locale">{{
                                        t(
                                            'settings.form.default_locale',
                                            'Standaardtaal',
                                        )
                                    }}</Label>
                                    <select
                                        id="default_locale"
                                        v-model="form.default_locale"
                                        required
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option
                                            v-for="language in activeLanguages"
                                            :key="language.locale"
                                            :value="language.locale"
                                        >
                                            {{ language.native_name }} ({{
                                                language.locale
                                            }})
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors.default_locale"
                                        class="text-sm text-red-600"
                                    >
                                        {{ form.errors.default_locale }}
                                    </p>
                                </div>
                            </div>

                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="form.multilingual_enabled"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                />
                                {{
                                    t(
                                        'settings.form.enable_multilingual',
                                        'Meertalige site inschakelen',
                                    )
                                }}
                            </label>

                            <div
                                class="grid gap-4 rounded-md border border-slate-200 bg-slate-50 p-4"
                            >
                                <div>
                                    <p
                                        class="text-sm font-semibold text-slate-800"
                                    >
                                        {{
                                            t(
                                                'settings.form.auto_locale_title',
                                                'Automatic language selection',
                                            )
                                        }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{
                                            t(
                                                'settings.form.auto_locale_help',
                                                'Use browser language first and optionally country headers as a fallback. Explicit language URLs always win.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2">
                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            v-model="
                                                form.auto_locale_detection_enabled
                                            "
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            t(
                                                'settings.form.auto_locale_detection_enabled',
                                                'Enable automatic language detection',
                                            )
                                        }}
                                    </label>

                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            v-model="
                                                form.auto_locale_redirect_enabled
                                            "
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            t(
                                                'settings.form.auto_locale_redirect_enabled',
                                                'Redirect to the detected language URL',
                                            )
                                        }}
                                    </label>

                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            v-model="
                                                form.auto_locale_remember_choice
                                            "
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            t(
                                                'settings.form.auto_locale_remember_choice',
                                                'Remember manual language choices',
                                            )
                                        }}
                                    </label>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label
                                            for="auto_locale_detection_strategy"
                                        >
                                            {{
                                                t(
                                                    'settings.form.auto_locale_detection_strategy',
                                                    'Detection strategy',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            id="auto_locale_detection_strategy"
                                            v-model="
                                                form.auto_locale_detection_strategy
                                            "
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option
                                                v-for="option in autoLocaleDetectionStrategyOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                form.errors
                                                    .auto_locale_detection_strategy
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors
                                                    .auto_locale_detection_strategy
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="auto_locale_cookie_days">
                                            {{
                                                t(
                                                    'settings.form.auto_locale_cookie_days',
                                                    'Cookie duration in days',
                                                )
                                            }}
                                        </Label>
                                        <input
                                            id="auto_locale_cookie_days"
                                            v-model.number="
                                                form.auto_locale_cookie_days
                                            "
                                            type="number"
                                            min="1"
                                            max="730"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        />
                                        <p
                                            v-if="
                                                form.errors
                                                    .auto_locale_cookie_days
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors
                                                    .auto_locale_cookie_days
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="auto_locale_country_map">
                                        {{
                                            t(
                                                'settings.form.auto_locale_country_map',
                                                'Country to language map',
                                            )
                                        }}
                                    </Label>
                                    <textarea
                                        id="auto_locale_country_map"
                                        v-model="form.auto_locale_country_map"
                                        rows="5"
                                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        :placeholder="
                                            t(
                                                'settings.form.auto_locale_country_map_placeholder',
                                                'BE=nl\nFR=fr\nUS=en',
                                            )
                                        "
                                    ></textarea>
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'settings.form.auto_locale_country_map_help',
                                                'One mapping per line. The country code must come from a trusted CDN/proxy header and the language must be active for this site.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            form.errors.auto_locale_country_map
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            form.errors.auto_locale_country_map
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="grid gap-4 rounded-md border border-slate-200 bg-slate-50 p-4 md:grid-cols-2"
                            >
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        v-model="form.public_text_cache_enabled"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    {{
                                        t(
                                            'settings.form.public_text_cache_enabled',
                                            'Cache openbare vertaalteksten inschakelen',
                                        )
                                    }}
                                </label>

                                <div class="grid gap-2">
                                    <Label for="public_text_cache_ttl">{{
                                        t(
                                            'settings.form.public_text_cache_ttl',
                                            'Cacheduur openbare vertaalteksten',
                                        )
                                    }}</Label>
                                    <input
                                        id="public_text_cache_ttl"
                                        v-model.number="
                                            form.public_text_cache_ttl
                                        "
                                        type="number"
                                        min="0"
                                        max="86400"
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    />
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'settings.form.public_text_cache_ttl_help',
                                                'Aantal seconden. Gebruik 0 om te bewaren tot de volgende wijziging.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="form.errors.public_text_cache_ttl"
                                        class="text-sm text-red-600"
                                    >
                                        {{ form.errors.public_text_cache_ttl }}
                                    </p>
                                </div>
                            </div>

                            <LocalizedFieldTabs
                                v-model="form.setting_translations"
                                field="site_tagline"
                                :label="t('settings.form.tagline', 'Tagline')"
                                input-id="site_tagline"
                                :languages="activeLanguages"
                                :default-locale="form.default_locale"
                                :required-default="false"
                                :error="
                                    localizedSettingError('site_tagline') ||
                                    form.errors.site_tagline
                                "
                            />

                            <div class="grid gap-2">
                                <Label for="homepage_id">{{
                                    t('settings.form.homepage', 'Homepage')
                                }}</Label>
                                <select
                                    id="homepage_id"
                                    v-model="form.homepage_id"
                                    class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                >
                                    <option value="">
                                        {{
                                            t(
                                                'settings.form.no_homepage',
                                                'Geen homepage ingesteld',
                                            )
                                        }}
                                    </option>
                                    <option
                                        v-for="page in filteredPageOptions"
                                        :key="page.id"
                                        :value="page.id"
                                    >
                                        {{ page.title }} ({{ page.locale }} /
                                        {{ page.status }})
                                    </option>
                                </select>
                                <p class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'settings.form.homepage_help',
                                            'De lijst volgt de ingestelde standaardtaal.',
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="form.errors.homepage_id"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.homepage_id }}
                                </p>
                            </div>

                            <div
                                class="grid gap-4 rounded-md border border-slate-200 bg-white p-4"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'settings.contact.title',
                                                'Contact details',
                                            )
                                        }}
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{
                                            t(
                                                'settings.contact.subtitle',
                                                'These values are used as defaults for address blocks and contact output.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div
                                        v-for="field in contactTextFields"
                                        :key="field.key"
                                        class="grid gap-2"
                                    >
                                        <Label :for="field.key">
                                            {{ field.label }}
                                        </Label>
                                        <Input
                                            :id="field.key"
                                            v-model="form[field.key]"
                                            :type="field.type || 'text'"
                                            :placeholder="field.placeholder"
                                        />
                                        <p
                                            v-if="form.errors[field.key]"
                                            class="text-sm text-red-600"
                                        >
                                            {{ form.errors[field.key] }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2 md:col-span-2">
                                        <Label
                                            for="contact_image_media_asset_id"
                                        >
                                            {{
                                                t(
                                                    'settings.form.contact_image_media_asset_id',
                                                    'Contact image',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            id="contact_image_media_asset_id"
                                            v-model="
                                                form.contact_image_media_asset_id
                                            "
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option value="">
                                                {{
                                                    t(
                                                        'settings.form.contact_image_none',
                                                        'No contact image',
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="asset in availableMediaOptions"
                                                :key="asset.id"
                                                :value="asset.id"
                                            >
                                                {{ mediaOptionLabel(asset) }}
                                            </option>
                                        </select>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'settings.form.contact_image_help',
                                                    'Address blocks can use this image when no block-specific image is selected.',
                                                )
                                            }}
                                        </p>
                                        <p
                                            v-if="
                                                form.errors
                                                    .contact_image_media_asset_id
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors
                                                    .contact_image_media_asset_id
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'visitor_tracking'"
                            class="grid gap-6"
                        >
                            <div
                                class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950"
                            >
                                {{
                                    t(
                                        'settings.form.visitor_tracking_info',
                                        'Visitor tracking is disabled by default. Enable it only when your privacy policy and consent flow allow analytics storage.',
                                    )
                                }}
                            </div>

                            <div
                                v-if="
                                    form.visitor_tracking_retention_mode ===
                                    'always'
                                "
                                class="rounded-md border border-orange-200 bg-orange-50 p-4 text-sm text-orange-950"
                            >
                                {{
                                    t(
                                        'settings.form.visitor_tracking_always_warning',
                                        'The retention mode is set to always. Visitor records will not be pruned automatically.',
                                    )
                                }}
                            </div>

                            <div
                                class="grid gap-4 rounded-md border border-slate-200 bg-white p-4"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'settings.visitor_tracking.title',
                                                'Visitor tracking',
                                            )
                                        }}
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{
                                            t(
                                                'settings.visitor_tracking.subtitle',
                                                'Store public CMS visits per tenant site for analytics and later geo enrichment.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2">
                                    <label
                                        v-for="field in visitorTrackingBooleanFields"
                                        :key="field.key"
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            v-model="form[field.key]"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{ field.label }}
                                    </label>
                                </div>

                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="grid gap-2">
                                        <Label
                                            for="visitor_tracking_retention_mode"
                                        >
                                            {{
                                                t(
                                                    'settings.form.visitor_tracking_retention_mode',
                                                    'Retention mode',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            id="visitor_tracking_retention_mode"
                                            v-model="
                                                form.visitor_tracking_retention_mode
                                            "
                                            :items="
                                                visitorTrackingRetentionOptions
                                            "
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label', 'value']"
                                            :placeholder="
                                                t(
                                                    'settings.form.visitor_tracking_retention_mode_placeholder',
                                                    'Choose retention mode',
                                                )
                                            "
                                        />
                                        <p
                                            v-if="
                                                form.errors
                                                    .visitor_tracking_retention_mode
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors
                                                    .visitor_tracking_retention_mode
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            for="visitor_tracking_retention_days"
                                        >
                                            {{
                                                t(
                                                    'settings.form.visitor_tracking_retention_days',
                                                    'Retention days',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            id="visitor_tracking_retention_days"
                                            v-model.number="
                                                form.visitor_tracking_retention_days
                                            "
                                            type="number"
                                            min="1"
                                            max="3650"
                                        />
                                        <p
                                            v-if="
                                                form.errors
                                                    .visitor_tracking_retention_days
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors
                                                    .visitor_tracking_retention_days
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            for="visitor_tracking_cookie_days"
                                        >
                                            {{
                                                t(
                                                    'settings.form.visitor_tracking_cookie_days',
                                                    'Cookie days',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            id="visitor_tracking_cookie_days"
                                            v-model.number="
                                                form.visitor_tracking_cookie_days
                                            "
                                            type="number"
                                            min="1"
                                            max="730"
                                        />
                                        <p
                                            v-if="
                                                form.errors
                                                    .visitor_tracking_cookie_days
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors
                                                    .visitor_tracking_cookie_days
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        for="visitor_tracking_excluded_paths"
                                    >
                                        {{
                                            t(
                                                'settings.form.visitor_tracking_excluded_paths',
                                                'Excluded paths',
                                            )
                                        }}
                                    </Label>
                                    <textarea
                                        id="visitor_tracking_excluded_paths"
                                        v-model="
                                            form.visitor_tracking_excluded_paths
                                        "
                                        rows="5"
                                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        :placeholder="
                                            t(
                                                'settings.form.visitor_tracking_excluded_paths_placeholder',
                                                '/private\n/preview',
                                            )
                                        "
                                    ></textarea>
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'settings.form.visitor_tracking_excluded_paths_help',
                                                'One path prefix per line. Admin, account, theme and crawler files are excluded by default.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            form.errors
                                                .visitor_tracking_excluded_paths
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            form.errors
                                                .visitor_tracking_excluded_paths
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="grid gap-4 rounded-md border border-slate-200 bg-white p-4"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'settings.visitor_tracking.geo_title',
                                                'Geo-IP enrichment',
                                            )
                                        }}
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{
                                            t(
                                                'settings.visitor_tracking.geo_subtitle',
                                                'Geo-IP runs from the scheduled processor, not during public page requests.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        v-model="
                                            form.visitor_tracking_geo_enabled
                                        "
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    {{
                                        t(
                                            'settings.form.visitor_tracking_geo_enabled',
                                            'Enable Geo-IP enrichment',
                                        )
                                    }}
                                </label>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label
                                            for="visitor_tracking_geo_provider"
                                        >
                                            {{
                                                t(
                                                    'settings.form.visitor_tracking_geo_provider',
                                                    'Geo-IP provider',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            id="visitor_tracking_geo_provider"
                                            v-model="
                                                form.visitor_tracking_geo_provider
                                            "
                                            :items="
                                                visitorTrackingGeoProviderOptions
                                            "
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label', 'value']"
                                            :placeholder="
                                                t(
                                                    'settings.form.visitor_tracking_geo_provider_placeholder',
                                                    'Choose Geo-IP provider',
                                                )
                                            "
                                        />
                                        <p
                                            v-if="
                                                form.errors
                                                    .visitor_tracking_geo_provider
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors
                                                    .visitor_tracking_geo_provider
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            for="visitor_tracking_geo_current_key"
                                        >
                                            {{
                                                t(
                                                    'settings.form.visitor_tracking_geo_current_key',
                                                    'Current Geo-IP API key',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            id="visitor_tracking_geo_current_key"
                                            :value="
                                                hasSavedGeoApiKey
                                                    ? '********'
                                                    : ''
                                            "
                                            disabled
                                            readonly
                                        />
                                        <p
                                            class="text-xs"
                                            :class="
                                                hasSavedGeoApiKey
                                                    ? 'text-green-700'
                                                    : 'text-slate-500'
                                            "
                                        >
                                            {{
                                                hasSavedGeoApiKey
                                                    ? t(
                                                          'settings.form.visitor_tracking_geo_key_saved',
                                                          'An encrypted Geo-IP API key is stored.',
                                                      )
                                                    : t(
                                                          'settings.form.visitor_tracking_geo_key_missing',
                                                          'No Geo-IP API key is stored.',
                                                      )
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label
                                            for="visitor_tracking_geo_api_key"
                                        >
                                            {{
                                                t(
                                                    'settings.form.visitor_tracking_geo_api_key',
                                                    'New Geo-IP API key',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            id="visitor_tracking_geo_api_key"
                                            v-model="
                                                form.visitor_tracking
                                                    .geo_api_key
                                            "
                                            type="password"
                                            autocomplete="off"
                                            :disabled="
                                                form.visitor_tracking
                                                    .clear_geo_api_key
                                            "
                                            :placeholder="
                                                t(
                                                    'settings.form.visitor_tracking_geo_api_key_placeholder',
                                                    'Leave empty to keep the current key',
                                                )
                                            "
                                        />
                                        <label
                                            class="flex items-center gap-2 text-sm text-slate-600"
                                        >
                                            <input
                                                v-model="
                                                    form.visitor_tracking
                                                        .clear_geo_api_key
                                                "
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            {{
                                                t(
                                                    'settings.form.visitor_tracking_clear_geo_api_key',
                                                    'Delete stored Geo-IP API key',
                                                )
                                            }}
                                        </label>
                                        <p
                                            v-if="
                                                form.errors[
                                                    'visitor_tracking.geo_api_key'
                                                ]
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors[
                                                    'visitor_tracking.geo_api_key'
                                                ]
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            for="visitor_tracking_geo_allowed_countries"
                                        >
                                            {{
                                                t(
                                                    'settings.form.visitor_tracking_geo_allowed_countries',
                                                    'Allowed countries',
                                                )
                                            }}
                                        </Label>
                                        <textarea
                                            id="visitor_tracking_geo_allowed_countries"
                                            v-model="
                                                form.visitor_tracking_geo_allowed_countries
                                            "
                                            rows="4"
                                            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                            :placeholder="
                                                t(
                                                    'settings.form.visitor_tracking_geo_allowed_countries_placeholder',
                                                    'BE, NL, FR',
                                                )
                                            "
                                        ></textarea>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'settings.form.visitor_tracking_geo_allowed_countries_help',
                                                    'Optional comma-separated ISO country codes. Leave empty to keep all countries.',
                                                )
                                            }}
                                        </p>
                                        <label
                                            class="flex items-center gap-2 text-sm text-slate-600"
                                        >
                                            <input
                                                v-model="
                                                    form.visitor_tracking_geo_delete_disallowed_countries
                                                "
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            {{
                                                t(
                                                    'settings.form.visitor_tracking_geo_delete_disallowed_countries',
                                                    'Delete visits outside allowed countries',
                                                )
                                            }}
                                        </label>
                                        <p
                                            v-if="
                                                form.errors
                                                    .visitor_tracking_geo_allowed_countries
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors
                                                    .visitor_tracking_geo_allowed_countries
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'search_console'"
                            class="grid gap-6"
                        >
                            <div
                                class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950"
                            >
                                {{
                                    t(
                                        'settings.form.search_console_info',
                                        'Connect this site to Google Search Console with its own Google account. Tokens are stored encrypted for this tenant.',
                                    )
                                }}
                            </div>

                            <div
                                class="grid gap-4 rounded-md border border-slate-200 bg-white p-4"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'settings.search_console.title',
                                                'Google Search Console',
                                            )
                                        }}
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{
                                            t(
                                                'settings.search_console.subtitle',
                                                'Configure the property and OAuth client used to load Search Console statistics.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        v-model="form.search_console_enabled"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    {{
                                        t(
                                            'settings.form.search_console_enabled',
                                            'Enable Search Console',
                                        )
                                    }}
                                </label>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label
                                            for="search_console_property_type"
                                        >
                                            {{
                                                t(
                                                    'settings.form.search_console_property_type',
                                                    'Property type',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            id="search_console_property_type"
                                            v-model="
                                                form.search_console_property_type
                                            "
                                            :items="
                                                searchConsolePropertyTypeOptions
                                            "
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label', 'value']"
                                            :placeholder="
                                                t(
                                                    'settings.form.search_console_property_type_placeholder',
                                                    'Choose property type',
                                                )
                                            "
                                        />
                                        <p
                                            v-if="
                                                form.errors
                                                    .search_console_property_type
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors
                                                    .search_console_property_type
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="search_console_site_url">
                                            {{
                                                t(
                                                    'settings.form.search_console_site_url',
                                                    'Search Console property',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            id="search_console_site_url"
                                            v-model="
                                                form.search_console_site_url
                                            "
                                            :placeholder="
                                                searchConsolePropertyPlaceholder
                                            "
                                        />
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'settings.form.search_console_site_url_help',
                                                    'Use the exact URL-prefix property or a domain property such as sc-domain:example.com.',
                                                )
                                            }}
                                        </p>
                                        <p
                                            v-if="
                                                form.errors
                                                    .search_console_site_url
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors
                                                    .search_console_site_url
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label
                                            for="search_console_oauth_client_id"
                                        >
                                            {{
                                                t(
                                                    'settings.form.search_console_oauth_client_id',
                                                    'OAuth client ID',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            id="search_console_oauth_client_id"
                                            v-model="
                                                form.search_console
                                                    .oauth_client_id
                                            "
                                            autocomplete="off"
                                        />
                                        <p
                                            v-if="
                                                form.errors[
                                                    'search_console.oauth_client_id'
                                                ]
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors[
                                                    'search_console.oauth_client_id'
                                                ]
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            for="search_console_oauth_client_secret"
                                        >
                                            {{
                                                t(
                                                    'settings.form.search_console_oauth_client_secret',
                                                    'OAuth client secret',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            id="search_console_oauth_client_secret"
                                            v-model="
                                                form.search_console
                                                    .oauth_client_secret
                                            "
                                            type="password"
                                            autocomplete="new-password"
                                            :placeholder="
                                                hasSavedSearchConsoleClientSecret
                                                    ? t(
                                                          'settings.form.search_console_oauth_client_secret_saved',
                                                          'Encrypted client secret is stored',
                                                      )
                                                    : ''
                                            "
                                        />
                                        <label
                                            v-if="
                                                hasSavedSearchConsoleClientSecret
                                            "
                                            class="flex items-center gap-2 text-xs text-slate-600"
                                        >
                                            <input
                                                v-model="
                                                    form.search_console
                                                        .clear_oauth_client_secret
                                                "
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            {{
                                                t(
                                                    'settings.form.search_console_clear_oauth_client_secret',
                                                    'Delete stored client secret',
                                                )
                                            }}
                                        </label>
                                        <p
                                            v-if="
                                                form.errors[
                                                    'search_console.oauth_client_secret'
                                                ]
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                form.errors[
                                                    'search_console.oauth_client_secret'
                                                ]
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-3">
                                    <div
                                        v-for="field in searchConsoleNumberFields"
                                        :key="field.key"
                                        class="grid gap-2"
                                    >
                                        <Label :for="field.key">{{
                                            field.label
                                        }}</Label>
                                        <Input
                                            :id="field.key"
                                            v-model.number="form[field.key]"
                                            type="number"
                                            :min="field.min"
                                            :max="field.max"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700"
                                >
                                    <div>
                                        <span class="font-medium">
                                            {{
                                                t(
                                                    'settings.form.search_console_callback_url',
                                                    'OAuth callback URL',
                                                )
                                            }}:
                                        </span>
                                        <span class="break-all">
                                            {{
                                                props.searchConsole.callback_url
                                            }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="font-medium">
                                            {{
                                                t(
                                                    'settings.form.search_console_status',
                                                    'Connection status',
                                                )
                                            }}:
                                        </span>
                                        {{ searchConsoleConnectionStatus }}
                                    </div>
                                    <div
                                        v-if="
                                            props.searchConsole.last_success_at
                                        "
                                    >
                                        <span class="font-medium">
                                            {{
                                                t(
                                                    'settings.form.search_console_last_success_at',
                                                    'Last successful check',
                                                )
                                            }}:
                                        </span>
                                        {{
                                            formatDateTime(
                                                props.searchConsole
                                                    .last_success_at,
                                            )
                                        }}
                                    </div>
                                    <div
                                        v-if="props.searchConsole.last_error"
                                        class="text-red-700"
                                    >
                                        <span class="font-medium">
                                            {{
                                                t(
                                                    'settings.form.search_console_last_error',
                                                    'Last error',
                                                )
                                            }}:
                                        </span>
                                        {{ props.searchConsole.last_error }}
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="gap-2 shadow-none"
                                        :disabled="
                                            form.isDirty ||
                                            !searchConsoleCanConnect
                                        "
                                        @click="connectSearchConsole"
                                    >
                                        <span
                                            class="mdi mdi-google text-base"
                                            aria-hidden="true"
                                        />
                                        {{
                                            t(
                                                'settings.form.search_console_connect',
                                                'Connect Google',
                                            )
                                        }}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="gap-2 shadow-none"
                                        :disabled="
                                            form.isDirty ||
                                            !props.searchConsole.has_oauth_token
                                        "
                                        @click="testSearchConsole"
                                    >
                                        <span
                                            class="mdi mdi-check-circle-outline text-base"
                                            aria-hidden="true"
                                        />
                                        {{
                                            t(
                                                'settings.form.search_console_test',
                                                'Test connection',
                                            )
                                        }}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="gap-2 border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                                        :disabled="
                                            form.isDirty ||
                                            !props.searchConsole.has_oauth_token
                                        "
                                        @click="disconnectSearchConsole"
                                    >
                                        <span
                                            class="mdi mdi-link-off text-base"
                                            aria-hidden="true"
                                        />
                                        {{
                                            t(
                                                'settings.form.search_console_disconnect',
                                                'Disconnect Google',
                                            )
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'media'"
                            class="grid gap-6"
                        >
                            <div
                                class="grid gap-4 rounded-md border border-slate-200 bg-white p-4"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'settings.media.title',
                                                'Media uploads',
                                            )
                                        }}
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{
                                            t(
                                                'settings.media.subtitle',
                                                'Configure upload limits and generated image variants for the CMS media library.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-2 md:max-w-sm">
                                    <Label for="media_max_image_upload_mb">
                                        {{
                                            t(
                                                'settings.form.media_max_image_upload_mb',
                                                'Maximum image upload (MB)',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        id="media_max_image_upload_mb"
                                        v-model.number="
                                            form.media_max_image_upload_mb
                                        "
                                        type="number"
                                        min="1"
                                        max="100"
                                        step="1"
                                    />
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'settings.form.media_max_image_upload_mb_help',
                                                'Application limit for image uploads in the CMS media library. Server and proxy limits can still be lower.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            form.errors
                                                .media_max_image_upload_mb
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            form.errors
                                                .media_max_image_upload_mb
                                        }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section v-if="activeTab === 'seo'" class="grid gap-6">
                            <LocalizedFieldTabs
                                v-model="form.setting_translations"
                                field="seo_default_title"
                                :label="
                                    t(
                                        'settings.form.seo_default_title',
                                        'SEO standaardtitel',
                                    )
                                "
                                input-id="seo_default_title"
                                :languages="activeLanguages"
                                :default-locale="form.default_locale"
                                :required-default="false"
                                :error="
                                    localizedSettingError(
                                        'seo_default_title',
                                    ) || form.errors.seo_default_title
                                "
                            />

                            <LocalizedFieldTabs
                                v-model="form.setting_translations"
                                field="seo_default_description"
                                :label="
                                    t(
                                        'settings.form.seo_default_description',
                                        'SEO standaardomschrijving',
                                    )
                                "
                                input-id="seo_default_description"
                                type="textarea"
                                :languages="activeLanguages"
                                :default-locale="form.default_locale"
                                :required-default="false"
                                :error="
                                    localizedSettingError(
                                        'seo_default_description',
                                    ) || form.errors.seo_default_description
                                "
                            />

                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="form.global_noindex"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                />
                                {{
                                    t(
                                        'settings.form.global_noindex',
                                        'Globale noindex inschakelen',
                                    )
                                }}
                            </label>

                            <div
                                class="grid gap-4 rounded-md border border-slate-200 bg-slate-50 p-4"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'settings.form.seo_limits_title',
                                                'SEO limieten',
                                            )
                                        }}
                                    </h3>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{
                                            t(
                                                'settings.form.seo_limits_help',
                                                'Deze waarden sturen de formuliercontrole, Health Dashboard waarschuwingen en publish checks.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div
                                        v-for="field in seoNumberFields"
                                        :key="field.key"
                                        class="grid gap-2"
                                    >
                                        <Label :for="field.key">{{
                                            field.label
                                        }}</Label>
                                        <input
                                            :id="field.key"
                                            v-model.number="form[field.key]"
                                            type="number"
                                            :min="field.min"
                                            :max="field.max"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        />
                                        <p
                                            v-if="form.errors[field.key]"
                                            class="text-sm text-red-600"
                                        >
                                            {{ form.errors[field.key] }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="grid gap-3 rounded-md border border-slate-200 bg-white p-4"
                            >
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'settings.form.seo_publish_title',
                                            'Publicatiecontrole',
                                        )
                                    }}
                                </h3>
                                <label
                                    v-for="field in seoBooleanFields"
                                    :key="field.key"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        v-model="form[field.key]"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    {{ field.label }}
                                </label>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'robots'"
                            class="grid gap-5"
                        >
                            <div
                                class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950"
                            >
                                {{
                                    t(
                                        'settings.form.robots_info',
                                        'Deze regels worden toegevoegd aan de automatisch gegenereerde robots.txt. De sitemapregel wordt altijd automatisch toegevoegd. Robots.txt is openbaar en is geen beveiliging voor gevoelige URLs.',
                                    )
                                }}
                            </div>

                            <div
                                v-if="form.global_noindex"
                                class="rounded-md border border-orange-200 bg-orange-50 p-4 text-sm text-orange-950"
                            >
                                {{
                                    t(
                                        'settings.form.robots_noindex_warning',
                                        'Globale noindex staat aan. De robots.txt blokkeert dan de volledige site met :rule en extra regels worden niet toegepast.',
                                        { rule: 'Disallow: /' },
                                    )
                                }}
                            </div>

                            <div class="grid gap-2">
                                <Label for="robots_extra_rules">{{
                                    t(
                                        'settings.form.robots_extra_rules',
                                        'Extra robots.txt regels',
                                    )
                                }}</Label>
                                <RwCodeEditor
                                    id="robots_extra_rules"
                                    v-model="form.robots_extra_rules"
                                    language="robots"
                                    height="260px"
                                    :placeholder="
                                        t(
                                            'settings.form.robots_placeholder',
                                            'Disallow: /tijdelijke-pagina',
                                        )
                                    "
                                    :line-wrapping="true"
                                    theme="graphite"
                                />
                                <p class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'settings.form.robots_help',
                                            'Toegestaan: User-agent, Allow, Disallow, Sitemap, Crawl-delay, Clean-param, Host en commentaarregels met #.',
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="form.errors.robots_extra_rules"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.robots_extra_rules }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-for="snippet in robotsSnippets"
                                    :key="snippet.label"
                                    type="button"
                                    variant="outline"
                                    @click="insertRobotsSnippet(snippet.value)"
                                >
                                    {{ snippet.label }}
                                </Button>
                            </div>

                            <div class="grid gap-2">
                                <Label>{{
                                    t(
                                        'settings.form.robots_preview',
                                        'Preview robots.txt',
                                    )
                                }}</Label>
                                <pre
                                    class="max-h-80 overflow-auto rounded-md bg-slate-950 p-4 text-sm leading-6 text-slate-100"
                                    >{{ robotsPreview }}</pre
                                >
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'branding'"
                            class="grid gap-6"
                        >
                            <div
                                class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950"
                            >
                                {{
                                    t(
                                        'settings.form.branding_info',
                                        'Laad een JPG of PNG op. De favicon wordt vierkant uitgesneden; het logo wordt breed uitgesneden voor de publieke header.',
                                    )
                                }}
                            </div>

                            <div
                                class="grid gap-4 rounded-md border border-slate-200 p-4 md:grid-cols-[180px_minmax(0,1fr)]"
                            >
                                <div class="grid content-start gap-2">
                                    <Label for="company_logo_media_asset_id">
                                        {{
                                            t(
                                                'settings.form.company_logo_media_asset_id',
                                                'Company logo for emails',
                                            )
                                        }}
                                    </Label>
                                    <p class="text-sm text-slate-500">
                                        {{
                                            t(
                                                'settings.form.company_logo_media_asset_id_help',
                                                'Choose the company logo used by the mail template company logo block.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <CmsMediaPicker
                                        id="company_logo_media_asset_id"
                                        v-model="
                                            form.company_logo_media_asset_id
                                        "
                                        v-model:assets="localMediaOptions"
                                        v-model:folders="localMediaFolders"
                                        uploaded-from="cms_settings_company_logo"
                                        upload-context-type="cms_settings"
                                    />
                                    <p
                                        v-if="
                                            form.errors
                                                .company_logo_media_asset_id
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            form.errors
                                                .company_logo_media_asset_id
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="grid gap-4 rounded-md border border-slate-200 p-4 md:grid-cols-[180px_minmax(0,1fr)]"
                            >
                                <div class="grid content-start gap-2">
                                    <Label>{{
                                        t(
                                            'settings.form.current_favicon',
                                            'Huidige favicon',
                                        )
                                    }}</Label>
                                    <div
                                        class="flex h-36 w-36 items-center justify-center rounded-lg border border-slate-200 bg-slate-50"
                                    >
                                        <img
                                            v-if="currentFaviconUrl"
                                            :src="currentFaviconUrl"
                                            :alt="
                                                t(
                                                    'settings.form.current_favicon',
                                                    'Huidige favicon',
                                                )
                                            "
                                            class="h-24 w-24 rounded object-contain"
                                        />
                                        <span
                                            v-else
                                            class="px-4 text-center text-sm text-slate-500"
                                        >
                                            {{
                                                t(
                                                    'settings.form.no_favicon',
                                                    'Nog geen favicon ingesteld.',
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <div class="grid gap-4">
                                    <div class="grid gap-2">
                                        <Label for="favicon_file">{{
                                            t(
                                                'settings.form.new_favicon',
                                                'Nieuwe favicon',
                                            )
                                        }}</Label>
                                        <Input
                                            id="favicon_file"
                                            ref="faviconInput"
                                            type="file"
                                            accept="image/png,image/jpeg"
                                            @change="selectFaviconFile"
                                        />
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'settings.form.favicon_help',
                                                    'Minimaal 64x64 pixels. Maximaal 4MB. SVG is niet toegestaan.',
                                                )
                                            }}
                                        </p>
                                        <p
                                            v-if="form.errors.favicon_file"
                                            class="text-sm text-red-600"
                                        >
                                            {{ form.errors.favicon_file }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="faviconSourceUrl"
                                        class="grid gap-4 rounded-md border border-slate-200 p-4"
                                    >
                                        <div
                                            class="grid gap-4 md:grid-cols-[180px_minmax(0,1fr)]"
                                        >
                                            <div class="grid gap-2">
                                                <Label>{{
                                                    t(
                                                        'settings.form.crop_preview',
                                                        'Voorbeeld uitsnede',
                                                    )
                                                }}</Label>
                                                <canvas
                                                    ref="faviconPreviewCanvas"
                                                    width="192"
                                                    height="192"
                                                    class="h-36 w-36 rounded-lg border border-slate-200 bg-white"
                                                ></canvas>
                                            </div>

                                            <div
                                                class="grid content-start gap-3"
                                            >
                                                <div class="grid gap-2">
                                                    <Label for="favicon_zoom">{{
                                                        t(
                                                            'settings.form.zoom',
                                                            'Zoom',
                                                        )
                                                    }}</Label>
                                                    <input
                                                        id="favicon_zoom"
                                                        v-model.number="
                                                            faviconCrop.zoom
                                                        "
                                                        type="range"
                                                        min="1"
                                                        max="3"
                                                        step="0.05"
                                                        class="w-full"
                                                    />
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label
                                                        for="favicon_offset_x"
                                                        >{{
                                                            t(
                                                                'settings.form.offset_x',
                                                                'Horizontaal verschuiven',
                                                            )
                                                        }}</Label
                                                    >
                                                    <input
                                                        id="favicon_offset_x"
                                                        v-model.number="
                                                            faviconCrop.offsetX
                                                        "
                                                        type="range"
                                                        min="-96"
                                                        max="96"
                                                        step="1"
                                                        class="w-full"
                                                    />
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label
                                                        for="favicon_offset_y"
                                                        >{{
                                                            t(
                                                                'settings.form.offset_y',
                                                                'Verticaal verschuiven',
                                                            )
                                                        }}</Label
                                                    >
                                                    <input
                                                        id="favicon_offset_y"
                                                        v-model.number="
                                                            faviconCrop.offsetY
                                                        "
                                                        type="range"
                                                        min="-96"
                                                        max="96"
                                                        step="1"
                                                        class="w-full"
                                                    />
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    @click="resetFaviconCrop"
                                                >
                                                    {{
                                                        t(
                                                            'settings.form.reset_crop',
                                                            'Uitsnede resetten',
                                                        )
                                                    }}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="grid gap-4 rounded-md border border-slate-200 p-4 md:grid-cols-[180px_minmax(0,1fr)]"
                            >
                                <div class="grid content-start gap-2">
                                    <Label>{{
                                        t(
                                            'settings.form.current_logo',
                                            'Huidig logo',
                                        )
                                    }}</Label>
                                    <div
                                        class="flex h-28 w-44 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-3"
                                    >
                                        <img
                                            v-if="currentLogoUrl"
                                            :src="currentLogoUrl"
                                            :alt="
                                                t(
                                                    'settings.form.current_logo',
                                                    'Huidig logo',
                                                )
                                            "
                                            class="max-h-full max-w-full object-contain"
                                        />
                                        <span
                                            v-else
                                            class="px-4 text-center text-sm text-slate-500"
                                        >
                                            {{
                                                t(
                                                    'settings.form.no_logo',
                                                    'Nog geen logo ingesteld.',
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <div class="grid gap-4">
                                    <div class="grid gap-2">
                                        <Label for="logo_file">{{
                                            t(
                                                'settings.form.new_logo',
                                                'Nieuw logo',
                                            )
                                        }}</Label>
                                        <Input
                                            id="logo_file"
                                            ref="logoInput"
                                            type="file"
                                            accept="image/png,image/jpeg"
                                            @change="selectLogoFile"
                                        />
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'settings.form.logo_help',
                                                    'Minimaal 64x32 pixels. Maximaal 4MB. SVG is niet toegestaan.',
                                                )
                                            }}
                                        </p>
                                        <p
                                            v-if="form.errors.logo_file"
                                            class="text-sm text-red-600"
                                        >
                                            {{ form.errors.logo_file }}
                                        </p>
                                    </div>

                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            v-model="form.logo_show_tagline"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            t(
                                                'settings.form.show_tagline_under_logo',
                                                'Baseline ook onder het logo tonen',
                                            )
                                        }}
                                    </label>

                                    <div
                                        v-if="logoSourceUrl"
                                        class="grid gap-4 rounded-md border border-slate-200 p-4"
                                    >
                                        <div
                                            class="grid gap-4 md:grid-cols-[240px_minmax(0,1fr)]"
                                        >
                                            <div class="grid gap-2">
                                                <Label>{{
                                                    t(
                                                        'settings.form.crop_preview',
                                                        'Voorbeeld uitsnede',
                                                    )
                                                }}</Label>
                                                <canvas
                                                    ref="logoPreviewCanvas"
                                                    width="480"
                                                    height="160"
                                                    class="h-20 w-60 rounded-lg border border-slate-200 bg-white"
                                                ></canvas>
                                            </div>

                                            <div
                                                class="grid content-start gap-3"
                                            >
                                                <div class="grid gap-2">
                                                    <Label for="logo_zoom">{{
                                                        t(
                                                            'settings.form.zoom',
                                                            'Zoom',
                                                        )
                                                    }}</Label>
                                                    <input
                                                        id="logo_zoom"
                                                        v-model.number="
                                                            logoCrop.zoom
                                                        "
                                                        type="range"
                                                        min="0.2"
                                                        max="3"
                                                        step="0.05"
                                                        class="w-full"
                                                    />
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label
                                                        for="logo_offset_x"
                                                        >{{
                                                            t(
                                                                'settings.form.offset_x',
                                                                'Horizontaal verschuiven',
                                                            )
                                                        }}</Label
                                                    >
                                                    <input
                                                        id="logo_offset_x"
                                                        v-model.number="
                                                            logoCrop.offsetX
                                                        "
                                                        type="range"
                                                        min="-240"
                                                        max="240"
                                                        step="1"
                                                        class="w-full"
                                                    />
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label
                                                        for="logo_offset_y"
                                                        >{{
                                                            t(
                                                                'settings.form.offset_y',
                                                                'Verticaal verschuiven',
                                                            )
                                                        }}</Label
                                                    >
                                                    <input
                                                        id="logo_offset_y"
                                                        v-model.number="
                                                            logoCrop.offsetY
                                                        "
                                                        type="range"
                                                        min="-80"
                                                        max="80"
                                                        step="1"
                                                        class="w-full"
                                                    />
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    @click="resetLogoCrop"
                                                >
                                                    {{
                                                        t(
                                                            'settings.form.reset_crop',
                                                            'Uitsnede resetten',
                                                        )
                                                    }}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section v-if="activeTab === 'ai'" class="grid gap-6">
                            <div
                                class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950"
                            >
                                {{
                                    t(
                                        'settings.form.ai_info',
                                        "Deze instellingen worden gebruikt voor AI-vertalingen van CMS-pagina's en de algemene vertaalmodule. API-keys worden encrypted opgeslagen.",
                                    )
                                }}
                            </div>

                            <div
                                v-if="!hasEffectiveAiApiKey"
                                class="rounded-md border border-orange-200 bg-orange-50 p-4 text-sm text-orange-950"
                            >
                                {{
                                    t(
                                        'settings.form.ai_missing_key_warning',
                                        'Er is geen opgeslagen API-key en geen config/.env API-key voor de gekozen provider gevonden. AI-vertalingen zullen pas werken nadat je een API-key bewaart of de serverconfiguratie aanvult.',
                                    )
                                }}
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="translation_ai_provider">{{
                                        t('settings.form.provider', 'Provider')
                                    }}</Label>
                                    <select
                                        id="translation_ai_provider"
                                        v-model="form.translation_ai.provider"
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option
                                            v-for="provider in aiProviders"
                                            :key="provider.value"
                                            :value="provider.value"
                                        >
                                            {{ provider.label }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="
                                            form.errors[
                                                'translation_ai.provider'
                                            ]
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            form.errors[
                                                'translation_ai.provider'
                                            ]
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="translation_ai_model">{{
                                        t('settings.form.model', 'Model')
                                    }}</Label>
                                    <select
                                        v-if="availableAiModels.length > 0"
                                        id="translation_ai_model"
                                        v-model="form.translation_ai.model"
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option
                                            v-for="model in availableAiModels"
                                            :key="model.value"
                                            :value="model.value"
                                        >
                                            {{ model.label }}
                                        </option>
                                    </select>
                                    <Input
                                        v-else
                                        id="translation_ai_model"
                                        v-model="form.translation_ai.model"
                                        :placeholder="
                                            t(
                                                'settings.form.model_placeholder',
                                                'Modelnaam',
                                            )
                                        "
                                    />
                                    <p
                                        v-if="
                                            form.errors['translation_ai.model']
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            form.errors['translation_ai.model']
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="translation_ai_current_key">{{
                                        t(
                                            'settings.form.current_api_key',
                                            'Huidige API-key',
                                        )
                                    }}</Label>
                                    <Input
                                        id="translation_ai_current_key"
                                        :value="
                                            hasSavedAiApiKey ? '********' : ''
                                        "
                                        disabled
                                        readonly
                                    />
                                    <p
                                        class="text-xs"
                                        :class="
                                            hasSavedAiApiKey
                                                ? 'text-green-700'
                                                : 'text-slate-500'
                                        "
                                    >
                                        {{
                                            hasSavedAiApiKey
                                                ? t(
                                                      'settings.form.saved_api_key_yes',
                                                      'Er is momenteel een encrypted API-key opgeslagen.',
                                                  )
                                                : t(
                                                      'settings.form.saved_api_key_no',
                                                      'Er is momenteel geen opgeslagen API-key.',
                                                  )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs"
                                        :class="
                                            hasConfigAiApiKey
                                                ? 'text-green-700'
                                                : 'text-slate-500'
                                        "
                                    >
                                        {{
                                            hasConfigAiApiKey
                                                ? t(
                                                      'settings.form.config_api_key_yes',
                                                      'Voor deze provider is ook een config/.env API-key beschikbaar.',
                                                  )
                                                : t(
                                                      'settings.form.config_api_key_no',
                                                      'Voor deze provider is geen config/.env API-key gevonden.',
                                                  )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="translation_ai_api_key">{{
                                        t(
                                            'settings.form.new_api_key',
                                            'Nieuwe API-key',
                                        )
                                    }}</Label>
                                    <Input
                                        id="translation_ai_api_key"
                                        v-model="form.translation_ai.api_key"
                                        type="password"
                                        autocomplete="off"
                                        :disabled="
                                            form.translation_ai.clear_api_key
                                        "
                                        :placeholder="
                                            t(
                                                'settings.form.api_key_placeholder',
                                                'Leeg laten gebruikt bestaande of .env key',
                                            )
                                        "
                                    />
                                    <label
                                        class="flex items-center gap-2 text-sm text-slate-600"
                                    >
                                        <input
                                            v-model="
                                                form.translation_ai
                                                    .clear_api_key
                                            "
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            t(
                                                'settings.form.clear_api_key',
                                                'Opgeslagen API-key wissen',
                                            )
                                        }}
                                    </label>
                                    <p
                                        v-if="
                                            form.errors[
                                                'translation_ai.api_key'
                                            ]
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            form.errors[
                                                'translation_ai.api_key'
                                            ]
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            form.errors[
                                                'translation_ai.clear_api_key'
                                            ]
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            form.errors[
                                                'translation_ai.clear_api_key'
                                            ]
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label
                                        for="translation_ai_fill_limit_default"
                                        >{{
                                            t(
                                                'settings.form.ai_fill_limit_default',
                                                'Standaard AI batch grootte',
                                            )
                                        }}</Label
                                    >
                                    <Input
                                        id="translation_ai_fill_limit_default"
                                        v-model.number="
                                            form.translation_ai
                                                .fill_limit_default
                                        "
                                        type="number"
                                        min="1"
                                        :max="
                                            form.translation_ai.fill_limit_max
                                        "
                                    />
                                    <p
                                        v-if="
                                            form.errors[
                                                'translation_ai.fill_limit_default'
                                            ]
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            form.errors[
                                                'translation_ai.fill_limit_default'
                                            ]
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        for="translation_ai_fill_limit_max"
                                        >{{
                                            t(
                                                'settings.form.ai_fill_limit_max',
                                                'Maximum AI batch grootte',
                                            )
                                        }}</Label
                                    >
                                    <Input
                                        id="translation_ai_fill_limit_max"
                                        v-model.number="
                                            form.translation_ai.fill_limit_max
                                        "
                                        type="number"
                                        min="1"
                                        max="5000"
                                    />
                                    <p
                                        v-if="
                                            form.errors[
                                                'translation_ai.fill_limit_max'
                                            ]
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            form.errors[
                                                'translation_ai.fill_limit_max'
                                            ]
                                        }}
                                    </p>
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'modules'"
                            class="grid gap-6"
                        >
                            <div
                                class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950"
                            >
                                {{
                                    t(
                                        'settings.form.modules_info',
                                        'Install or synchronize optional CMS modules for this site. Module actions are safe to run again and will restore required pages, blocks and settings.',
                                    )
                                }}
                            </div>

                            <div
                                v-if="moduleInstallDetails"
                                class="grid gap-3 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-950"
                            >
                                <p class="font-semibold">
                                    {{
                                        t(
                                            'settings.form.module_install_result_title',
                                            'Latest module synchronization',
                                        )
                                    }}
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="item in moduleInstallSummary"
                                        :key="item.key"
                                        class="rounded-full border border-green-200 bg-white px-3 py-1 text-xs font-medium text-green-800"
                                    >
                                        {{ item.label }}: {{ item.count }}
                                    </span>
                                </div>
                            </div>

                            <div
                                v-for="cmsModule in cmsModules"
                                :key="cmsModule.key"
                                class="grid gap-4 rounded-md border border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                            >
                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-start gap-3"
                                    >
                                        <div
                                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                                            aria-hidden="true"
                                        >
                                            <span
                                                :class="[
                                                    'mdi text-2xl',
                                                    cmsModule.icon,
                                                ]"
                                            />
                                        </div>
                                        <div class="min-w-0">
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <h3
                                                    class="text-sm font-semibold text-slate-900"
                                                >
                                                    {{
                                                        t(
                                                            cmsModule.name_key,
                                                            cmsModule.name_fallback,
                                                        )
                                                    }}
                                                </h3>
                                                <span
                                                    class="rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                                    :class="
                                                        cmsModule.installed
                                                            ? 'border-green-200 bg-green-50 text-green-700'
                                                            : 'border-slate-200 bg-slate-50 text-slate-600'
                                                    "
                                                >
                                                    {{
                                                        moduleStatusLabel(
                                                            cmsModule,
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    v-if="cmsModule.outdated"
                                                    class="rounded-full border border-orange-200 bg-orange-50 px-2.5 py-0.5 text-xs font-medium text-orange-700"
                                                >
                                                    {{
                                                        t(
                                                            'settings.form.module_status_update_available',
                                                            'Update available',
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                            <p
                                                class="mt-1 text-sm text-slate-500"
                                            >
                                                {{
                                                    t(
                                                        cmsModule.description_key,
                                                        cmsModule.description_fallback,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                v-if="
                                                    cmsModule.installed_at_display
                                                "
                                                class="mt-2 text-xs text-slate-500"
                                            >
                                                {{
                                                    t(
                                                        'settings.form.module_installed_at',
                                                        'Installed at',
                                                    )
                                                }}:
                                                <span
                                                    class="font-medium text-slate-700"
                                                >
                                                    {{
                                                        cmsModule.installed_at_display
                                                    }}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                                        :disabled="
                                            moduleInstallForm.processing ||
                                            moduleDemoDataForm.processing
                                        "
                                        @click="installCmsModule(cmsModule)"
                                    >
                                        <span
                                            v-if="moduleInstallForm.processing"
                                            class="mdi mdi-loading animate-spin text-base"
                                            aria-hidden="true"
                                        />
                                        <span
                                            v-else
                                            class="mdi mdi-puzzle-plus-outline text-base"
                                            aria-hidden="true"
                                        />
                                        {{ moduleActionLabel(cmsModule) }}
                                    </Button>
                                    <Button
                                        v-if="
                                            cmsModule.installed &&
                                            cmsModule.has_demo_data
                                        "
                                        type="button"
                                        variant="outline"
                                        class="gap-2 border-orange-200 text-orange-700 shadow-none hover:bg-orange-50 hover:text-orange-800"
                                        :disabled="
                                            moduleInstallForm.processing ||
                                            moduleDemoDataForm.processing
                                        "
                                        @click="
                                            installCmsModuleDemoData(cmsModule)
                                        "
                                    >
                                        <span
                                            v-if="moduleDemoDataForm.processing"
                                            class="mdi mdi-loading animate-spin text-base"
                                            aria-hidden="true"
                                        />
                                        <span
                                            v-else
                                            class="mdi mdi-database-plus-outline text-base"
                                            aria-hidden="true"
                                        />
                                        {{
                                            t(
                                                cmsModule.demo_label_key,
                                                cmsModule.demo_label_fallback,
                                            )
                                        }}
                                    </Button>
                                    <Button
                                        v-if="
                                            cmsModule.installed &&
                                            cmsModule.manage_url
                                        "
                                        as-child
                                        type="button"
                                        variant="outline"
                                        class="gap-2 border-slate-200 text-slate-700 shadow-none hover:bg-slate-50 hover:text-slate-900"
                                    >
                                        <a :href="cmsModule.manage_url">
                                            <span
                                                class="mdi mdi-open-in-new text-base"
                                                aria-hidden="true"
                                            />
                                            {{
                                                t(
                                                    'settings.form.module_manage_button',
                                                    'Open module',
                                                )
                                            }}
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'starter'"
                            class="grid gap-6"
                        >
                            <div
                                class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950"
                            >
                                {{
                                    t(
                                        'settings.form.starter_import_info',
                                        "Upload een RwSoft CMS starter-site ZIP. Layouts, templates en menu's worden inactief geimporteerd; pagina's worden als concept toegevoegd.",
                                    )
                                }}
                            </div>

                            <div
                                class="grid gap-4 rounded-md border border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'settings.form.starter_example_title',
                                                'Voorbeeld-starter downloaden',
                                            )
                                        }}
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{
                                            t(
                                                'settings.form.starter_example_help',
                                                'Download een minimale geldige starter ZIP als basis voor eigen starter-sites.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    as-child
                                    type="button"
                                    variant="outline"
                                    class="gap-2 border-slate-200 text-slate-700 shadow-none hover:bg-slate-50 hover:text-slate-900"
                                >
                                    <a
                                        :href="
                                            route(
                                                'admin.cms.settings.starter-example',
                                            )
                                        "
                                    >
                                        <span
                                            class="mdi mdi-download text-base"
                                            aria-hidden="true"
                                        />
                                        {{
                                            t(
                                                'settings.form.starter_example_button',
                                                'Download voorbeeld ZIP',
                                            )
                                        }}
                                    </a>
                                </Button>
                            </div>

                            <div
                                class="grid gap-4 rounded-md border border-slate-200 bg-white p-4"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'settings.form.starter_export_title',
                                                'Starter-site exporteren',
                                            )
                                        }}
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{
                                            t(
                                                'settings.form.starter_export_help',
                                                'Maak een starter-ZIP van een bestaande layout, template, pagina en menu. Database-ID’s worden vervangen door import keys.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="starter_export_key">{{
                                            t(
                                                'settings.form.starter_export_key',
                                                'Starter key',
                                            )
                                        }}</Label>
                                        <Input
                                            id="starter_export_key"
                                            v-model="
                                                starterExportForm.starter_key
                                            "
                                            type="text"
                                            placeholder="my-starter"
                                        />
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'settings.form.starter_export_key_help',
                                                    'Optional. Use letters, numbers, dashes or underscores.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="starter_export_name">{{
                                            t(
                                                'settings.form.starter_export_name',
                                                'Starter name',
                                            )
                                        }}</Label>
                                        <Input
                                            id="starter_export_name"
                                            v-model="
                                                starterExportForm.starter_name
                                            "
                                            type="text"
                                            :placeholder="
                                                t(
                                                    'settings.form.starter_export_name_placeholder',
                                                    'My starter package',
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="starter_export_layout_id">{{
                                            t(
                                                'settings.form.starter_export_layout',
                                                'Layout',
                                            )
                                        }}</Label>
                                        <select
                                            id="starter_export_layout_id"
                                            v-model="
                                                starterExportForm.layout_id
                                            "
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option value="">
                                                {{
                                                    t(
                                                        'settings.form.starter_export_select_layout',
                                                        'Select layout',
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="layout in layoutOptions"
                                                :key="layout.id"
                                                :value="layout.id"
                                            >
                                                {{
                                                    optionLabel(layout, 'name')
                                                }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label
                                            for="starter_export_template_id"
                                            >{{
                                                t(
                                                    'settings.form.starter_export_template',
                                                    'Template',
                                                )
                                            }}</Label
                                        >
                                        <select
                                            id="starter_export_template_id"
                                            v-model="
                                                starterExportForm.template_id
                                            "
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option value="">
                                                {{
                                                    t(
                                                        'settings.form.starter_export_select_template',
                                                        'Select template',
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="template in filteredTemplateOptions"
                                                :key="template.id"
                                                :value="template.id"
                                            >
                                                {{
                                                    optionLabel(
                                                        template,
                                                        'name',
                                                    )
                                                }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="starter_export_page_id">{{
                                            t(
                                                'settings.form.starter_export_page',
                                                'Page',
                                            )
                                        }}</Label>
                                        <select
                                            id="starter_export_page_id"
                                            v-model="starterExportForm.page_id"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option value="">
                                                {{
                                                    t(
                                                        'settings.form.starter_export_select_page',
                                                        'Select page',
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="pageOption in filteredStarterPageOptions"
                                                :key="pageOption.id"
                                                :value="pageOption.id"
                                            >
                                                {{
                                                    optionLabel(
                                                        pageOption,
                                                        'title',
                                                    )
                                                }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="starter_export_menu_id">{{
                                            t(
                                                'settings.form.starter_export_menu',
                                                'Menu',
                                            )
                                        }}</Label>
                                        <select
                                            id="starter_export_menu_id"
                                            v-model="starterExportForm.menu_id"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option value="">
                                                {{
                                                    t(
                                                        'settings.form.starter_export_select_menu',
                                                        'Select menu',
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="menu in menuOptions"
                                                :key="menu.id"
                                                :value="menu.id"
                                            >
                                                {{ optionLabel(menu, 'title') }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        as-child
                                        type="button"
                                        variant="outline"
                                        class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                                        :class="{
                                            'pointer-events-none opacity-50':
                                                !starterExportReady,
                                        }"
                                    >
                                        <a
                                            :href="starterExportHref"
                                            :aria-disabled="!starterExportReady"
                                            @click="
                                                preventStarterExportIfIncomplete
                                            "
                                        >
                                            <span
                                                class="mdi mdi-package-variant text-base"
                                                aria-hidden="true"
                                            />
                                            {{
                                                t(
                                                    'settings.form.starter_export_button',
                                                    'Download starter ZIP',
                                                )
                                            }}
                                        </a>
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-if="starterImportDetails"
                                class="grid gap-3 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-950"
                            >
                                <p class="font-semibold">
                                    {{
                                        t(
                                            'settings.form.starter_import_result_title',
                                            'Laatste import',
                                        )
                                    }}
                                </p>
                                <p v-if="starterImportDetails.name">
                                    {{ starterImportDetails.name }}
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="item in starterImportSummary"
                                        :key="item.key"
                                        class="rounded-full border border-green-200 bg-white px-3 py-1 text-xs font-medium text-green-800"
                                    >
                                        {{ item.label }}: {{ item.count }}
                                    </span>
                                </div>
                            </div>

                            <div
                                class="grid gap-4 rounded-md border border-slate-200 bg-white p-4"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'settings.form.starter_import_title',
                                                'Starter-site importeren',
                                            )
                                        }}
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{
                                            t(
                                                'settings.form.starter_import_help',
                                                'De import is veilig: records worden eerst als concept of inactief toegevoegd zodat je ze kan controleren voor publicatie.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-2 md:max-w-xl">
                                    <Label for="starter_zip">{{
                                        t(
                                            'settings.form.starter_zip_file',
                                            'Starter-site ZIP bestand',
                                        )
                                    }}</Label>
                                    <Input
                                        id="starter_zip"
                                        ref="starterInput"
                                        type="file"
                                        accept=".zip,application/zip"
                                        @change="selectStarterFile"
                                    />
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'settings.form.starter_zip_help',
                                                'Maximaal 50MB. Alleen ZIP-bestanden met een geldig starter manifest worden geaccepteerd.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="starterForm.errors.starter_zip"
                                        class="text-sm text-red-600"
                                    >
                                        {{ starterForm.errors.starter_zip }}
                                    </p>
                                </div>

                                <div
                                    v-if="starterForm.progress"
                                    class="grid gap-2 md:max-w-xl"
                                >
                                    <progress
                                        :value="starterForm.progress.percentage"
                                        max="100"
                                        class="h-2 w-full overflow-hidden rounded-full"
                                    >
                                        {{ starterForm.progress.percentage }}%
                                    </progress>
                                    <p class="text-xs text-slate-500">
                                        {{ starterForm.progress.percentage }}%
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                                        :disabled="starterForm.processing"
                                        @click="submitStarterImport"
                                    >
                                        <span
                                            v-if="starterForm.processing"
                                            class="mdi mdi-loading animate-spin text-base"
                                            aria-hidden="true"
                                        />
                                        <span
                                            v-else
                                            class="mdi mdi-package-variant-closed-plus text-base"
                                            aria-hidden="true"
                                        />
                                        {{
                                            t(
                                                'settings.form.starter_import_button',
                                                'Starter importeren',
                                            )
                                        }}
                                    </Button>
                                </div>
                            </div>

                            <div
                                class="grid gap-4 rounded-md border border-indigo-200 bg-indigo-50 p-4"
                            >
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-indigo-950"
                                    >
                                        {{
                                            t(
                                                'settings.form.site_package_title',
                                                'Volledige site package',
                                            )
                                        }}
                                    </h3>
                                    <p class="mt-1 text-sm text-indigo-900">
                                        {{
                                            t(
                                                'settings.form.site_package_help',
                                                'Exporteer of importeer CMS layouts, templates, pagina’s, blogs, menu’s, media, themes, redirects en taxonomies als migratiepakket. Import blijft veilig: layouts, templates, menu’s, themes, redirects en taxonomies worden inactief, pagina’s en blogs blijven concept.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="grid gap-4 rounded-md border border-white/70 bg-white p-4"
                                >
                                    <div>
                                        <h4
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'settings.form.site_package_export_title',
                                                    'Volledige site exporteren',
                                                )
                                            }}
                                        </h4>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{
                                                t(
                                                    'settings.form.site_package_export_help',
                                                    'Maakt een ZIP van de huidige CMS site-structuur. V1.7 bevat site-instellingen, publieke teksten, layouts, templates, pagina’s, blogs, formulieren, menu’s, mediabestanden, themes, redirects en taxonomies.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label for="site_package_key">{{
                                                t(
                                                    'settings.form.site_package_key',
                                                    'Package key',
                                                )
                                            }}</Label>
                                            <Input
                                                id="site_package_key"
                                                v-model="
                                                    sitePackageExportForm.package_key
                                                "
                                                type="text"
                                                placeholder="customer-site"
                                            />
                                            <p class="text-xs text-slate-500">
                                                {{
                                                    t(
                                                        'settings.form.site_package_key_help',
                                                        'Optioneel. Gebruik letters, cijfers, streepjes of underscores.',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div class="grid gap-2">
                                            <Label for="site_package_name">{{
                                                t(
                                                    'settings.form.site_package_name',
                                                    'Package naam',
                                                )
                                            }}</Label>
                                            <Input
                                                id="site_package_name"
                                                v-model="
                                                    sitePackageExportForm.package_name
                                                "
                                                type="text"
                                                :placeholder="
                                                    t(
                                                        'settings.form.site_package_name_placeholder',
                                                        'Klantensite package',
                                                    )
                                                "
                                            />
                                        </div>
                                    </div>

                                    <div class="grid gap-2">
                                        <p
                                            class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                        >
                                            {{
                                                t(
                                                    'settings.form.site_package_modules',
                                                    'Modules',
                                                )
                                            }}
                                        </p>
                                        <div class="flex flex-wrap gap-3">
                                            <label
                                                v-for="module in sitePackageModuleOptions"
                                                :key="module.key"
                                                class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                            >
                                                <input
                                                    v-model="
                                                        sitePackageExportForm.modules
                                                    "
                                                    type="checkbox"
                                                    :value="module.key"
                                                    class="h-4 w-4 rounded border-slate-300"
                                                />
                                                {{ module.label }}
                                            </label>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <Button
                                            as-child
                                            type="button"
                                            variant="outline"
                                            class="gap-2 border-indigo-200 text-indigo-700 shadow-none hover:bg-indigo-50 hover:text-indigo-800"
                                            :class="{
                                                'pointer-events-none opacity-50':
                                                    !sitePackageExportReady,
                                            }"
                                        >
                                            <a
                                                :href="sitePackageExportHref"
                                                :aria-disabled="
                                                    !sitePackageExportReady
                                                "
                                                @click="
                                                    preventSitePackageExportIfIncomplete
                                                "
                                            >
                                                <span
                                                    class="mdi mdi-archive-arrow-down-outline text-base"
                                                    aria-hidden="true"
                                                />
                                                {{
                                                    t(
                                                        'settings.form.site_package_export_button',
                                                        'Download site package ZIP',
                                                    )
                                                }}
                                            </a>
                                        </Button>
                                    </div>
                                </div>

                                <div
                                    v-if="sitePackageImportDetails"
                                    class="grid gap-3 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-950"
                                >
                                    <p class="font-semibold">
                                        {{
                                            t(
                                                'settings.form.site_package_import_result_title',
                                                'Laatste site package import',
                                            )
                                        }}
                                    </p>
                                    <p v-if="sitePackageImportDetails.name">
                                        {{ sitePackageImportDetails.name }}
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="item in sitePackageImportSummary"
                                            :key="item.key"
                                            class="rounded-full border border-green-200 bg-white px-3 py-1 text-xs font-medium text-green-800"
                                        >
                                            {{ item.label }}: {{ item.count }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    v-if="sitePackagePreviewDetails"
                                    class="grid gap-3 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950"
                                >
                                    <p class="font-semibold">
                                        {{
                                            t(
                                                'settings.form.site_package_preview_result_title',
                                                'Site package preview',
                                            )
                                        }}
                                    </p>
                                    <p v-if="sitePackagePreviewDetails.name">
                                        {{ sitePackagePreviewDetails.name }}
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="item in sitePackagePreviewSummary"
                                            :key="item.key"
                                            class="rounded-full border border-blue-200 bg-white px-3 py-1 text-xs font-medium text-blue-800"
                                        >
                                            {{ item.label }}: {{ item.count }}
                                        </span>
                                    </div>
                                    <div
                                        v-if="sitePackagePreviewWarnings.length"
                                        class="grid gap-1 text-xs text-orange-700"
                                    >
                                        <p
                                            v-for="warning in sitePackagePreviewWarnings"
                                            :key="warning"
                                        >
                                            {{ warning }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="sitePackageActivationDetails"
                                    class="grid gap-3 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-950"
                                >
                                    <p class="font-semibold">
                                        {{
                                            t(
                                                'settings.form.site_package_activation_result_title',
                                                'Laatste activatie',
                                            )
                                        }}
                                    </p>
                                    <p v-if="sitePackageActivationDetails.key">
                                        {{ sitePackageActivationDetails.key }}
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="item in sitePackageActivationSummary"
                                            :key="item.key"
                                            class="rounded-full border border-green-200 bg-white px-3 py-1 text-xs font-medium text-green-800"
                                        >
                                            {{ item.label }}: {{ item.count }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="grid gap-4 rounded-md border border-white/70 bg-white p-4"
                                >
                                    <div>
                                        <h4
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'settings.form.site_package_import_title',
                                                    'Volledige site importeren',
                                                )
                                            }}
                                        </h4>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{
                                                t(
                                                    'settings.form.site_package_import_help',
                                                    'Upload een site package ZIP. Dit kan alleen in een lege CMS-site. Records worden niet automatisch geactiveerd of gepubliceerd.',
                                                )
                                            }}
                                            {{
                                                t(
                                                    'settings.form.site_package_preview_help',
                                                    'Controleert het ZIP bestand en toont de inhoud zonder iets te importeren.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2 md:max-w-xl">
                                        <Label for="site_package_zip">{{
                                            t(
                                                'settings.form.site_package_zip_file',
                                                'Site package ZIP bestand',
                                            )
                                        }}</Label>
                                        <Input
                                            id="site_package_zip"
                                            ref="sitePackageInput"
                                            type="file"
                                            accept=".zip,application/zip"
                                            @change="selectSitePackageFile"
                                        />
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'settings.form.site_package_zip_help',
                                                    'Maximaal 50MB. Alleen ZIP-bestanden met een geldig site package manifest worden geaccepteerd.',
                                                )
                                            }}
                                        </p>
                                        <p
                                            v-if="
                                                sitePackageForm.errors
                                                    .site_package_zip
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                sitePackageForm.errors
                                                    .site_package_zip
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="sitePackageForm.progress"
                                        class="grid gap-2 md:max-w-xl"
                                    >
                                        <progress
                                            :value="
                                                sitePackageForm.progress
                                                    .percentage
                                            "
                                            max="100"
                                            class="h-2 w-full overflow-hidden rounded-full"
                                        >
                                            {{
                                                sitePackageForm.progress
                                                    .percentage
                                            }}%
                                        </progress>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                sitePackageForm.progress
                                                    .percentage
                                            }}%
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                                            :disabled="
                                                sitePackageForm.processing
                                            "
                                            @click="submitSitePackagePreview"
                                        >
                                            <span
                                                v-if="
                                                    sitePackageForm.processing
                                                "
                                                class="mdi mdi-loading animate-spin text-base"
                                                aria-hidden="true"
                                            />
                                            <span
                                                v-else
                                                class="mdi mdi-eye-outline text-base"
                                                aria-hidden="true"
                                            />
                                            {{
                                                t(
                                                    'settings.form.site_package_preview_button',
                                                    'Preview site package',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="gap-2 border-indigo-200 text-indigo-700 shadow-none hover:bg-indigo-50 hover:text-indigo-800"
                                            :disabled="
                                                sitePackageForm.processing
                                            "
                                            @click="submitSitePackageImport"
                                        >
                                            <span
                                                v-if="
                                                    sitePackageForm.processing
                                                "
                                                class="mdi mdi-loading animate-spin text-base"
                                                aria-hidden="true"
                                            />
                                            <span
                                                v-else
                                                class="mdi mdi-archive-arrow-up-outline text-base"
                                                aria-hidden="true"
                                            />
                                            {{
                                                t(
                                                    'settings.form.site_package_import_button',
                                                    'Site package importeren',
                                                )
                                            }}
                                        </Button>
                                    </div>
                                </div>

                                <div
                                    class="grid gap-4 rounded-md border border-white/70 bg-white p-4"
                                >
                                    <div>
                                        <h4
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'settings.form.site_package_activation_title',
                                                    'Site package activeren',
                                                )
                                            }}
                                        </h4>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{
                                                t(
                                                    'settings.form.site_package_activation_help',
                                                    'Activeer alleen de onderdelen die je expliciet selecteert. Homepage, defaults en theme-switch wijzigen alleen via de opties hieronder.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2 md:max-w-xl">
                                        <Label
                                            for="site_package_activation_key"
                                        >
                                            {{
                                                t(
                                                    'settings.form.site_package_activation_key',
                                                    'Package key voor activatie',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            id="site_package_activation_key"
                                            v-model="activationForm.package_key"
                                            type="text"
                                            placeholder="customer-site"
                                        />
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'settings.form.site_package_activation_key_help',
                                                    'Gebruik dezelfde key als in de import, bijvoorbeeld customer-site.',
                                                )
                                            }}
                                        </p>
                                        <p
                                            v-if="
                                                activationForm.errors
                                                    .package_key
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                activationForm.errors
                                                    .package_key
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <p
                                            class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                        >
                                            {{
                                                t(
                                                    'settings.form.site_package_activation_modules',
                                                    'Onderdelen activeren',
                                                )
                                            }}
                                        </p>
                                        <div class="flex flex-wrap gap-3">
                                            <label
                                                v-for="module in activationModuleOptions"
                                                :key="module.key"
                                                class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                            >
                                                <input
                                                    v-model="
                                                        activationForm.modules
                                                    "
                                                    type="checkbox"
                                                    :value="module.key"
                                                    class="h-4 w-4 rounded border-slate-300"
                                                />
                                                {{ module.label }}
                                            </label>
                                        </div>
                                        <p
                                            v-if="activationForm.errors.modules"
                                            class="text-sm text-red-600"
                                        >
                                            {{ activationForm.errors.modules }}
                                        </p>
                                    </div>

                                    <div class="grid gap-3">
                                        <p
                                            class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                        >
                                            {{
                                                t(
                                                    'settings.form.site_package_activation_publish_options',
                                                    'Publicatie en defaults',
                                                )
                                            }}
                                        </p>
                                        <div class="flex flex-wrap gap-3">
                                            <label
                                                class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                            >
                                                <input
                                                    v-model="
                                                        activationForm.set_homepage
                                                    "
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300"
                                                />
                                                {{
                                                    t(
                                                        'settings.form.site_package_set_homepage',
                                                        'Homepage instellen uit package',
                                                    )
                                                }}
                                            </label>
                                            <label
                                                class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                            >
                                                <input
                                                    v-model="
                                                        activationForm.set_default_layouts
                                                    "
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300"
                                                />
                                                {{
                                                    t(
                                                        'settings.form.site_package_set_default_layouts',
                                                        'Default layouts instellen',
                                                    )
                                                }}
                                            </label>
                                            <label
                                                class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                            >
                                                <input
                                                    v-model="
                                                        activationForm.set_default_templates
                                                    "
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300"
                                                />
                                                {{
                                                    t(
                                                        'settings.form.site_package_set_default_templates',
                                                        'Default templates instellen',
                                                    )
                                                }}
                                            </label>
                                            <label
                                                class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                            >
                                                <input
                                                    v-model="
                                                        activationForm.publish_pages
                                                    "
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300"
                                                />
                                                {{
                                                    t(
                                                        'settings.form.site_package_publish_pages',
                                                        "Pagina's publiceren",
                                                    )
                                                }}
                                            </label>
                                            <label
                                                class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                                            >
                                                <input
                                                    v-model="
                                                        activationForm.publish_blogs
                                                    "
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300"
                                                />
                                                {{
                                                    t(
                                                        'settings.form.site_package_publish_blogs',
                                                        'Blogs publiceren',
                                                    )
                                                }}
                                            </label>
                                        </div>
                                    </div>

                                    <div class="grid gap-2 md:max-w-xl">
                                        <Label for="activate_theme_import_key">
                                            {{
                                                t(
                                                    'settings.form.site_package_activate_theme_import_key',
                                                    'Theme import key live activeren',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            id="activate_theme_import_key"
                                            v-model="
                                                activationForm.activate_theme_import_key
                                            "
                                            type="text"
                                            placeholder="theme.customer"
                                        />
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'settings.form.site_package_activate_theme_import_key_help',
                                                    'Optioneel. Vul een theme import key uit hetzelfde package in om dat theme als live theme te publiceren.',
                                                )
                                            }}
                                        </p>
                                        <p
                                            v-if="
                                                activationForm.errors
                                                    .activate_theme_import_key
                                            "
                                            class="text-sm text-red-600"
                                        >
                                            {{
                                                activationForm.errors
                                                    .activate_theme_import_key
                                            }}
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                                            :disabled="
                                                activationForm.processing
                                            "
                                            @click="submitSitePackageActivation"
                                        >
                                            <span
                                                v-if="activationForm.processing"
                                                class="mdi mdi-loading animate-spin text-base"
                                                aria-hidden="true"
                                            />
                                            <span
                                                v-else
                                                class="mdi mdi-check-circle-outline text-base"
                                                aria-hidden="true"
                                            />
                                            {{
                                                t(
                                                    'settings.form.site_package_activation_button',
                                                    'Geselecteerde onderdelen activeren',
                                                )
                                            }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </form>
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import RwCodeEditor from '@/Components/RwCodeEditor.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CmsMediaPicker from '@/Pages/Admin/Cms/Components/CmsMediaPicker.vue';
import LocalizedFieldTabs from '@/Pages/Admin/Cms/Components/LocalizedFieldTabs.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const page = usePage();

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
    settingMeta: {
        type: Object,
        required: true,
    },
    adminSettings: {
        type: Object,
        required: true,
    },
    pageOptions: {
        type: Array,
        required: true,
    },
    activeLanguages: {
        type: Array,
        required: true,
    },
    translationAi: {
        type: Object,
        required: true,
    },
    visitorTracking: {
        type: Object,
        required: true,
    },
    searchConsole: {
        type: Object,
        required: true,
    },
    robotsDefaultDisallowPaths: {
        type: Array,
        required: true,
    },
    robotsSitemapUrl: {
        type: String,
        required: true,
    },
    layoutOptions: {
        type: Array,
        required: true,
    },
    templateOptions: {
        type: Array,
        required: true,
    },
    seoSettings: {
        type: Object,
        required: true,
    },
    menuOptions: {
        type: Array,
        required: true,
    },
    mediaOptions: {
        type: Array,
        required: true,
    },
    mediaFolders: {
        type: Array,
        required: true,
    },
    modules: {
        type: Object,
        required: true,
    },
});

const tabs = computed(() => [
    { key: 'admin', label: t('settings.tabs.admin', 'Admin') },
    { key: 'general', label: t('settings.tabs.general', 'Algemeen') },
    {
        key: 'visitor_tracking',
        label: t('settings.tabs.visitor_tracking', 'Visitor tracking'),
    },
    {
        key: 'search_console',
        label: t('settings.tabs.search_console', 'Search Console'),
    },
    { key: 'media', label: t('settings.tabs.media', 'Media') },
    { key: 'seo', label: t('settings.tabs.seo', 'SEO') },
    { key: 'robots', label: t('settings.tabs.robots', 'Robots.txt') },
    { key: 'branding', label: t('settings.tabs.branding', 'Branding') },
    { key: 'ai', label: t('settings.tabs.ai', 'AI vertaling') },
    { key: 'modules', label: t('settings.tabs.modules', 'Modules') },
    { key: 'starter', label: t('settings.tabs.starter', 'Starter import') },
]);

const requestedTab =
    typeof window !== 'undefined'
        ? new URLSearchParams(window.location.search).get('tab')
        : null;
const activeTab = ref(
    tabs.value.some((tab) => tab.key === requestedTab)
        ? requestedTab
        : 'general',
);

const form = useForm({
    site_name: props.settings.site_name ?? '',
    site_tagline: props.settings.site_tagline ?? '',
    default_locale: props.settings.default_locale ?? 'nl',
    multilingual_enabled: Boolean(props.settings.multilingual_enabled ?? true),
    auto_locale_detection_enabled: Boolean(
        props.settings.auto_locale_detection_enabled ?? false,
    ),
    auto_locale_detection_strategy:
        props.settings.auto_locale_detection_strategy ?? 'browser_then_ip',
    auto_locale_redirect_enabled: Boolean(
        props.settings.auto_locale_redirect_enabled ?? true,
    ),
    auto_locale_remember_choice: Boolean(
        props.settings.auto_locale_remember_choice ?? true,
    ),
    auto_locale_cookie_days: Number(
        props.settings.auto_locale_cookie_days ?? 180,
    ),
    auto_locale_country_map: props.settings.auto_locale_country_map ?? '',
    visitor_tracking_enabled: Boolean(
        props.settings.visitor_tracking_enabled ?? false,
    ),
    visitor_tracking_retention_mode:
        props.settings.visitor_tracking_retention_mode ?? 'days',
    visitor_tracking_retention_days: Number(
        props.settings.visitor_tracking_retention_days ?? 90,
    ),
    visitor_tracking_cookie_days: Number(
        props.settings.visitor_tracking_cookie_days ?? 90,
    ),
    visitor_tracking_store_ip: Boolean(
        props.settings.visitor_tracking_store_ip ?? true,
    ),
    visitor_tracking_store_ip_hash: Boolean(
        props.settings.visitor_tracking_store_ip_hash ?? true,
    ),
    visitor_tracking_ignore_bots: Boolean(
        props.settings.visitor_tracking_ignore_bots ?? true,
    ),
    visitor_tracking_excluded_paths:
        props.settings.visitor_tracking_excluded_paths ?? '',
    visitor_tracking_geo_enabled: Boolean(
        props.settings.visitor_tracking_geo_enabled ?? false,
    ),
    visitor_tracking_geo_provider:
        props.settings.visitor_tracking_geo_provider ?? 'ip_api',
    visitor_tracking_geo_allowed_countries:
        props.settings.visitor_tracking_geo_allowed_countries ?? '',
    visitor_tracking_geo_delete_disallowed_countries: Boolean(
        props.settings.visitor_tracking_geo_delete_disallowed_countries ??
        false,
    ),
    search_console_enabled: Boolean(
        props.settings.search_console_enabled ?? false,
    ),
    search_console_property_type:
        props.settings.search_console_property_type ?? 'url_prefix',
    search_console_site_url: props.settings.search_console_site_url ?? '',
    search_console_analytics_cache_seconds: Number(
        props.settings.search_console_analytics_cache_seconds ?? 43200,
    ),
    search_console_inspection_cache_seconds: Number(
        props.settings.search_console_inspection_cache_seconds ?? 86400,
    ),
    search_console_query_limit: Number(
        props.settings.search_console_query_limit ?? 10,
    ),
    homepage_id: props.settings.homepage_id ?? '',
    public_text_cache_enabled: Boolean(
        props.settings.public_text_cache_enabled ?? true,
    ),
    public_text_cache_ttl: Number(props.settings.public_text_cache_ttl ?? 3600),
    contact_company_name: props.settings.contact_company_name ?? '',
    contact_street: props.settings.contact_street ?? '',
    contact_postal_code: props.settings.contact_postal_code ?? '',
    contact_city: props.settings.contact_city ?? '',
    contact_country: props.settings.contact_country ?? '',
    contact_country_code: props.settings.contact_country_code ?? '',
    contact_phone_1_label: props.settings.contact_phone_1_label ?? '',
    contact_phone_1: props.settings.contact_phone_1 ?? '',
    contact_phone_2_label: props.settings.contact_phone_2_label ?? '',
    contact_phone_2: props.settings.contact_phone_2 ?? '',
    contact_phone_3_label: props.settings.contact_phone_3_label ?? '',
    contact_phone_3: props.settings.contact_phone_3 ?? '',
    contact_email_1_label: props.settings.contact_email_1_label ?? '',
    contact_email_1: props.settings.contact_email_1 ?? '',
    contact_email_2_label: props.settings.contact_email_2_label ?? '',
    contact_email_2: props.settings.contact_email_2 ?? '',
    contact_vat_number: props.settings.contact_vat_number ?? '',
    contact_image_media_asset_id:
        props.settings.contact_image_media_asset_id ?? '',
    company_logo_media_asset_id:
        props.settings.company_logo_media_asset_id ?? '',
    media_max_image_upload_mb: Number(
        props.settings.media_max_image_upload_mb ?? 20,
    ),
    seo_default_title: props.settings.seo_default_title ?? '',
    seo_default_description: props.settings.seo_default_description ?? '',
    seo_h1_min_length: Number(
        props.settings.seo_h1_min_length ??
            props.seoSettings.seo_h1_min_length ??
            20,
    ),
    seo_h1_max_length: Number(
        props.settings.seo_h1_max_length ??
            props.seoSettings.seo_h1_max_length ??
            70,
    ),
    seo_h2_max_length: Number(
        props.settings.seo_h2_max_length ??
            props.seoSettings.seo_h2_max_length ??
            90,
    ),
    seo_h3_max_length: Number(
        props.settings.seo_h3_max_length ??
            props.seoSettings.seo_h3_max_length ??
            100,
    ),
    seo_meta_title_min_length: Number(
        props.settings.seo_meta_title_min_length ??
            props.seoSettings.seo_meta_title_min_length ??
            30,
    ),
    seo_meta_title_max_length: Number(
        props.settings.seo_meta_title_max_length ??
            props.seoSettings.seo_meta_title_max_length ??
            60,
    ),
    seo_meta_description_min_length: Number(
        props.settings.seo_meta_description_min_length ??
            props.seoSettings.seo_meta_description_min_length ??
            120,
    ),
    seo_meta_description_max_length: Number(
        props.settings.seo_meta_description_max_length ??
            props.seoSettings.seo_meta_description_max_length ??
            160,
    ),
    seo_slug_min_length: Number(
        props.settings.seo_slug_min_length ??
            props.seoSettings.seo_slug_min_length ??
            3,
    ),
    seo_slug_max_length: Number(
        props.settings.seo_slug_max_length ??
            props.seoSettings.seo_slug_max_length ??
            80,
    ),
    seo_url_max_length: Number(
        props.settings.seo_url_max_length ??
            props.seoSettings.seo_url_max_length ??
            2000,
    ),
    seo_content_min_words: Number(
        props.settings.seo_content_min_words ??
            props.seoSettings.seo_content_min_words ??
            80,
    ),
    seo_require_meta_title_on_publish: Boolean(
        props.settings.seo_require_meta_title_on_publish ??
        props.seoSettings.seo_require_meta_title_on_publish ??
        true,
    ),
    seo_require_meta_description_on_publish: Boolean(
        props.settings.seo_require_meta_description_on_publish ??
        props.seoSettings.seo_require_meta_description_on_publish ??
        true,
    ),
    seo_require_single_h1: Boolean(
        props.settings.seo_require_single_h1 ??
        props.seoSettings.seo_require_single_h1 ??
        true,
    ),
    seo_require_valid_heading_hierarchy: Boolean(
        props.settings.seo_require_valid_heading_hierarchy ??
        props.seoSettings.seo_require_valid_heading_hierarchy ??
        true,
    ),
    seo_require_json_ld: Boolean(
        props.settings.seo_require_json_ld ??
        props.seoSettings.seo_require_json_ld ??
        false,
    ),
    seo_require_og_image_for_posts: Boolean(
        props.settings.seo_require_og_image_for_posts ??
        props.seoSettings.seo_require_og_image_for_posts ??
        false,
    ),
    setting_translations: props.settings.setting_translations ?? {},
    admin_settings: {
        admin_default_locale: props.adminSettings?.admin_default_locale ?? 'nl',
    },
    global_noindex: Boolean(props.settings.global_noindex ?? false),
    robots_extra_rules: props.settings.robots_extra_rules ?? '',
    logo_show_tagline: Boolean(props.settings.logo_show_tagline ?? false),
    favicon_file: null,
    logo_file: null,
    translation_ai: {
        provider: String(props.translationAi?.provider || 'gemini'),
        model: String(props.translationAi?.model || ''),
        api_key: '',
        clear_api_key: Boolean(props.translationAi?.clear_api_key ?? false),
        fill_limit_default: Number(
            props.translationAi?.fill_limit_default ?? 100,
        ),
        fill_limit_max: Number(props.translationAi?.fill_limit_max ?? 500),
    },
    visitor_tracking: {
        geo_api_key: '',
        clear_geo_api_key: Boolean(
            props.visitorTracking?.clear_geo_api_key ?? false,
        ),
    },
    search_console: {
        oauth_client_id: props.searchConsole?.oauth_client_id ?? '',
        oauth_client_secret: '',
        clear_oauth_client_secret: Boolean(
            props.searchConsole?.clear_oauth_client_secret ?? false,
        ),
    },
});

const starterForm = useForm({
    starter_zip: null,
});
const moduleInstallForm = useForm({});
const moduleDemoDataForm = useForm({});
const starterExportForm = useForm({
    starter_key: '',
    starter_name: '',
    layout_id: '',
    template_id: '',
    page_id: '',
    menu_id: '',
});
const sitePackageForm = useForm({
    site_package_zip: null,
});
const activationForm = useForm({
    package_key: '',
    modules: [
        'layouts',
        'templates',
        'pages',
        'menus',
        'redirects',
        'taxonomies',
        'blogs',
        'forms',
        'docs',
        'downloads',
        'themes',
    ],
    publish_pages: false,
    publish_blogs: false,
    set_homepage: false,
    set_default_layouts: false,
    set_default_templates: false,
    activate_theme_import_key: '',
});
const sitePackageExportForm = useForm({
    package_key: '',
    package_name: '',
    modules: [
        'site',
        'public_texts',
        'layouts',
        'templates',
        'pages',
        'menus',
        'media',
        'downloads',
        'themes',
        'redirects',
        'taxonomies',
        'blogs',
        'forms',
        'docs',
    ],
});

const hasSavedAiApiKey = ref(
    Boolean(props.translationAi?.has_api_key ?? false),
);
const hasSavedGeoApiKey = ref(
    Boolean(props.visitorTracking?.has_geo_api_key ?? false),
);
const hasSavedSearchConsoleClientSecret = ref(
    Boolean(props.searchConsole?.has_oauth_client_secret ?? false),
);
const searchConsolePropertyTypeOptions = computed(() =>
    Array.isArray(props.searchConsole?.property_type_options)
        ? props.searchConsole.property_type_options
        : [],
);
const searchConsolePropertyPlaceholder = computed(() =>
    form.search_console_property_type === 'domain'
        ? 'sc-domain:example.com'
        : 'https://example.com/',
);
const searchConsoleNumberFields = computed(() => [
    {
        key: 'search_console_analytics_cache_seconds',
        label: t(
            'settings.form.search_console_analytics_cache_seconds',
            'Analytics cache seconds',
        ),
        min: 60,
        max: 604800,
    },
    {
        key: 'search_console_inspection_cache_seconds',
        label: t(
            'settings.form.search_console_inspection_cache_seconds',
            'Inspection cache seconds',
        ),
        min: 60,
        max: 604800,
    },
    {
        key: 'search_console_query_limit',
        label: t('settings.form.search_console_query_limit', 'Query limit'),
        min: 1,
        max: 25,
    },
]);
const searchConsoleCanConnect = computed(
    () =>
        form.search_console_enabled &&
        String(form.search_console_site_url || '').trim() !== '' &&
        String(form.search_console.oauth_client_id || '').trim() !== '' &&
        (hasSavedSearchConsoleClientSecret.value ||
            String(form.search_console.oauth_client_secret || '').trim() !==
                ''),
);
const searchConsoleConnectionStatus = computed(() => {
    if (!form.search_console_enabled) {
        return t('settings.form.search_console_status_disabled', 'Disabled');
    }

    if (!props.searchConsole?.has_oauth_token) {
        return t(
            'settings.form.search_console_status_not_connected',
            'Not connected',
        );
    }

    return t('settings.form.search_console_status_connected', 'Connected');
});
const visitorTrackingRetentionOptions = computed(() => [
    {
        value: 'days',
        label: t('settings.form.visitor_tracking_retention_days_mode', 'Days'),
    },
    {
        value: 'always',
        label: t(
            'settings.form.visitor_tracking_retention_always_mode',
            'Always',
        ),
    },
]);
const visitorTrackingGeoProviderOptions = computed(() =>
    Array.isArray(props.visitorTracking?.geo_provider_options)
        ? props.visitorTracking.geo_provider_options
        : [],
);
const visitorTrackingBooleanFields = computed(() => [
    {
        key: 'visitor_tracking_enabled',
        label: t(
            'settings.form.visitor_tracking_enabled',
            'Enable visitor tracking',
        ),
    },
    {
        key: 'visitor_tracking_store_ip',
        label: t(
            'settings.form.visitor_tracking_store_ip',
            'Store full IP address',
        ),
    },
    {
        key: 'visitor_tracking_store_ip_hash',
        label: t(
            'settings.form.visitor_tracking_store_ip_hash',
            'Store IP hash',
        ),
    },
    {
        key: 'visitor_tracking_ignore_bots',
        label: t(
            'settings.form.visitor_tracking_ignore_bots',
            'Ignore crawlers and bots',
        ),
    },
]);
const autoLocaleDetectionStrategyOptions = computed(() => [
    {
        value: 'browser',
        label: t(
            'settings.form.auto_locale_strategy_browser',
            'Browser language',
        ),
    },
    {
        value: 'ip',
        label: t('settings.form.auto_locale_strategy_ip', 'Country header'),
    },
    {
        value: 'browser_then_ip',
        label: t(
            'settings.form.auto_locale_strategy_browser_then_ip',
            'Browser language, then country header',
        ),
    },
]);
const faviconInput = ref(null);
const faviconPreviewCanvas = ref(null);
const faviconSourceUrl = ref('');
const faviconSourceImage = ref(null);
const faviconCrop = ref({
    zoom: 1,
    offsetX: 0,
    offsetY: 0,
});
const logoInput = ref(null);
const starterInput = ref(null);
const sitePackageInput = ref(null);
const logoPreviewCanvas = ref(null);
const logoSourceUrl = ref('');
const logoSourceImage = ref(null);
const logoCrop = ref({
    zoom: 1,
    offsetX: 0,
    offsetY: 0,
});
const localMediaOptions = ref([...props.mediaOptions]);
const localMediaFolders = ref([...props.mediaFolders]);

const robotsSnippets = [
    {
        label: t('settings.form.robots_snippets.disallow', '+ Disallow'),
        value: 'Disallow: /pad',
    },
    {
        label: t('settings.form.robots_snippets.allow', '+ Allow'),
        value: 'Allow: /pad',
    },
    {
        label: t('settings.form.robots_snippets.crawl_delay', '+ Crawl-delay'),
        value: 'Crawl-delay: 10',
    },
];

const contactTextFields = computed(() => [
    {
        key: 'contact_company_name',
        label: t('settings.form.contact_company_name', 'Company name'),
        placeholder: t(
            'settings.form.contact_company_name_placeholder',
            'Example Ltd.',
        ),
    },
    {
        key: 'contact_street',
        label: t('settings.form.contact_street', 'Street and number'),
        placeholder: t(
            'settings.form.contact_street_placeholder',
            'Main street 1',
        ),
    },
    {
        key: 'contact_postal_code',
        label: t('settings.form.contact_postal_code', 'Postal code'),
        placeholder: t('settings.form.contact_postal_code_placeholder', '1000'),
    },
    {
        key: 'contact_city',
        label: t('settings.form.contact_city', 'City'),
        placeholder: t('settings.form.contact_city_placeholder', 'Brussels'),
    },
    {
        key: 'contact_country',
        label: t('settings.form.contact_country', 'Country'),
        placeholder: t('settings.form.contact_country_placeholder', 'Belgium'),
    },
    {
        key: 'contact_country_code',
        label: t('settings.form.contact_country_code', 'Country code'),
        placeholder: t('settings.form.contact_country_code_placeholder', 'BE'),
    },
    {
        key: 'contact_phone_1_label',
        label: t('settings.form.contact_phone_1_label', 'Phone 1 label'),
        placeholder: t(
            'settings.form.contact_phone_label_placeholder',
            'Office',
        ),
    },
    {
        key: 'contact_phone_1',
        label: t('settings.form.contact_phone_1', 'Phone 1'),
        placeholder: t('settings.form.contact_phone_placeholder', '+32 ...'),
        type: 'tel',
    },
    {
        key: 'contact_phone_2_label',
        label: t('settings.form.contact_phone_2_label', 'Phone 2 label'),
        placeholder: t(
            'settings.form.contact_phone_label_placeholder',
            'Support',
        ),
    },
    {
        key: 'contact_phone_2',
        label: t('settings.form.contact_phone_2', 'Phone 2'),
        placeholder: t('settings.form.contact_phone_placeholder', '+32 ...'),
        type: 'tel',
    },
    {
        key: 'contact_phone_3_label',
        label: t('settings.form.contact_phone_3_label', 'Phone 3 label'),
        placeholder: t(
            'settings.form.contact_phone_label_placeholder',
            'Mobile',
        ),
    },
    {
        key: 'contact_phone_3',
        label: t('settings.form.contact_phone_3', 'Phone 3'),
        placeholder: t('settings.form.contact_phone_placeholder', '+32 ...'),
        type: 'tel',
    },
    {
        key: 'contact_email_1_label',
        label: t('settings.form.contact_email_1_label', 'Email 1 label'),
        placeholder: t('settings.form.contact_email_label_placeholder', 'Info'),
    },
    {
        key: 'contact_email_1',
        label: t('settings.form.contact_email_1', 'Email 1'),
        placeholder: t(
            'settings.form.contact_email_placeholder',
            'info@example.com',
        ),
        type: 'email',
    },
    {
        key: 'contact_email_2_label',
        label: t('settings.form.contact_email_2_label', 'Email 2 label'),
        placeholder: t(
            'settings.form.contact_email_label_placeholder',
            'Sales',
        ),
    },
    {
        key: 'contact_email_2',
        label: t('settings.form.contact_email_2', 'Email 2'),
        placeholder: t(
            'settings.form.contact_email_placeholder',
            'sales@example.com',
        ),
        type: 'email',
    },
    {
        key: 'contact_vat_number',
        label: t('settings.form.contact_vat_number', 'VAT number'),
        placeholder: t(
            'settings.form.contact_vat_number_placeholder',
            'BE ...',
        ),
    },
]);

const filteredPageOptions = computed(() =>
    props.pageOptions.filter((page) => page.locale === form.default_locale),
);
const layoutOptions = computed(() => props.layoutOptions || []);
const menuOptions = computed(() => props.menuOptions || []);
const availableMediaOptions = computed(() => localMediaOptions.value || []);
const filteredTemplateOptions = computed(() => {
    const layoutId = Number(starterExportForm.layout_id || 0);

    return (props.templateOptions || []).filter(
        (template) => !layoutId || Number(template.layout_id || 0) === layoutId,
    );
});
const filteredStarterPageOptions = computed(() => {
    const templateId = Number(starterExportForm.template_id || 0);

    return (props.pageOptions || []).filter((pageOption) => {
        if (
            templateId &&
            Number(pageOption.detail_template_id || 0) !== templateId
        ) {
            return false;
        }

        return true;
    });
});
const starterExportReady = computed(
    () =>
        Number(starterExportForm.layout_id || 0) > 0 &&
        Number(starterExportForm.template_id || 0) > 0 &&
        Number(starterExportForm.page_id || 0) > 0 &&
        Number(starterExportForm.menu_id || 0) > 0,
);
const starterExportHref = computed(() => {
    if (!starterExportReady.value) {
        return '#';
    }

    const parameters = new URLSearchParams();

    [
        'starter_key',
        'starter_name',
        'layout_id',
        'template_id',
        'page_id',
        'menu_id',
    ].forEach((field) => {
        const value = starterExportForm[field];

        if (value !== null && value !== undefined && String(value) !== '') {
            parameters.set(field, String(value));
        }
    });

    return `${route('admin.cms.settings.starter-export')}?${parameters.toString()}`;
});

const sitePackageModuleOptions = computed(() => [
    { key: 'site', label: starterModuleLabel('site') },
    { key: 'public_texts', label: starterModuleLabel('public_texts') },
    { key: 'layouts', label: starterModuleLabel('layouts') },
    { key: 'templates', label: starterModuleLabel('templates') },
    { key: 'pages', label: starterModuleLabel('pages') },
    { key: 'menus', label: starterModuleLabel('menus') },
    { key: 'media', label: starterModuleLabel('media') },
    { key: 'downloads', label: starterModuleLabel('downloads') },
    { key: 'themes', label: starterModuleLabel('themes') },
    { key: 'redirects', label: starterModuleLabel('redirects') },
    { key: 'taxonomies', label: starterModuleLabel('taxonomies') },
    { key: 'blogs', label: starterModuleLabel('blogs') },
    { key: 'forms', label: starterModuleLabel('forms') },
    { key: 'docs', label: starterModuleLabel('docs') },
]);
const activationModuleOptions = computed(() => [
    { key: 'layouts', label: starterModuleLabel('layouts') },
    { key: 'templates', label: starterModuleLabel('templates') },
    { key: 'pages', label: starterModuleLabel('pages') },
    { key: 'menus', label: starterModuleLabel('menus') },
    { key: 'downloads', label: starterModuleLabel('downloads') },
    { key: 'redirects', label: starterModuleLabel('redirects') },
    { key: 'taxonomies', label: starterModuleLabel('taxonomies') },
    { key: 'blogs', label: starterModuleLabel('blogs') },
    { key: 'forms', label: starterModuleLabel('forms') },
    { key: 'docs', label: starterModuleLabel('docs') },
    { key: 'themes', label: starterModuleLabel('themes') },
]);
const sitePackageExportReady = computed(
    () => (sitePackageExportForm.modules || []).length > 0,
);
const sitePackageExportHref = computed(() => {
    if (!sitePackageExportReady.value) {
        return '#';
    }

    const parameters = new URLSearchParams();

    ['package_key', 'package_name'].forEach((field) => {
        const value = sitePackageExportForm[field];

        if (value !== null && value !== undefined && String(value) !== '') {
            parameters.set(field, String(value));
        }
    });

    (sitePackageExportForm.modules || []).forEach((module) => {
        parameters.append('modules[]', String(module));
    });

    return `${route('admin.cms.settings.site-package-export')}?${parameters.toString()}`;
});

const activeLanguages = computed(() => props.activeLanguages);
const adminLocaleOptions = computed(() => {
    const values = props.adminSettings?.locale_options;

    return Array.isArray(values) ? values : [];
});
const currentFaviconUrl = computed(
    () => props.settings?.favicon?.favicon_192_url || null,
);
const currentLogoUrl = computed(() => props.settings?.logo?.url || null);
const cmsModules = computed(() =>
    Array.isArray(props.modules?.items) ? props.modules.items : [],
);

const seoNumberFields = computed(() => [
    {
        key: 'seo_h1_min_length',
        label: t('settings.form.seo_h1_min_length', 'H1 minimum lengte'),
        min: 1,
        max: 160,
    },
    {
        key: 'seo_h1_max_length',
        label: t('settings.form.seo_h1_max_length', 'H1 maximum lengte'),
        min: 1,
        max: 220,
    },
    {
        key: 'seo_h2_max_length',
        label: t('settings.form.seo_h2_max_length', 'H2 maximum lengte'),
        min: 1,
        max: 220,
    },
    {
        key: 'seo_h3_max_length',
        label: t('settings.form.seo_h3_max_length', 'H3 maximum lengte'),
        min: 1,
        max: 220,
    },
    {
        key: 'seo_meta_title_min_length',
        label: t(
            'settings.form.seo_meta_title_min_length',
            'Meta title minimum lengte',
        ),
        min: 1,
        max: 160,
    },
    {
        key: 'seo_meta_title_max_length',
        label: t(
            'settings.form.seo_meta_title_max_length',
            'Meta title maximum lengte',
        ),
        min: 1,
        max: 220,
    },
    {
        key: 'seo_meta_description_min_length',
        label: t(
            'settings.form.seo_meta_description_min_length',
            'Meta description minimum lengte',
        ),
        min: 1,
        max: 320,
    },
    {
        key: 'seo_meta_description_max_length',
        label: t(
            'settings.form.seo_meta_description_max_length',
            'Meta description maximum lengte',
        ),
        min: 1,
        max: 500,
    },
    {
        key: 'seo_slug_min_length',
        label: t('settings.form.seo_slug_min_length', 'Slug minimum lengte'),
        min: 1,
        max: 80,
    },
    {
        key: 'seo_slug_max_length',
        label: t('settings.form.seo_slug_max_length', 'Slug maximum lengte'),
        min: 1,
        max: 180,
    },
    {
        key: 'seo_url_max_length',
        label: t('settings.form.seo_url_max_length', 'URL maximum lengte'),
        min: 120,
        max: 5000,
    },
    {
        key: 'seo_content_min_words',
        label: t(
            'settings.form.seo_content_min_words',
            'Minimaal aantal woorden',
        ),
        min: 0,
        max: 5000,
    },
]);

const seoBooleanFields = computed(() => [
    {
        key: 'seo_require_meta_title_on_publish',
        label: t(
            'settings.form.seo_require_meta_title_on_publish',
            'Meta title verplicht bij publiceren',
        ),
    },
    {
        key: 'seo_require_meta_description_on_publish',
        label: t(
            'settings.form.seo_require_meta_description_on_publish',
            'Meta description verplicht bij publiceren',
        ),
    },
    {
        key: 'seo_require_single_h1',
        label: t(
            'settings.form.seo_require_single_h1',
            'Exact een H1 verplicht',
        ),
    },
    {
        key: 'seo_require_valid_heading_hierarchy',
        label: t(
            'settings.form.seo_require_valid_heading_hierarchy',
            'Geldige heading-hierarchie verplicht',
        ),
    },
    {
        key: 'seo_require_json_ld',
        label: t(
            'settings.form.seo_require_json_ld',
            'JSON-LD verplicht bij publiceren',
        ),
    },
    {
        key: 'seo_require_og_image_for_posts',
        label: t(
            'settings.form.seo_require_og_image_for_posts',
            'OG image waarschuwing voor berichten',
        ),
    },
]);

const aiProviders = computed(() => {
    const providers = props.translationAi?.providers;

    return Array.isArray(providers) ? providers : [];
});

const selectedAiProvider = computed(() => {
    const provider = String(form.translation_ai.provider || '');

    return aiProviders.value.find((item) => item.value === provider) || null;
});

const availableAiModels = computed(() => {
    const models = selectedAiProvider.value?.models;

    return Array.isArray(models) ? models : [];
});

const hasConfigAiApiKey = computed(() =>
    Boolean(
        selectedAiProvider.value?.has_config_api_key ??
        props.translationAi?.has_config_api_key ??
        false,
    ),
);

const hasEffectiveAiApiKey = computed(() => {
    if (String(form.translation_ai.api_key || '').trim() !== '') {
        return true;
    }

    return (
        (!form.translation_ai.clear_api_key && hasSavedAiApiKey.value) ||
        hasConfigAiApiKey.value
    );
});

const recordIdLabel = computed(() => props.settingMeta?.id ?? '-');
const updatedAtLabel = computed(() =>
    formatRecordDate(props.settingMeta?.updated_at),
);
const createdAtLabel = computed(() =>
    formatRecordDate(props.settingMeta?.created_at),
);
const starterImportDetails = computed(() => {
    const details = page.props?.flash?.details?.starter_import;

    return details && typeof details === 'object' ? details : null;
});
const starterImportSummary = computed(() => {
    const imported = starterImportDetails.value?.imported;

    if (!imported || typeof imported !== 'object') {
        return [];
    }

    return Object.entries(imported).map(([key, count]) => ({
        key,
        count: Number(count || 0),
        label: starterModuleLabel(key),
    }));
});
const moduleInstallDetails = computed(() => {
    const details = page.props?.flash?.details?.cms_module_install;

    return details && typeof details === 'object' ? details : null;
});
const moduleInstallSummary = computed(() => {
    const result = moduleInstallDetails.value?.result;

    if (!result || typeof result !== 'object') {
        return [];
    }

    return Object.entries(result).map(([key, count]) => ({
        key,
        count: Number(count || 0),
        label: cmsModuleResultLabel(key),
    }));
});
const sitePackageImportDetails = computed(() => {
    const details = page.props?.flash?.details?.site_package_import;

    return details && typeof details === 'object' ? details : null;
});
const sitePackageImportSummary = computed(() => {
    const imported = sitePackageImportDetails.value?.imported;

    if (!imported || typeof imported !== 'object') {
        return [];
    }

    return Object.entries(imported).map(([key, count]) => ({
        key,
        count: Number(count || 0),
        label: starterModuleLabel(key),
    }));
});
const sitePackagePreviewDetails = computed(() => {
    const details = page.props?.flash?.details?.site_package_preview;

    return details && typeof details === 'object' ? details : null;
});
const sitePackagePreviewSummary = computed(() => {
    const modules = sitePackagePreviewDetails.value?.modules;

    if (!modules || typeof modules !== 'object') {
        return [];
    }

    return Object.entries(modules).map(([key, count]) => ({
        key,
        count: Number(count || 0),
        label: starterModuleLabel(key),
    }));
});
const sitePackagePreviewWarnings = computed(() => {
    const warnings = sitePackagePreviewDetails.value?.warnings;

    return Array.isArray(warnings) ? warnings : [];
});
const sitePackageActivationDetails = computed(() => {
    const details = page.props?.flash?.details?.site_package_activation;

    return details && typeof details === 'object' ? details : null;
});
const sitePackageActivationSummary = computed(() => {
    const activated = sitePackageActivationDetails.value?.activated;

    if (!activated || typeof activated !== 'object') {
        return [];
    }

    return Object.entries(activated).map(([key, count]) => ({
        key,
        count: Number(count || 0),
        label: starterModuleLabel(key),
    }));
});
const suggestedActivationKey = computed(
    () =>
        sitePackageImportDetails.value?.key ||
        sitePackagePreviewDetails.value?.key ||
        '',
);

watch(
    suggestedActivationKey,
    (packageKey) => {
        if (packageKey && !activationForm.package_key) {
            activationForm.package_key = packageKey;
        }
    },
    { immediate: true },
);

const cardFlash = computed(() => {
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

    return { type: 'info', message: '' };
});

const robotsPreview = computed(() => {
    if (form.global_noindex) {
        return [
            `User-agent: *`,
            `Disallow: /`,
            ``,
            `Sitemap: ${props.robotsSitemapUrl}`,
        ].join('\n');
    }

    const lines = [
        'User-agent: *',
        ...props.robotsDefaultDisallowPaths.map((path) => `Disallow: ${path}`),
    ];

    if (form.robots_extra_rules?.trim()) {
        lines.push(
            '',
            '# Extra regels uit CMS',
            form.robots_extra_rules.trim(),
        );
    }

    lines.push('', `Sitemap: ${props.robotsSitemapUrl}`);

    return `${lines.join('\n')}\n`;
});

function insertRobotsSnippet(value) {
    const current = String(form.robots_extra_rules || '').trimEnd();
    form.robots_extra_rules = current ? `${current}\n${value}` : value;
}

function mediaOptionLabel(asset) {
    return asset?.original_filename || asset?.filename || `#${asset?.id}`;
}

function connectSearchConsole() {
    if (!searchConsoleCanConnect.value || form.isDirty) {
        return;
    }

    window.location.assign(route('admin.cms.search-console.connect'));
}

function testSearchConsole() {
    if (form.isDirty || !props.searchConsole.has_oauth_token) {
        return;
    }

    router.post(
        route('admin.cms.search-console.test'),
        {},
        { preserveScroll: true },
    );
}

function disconnectSearchConsole() {
    if (form.isDirty || !props.searchConsole.has_oauth_token) {
        return;
    }

    router.post(
        route('admin.cms.search-console.disconnect'),
        {},
        { preserveScroll: true },
    );
}

watch(
    () => form.translation_ai.provider,
    () => {
        if (availableAiModels.value.length === 0) {
            return;
        }

        const currentModel = String(form.translation_ai.model || '');
        const availableModelValues = availableAiModels.value.map((item) =>
            String(item.value || ''),
        );

        if (availableModelValues.includes(currentModel)) {
            return;
        }

        form.translation_ai.model =
            String(selectedAiProvider.value?.default_model || '') ||
            availableModelValues[0];
    },
    { immediate: true },
);

watch(
    () => form.translation_ai.fill_limit_max,
    (value) => {
        const maxValue = Math.max(1, Number(value || 1));

        if (Number(form.translation_ai.fill_limit_default || 1) > maxValue) {
            form.translation_ai.fill_limit_default = maxValue;
        }
    },
);

watch(
    () => form.translation_ai.clear_api_key,
    (clearApiKey) => {
        if (clearApiKey) {
            form.translation_ai.api_key = '';
        }
    },
);

watch(
    () => form.visitor_tracking.clear_geo_api_key,
    (clearApiKey) => {
        if (clearApiKey) {
            form.visitor_tracking.geo_api_key = '';
        }
    },
);

watch(
    () => form.search_console.clear_oauth_client_secret,
    (clearClientSecret) => {
        if (clearClientSecret) {
            form.search_console.oauth_client_secret = '';
        }
    },
);

watch(faviconCrop, () => drawFaviconPreview(), { deep: true });

watch(logoCrop, () => drawLogoPreview(), { deep: true });

watch(
    () => props.mediaOptions,
    (items) => {
        localMediaOptions.value = [...items];
    },
);

watch(
    () => props.mediaFolders,
    (items) => {
        localMediaFolders.value = [...items];
    },
);

function selectFaviconFile(event) {
    const file = event.target?.files?.[0] || null;

    form.clearErrors('favicon_file');
    form.favicon_file = null;

    if (!file) {
        clearFaviconSource();

        return;
    }

    if (!['image/jpeg', 'image/png'].includes(file.type)) {
        form.setError(
            'favicon_file',
            t(
                'settings.form.image_type_error',
                'Kies een JPG of PNG afbeelding.',
            ),
        );
        clearFaviconSource();

        return;
    }

    clearFaviconSource();

    const objectUrl = URL.createObjectURL(file);
    const image = new Image();

    image.onload = async () => {
        faviconSourceUrl.value = objectUrl;
        faviconSourceImage.value = image;
        resetFaviconCrop(false);
        await nextTick();
        drawFaviconPreview();
    };
    image.onerror = () => {
        URL.revokeObjectURL(objectUrl);
        form.setError(
            'favicon_file',
            t(
                'settings.form.image_read_error',
                'De afbeelding kon niet gelezen worden.',
            ),
        );
    };
    image.src = objectUrl;
}

function selectLogoFile(event) {
    const file = event.target?.files?.[0] || null;

    form.clearErrors('logo_file');
    form.logo_file = null;

    if (!file) {
        clearLogoSource();

        return;
    }

    if (!['image/jpeg', 'image/png'].includes(file.type)) {
        form.setError(
            'logo_file',
            t(
                'settings.form.image_type_error',
                'Kies een JPG of PNG afbeelding.',
            ),
        );
        clearLogoSource();

        return;
    }

    clearLogoSource();

    const objectUrl = URL.createObjectURL(file);
    const image = new Image();

    image.onload = async () => {
        logoSourceUrl.value = objectUrl;
        logoSourceImage.value = image;
        resetLogoCrop(false);
        await nextTick();
        drawLogoPreview();
    };
    image.onerror = () => {
        URL.revokeObjectURL(objectUrl);
        form.setError(
            'logo_file',
            t(
                'settings.form.image_read_error',
                'De afbeelding kon niet gelezen worden.',
            ),
        );
    };
    image.src = objectUrl;
}

function selectStarterFile(event) {
    starterForm.clearErrors('starter_zip');
    starterForm.starter_zip = event.target?.files?.[0] || null;
}

function selectSitePackageFile(event) {
    sitePackageForm.clearErrors('site_package_zip');
    sitePackageForm.site_package_zip = event.target?.files?.[0] || null;
}

function submitStarterImport() {
    starterForm.post(route('admin.cms.settings.starter-import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            starterForm.reset();

            if (starterInput.value?.$el instanceof HTMLInputElement) {
                starterInput.value.$el.value = '';
            } else if (starterInput.value instanceof HTMLInputElement) {
                starterInput.value.value = '';
            }
        },
    });
}

function submitSitePackageImport() {
    sitePackageForm.post(route('admin.cms.settings.site-package-import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            sitePackageForm.reset();

            if (sitePackageInput.value?.$el instanceof HTMLInputElement) {
                sitePackageInput.value.$el.value = '';
            } else if (sitePackageInput.value instanceof HTMLInputElement) {
                sitePackageInput.value.value = '';
            }
        },
    });
}

function submitSitePackagePreview() {
    sitePackageForm.post(route('admin.cms.settings.site-package-preview'), {
        forceFormData: true,
        preserveScroll: true,
    });
}

function submitSitePackageActivation() {
    activationForm.post(route('admin.cms.settings.site-package-activate'), {
        preserveScroll: true,
    });
}

function installCmsModule(cmsModule) {
    if (!cmsModule?.key) {
        return;
    }

    moduleInstallForm.post(
        route('admin.cms.settings.modules.install', {
            module: cmsModule.key,
        }),
        {
            preserveScroll: true,
        },
    );
}

function installCmsModuleDemoData(cmsModule) {
    if (!cmsModule?.key) {
        return;
    }

    moduleDemoDataForm.post(
        route('admin.cms.settings.modules.demo-data', {
            module: cmsModule.key,
        }),
        {
            preserveScroll: true,
        },
    );
}

function preventStarterExportIfIncomplete(event) {
    if (starterExportReady.value) {
        return;
    }

    event.preventDefault();
}

function preventSitePackageExportIfIncomplete(event) {
    if (sitePackageExportReady.value) {
        return;
    }

    event.preventDefault();
}

function optionLabel(item, labelField) {
    const label = String(item?.[labelField] || '');
    const locale = item?.locale ? ` · ${item.locale}` : '';
    const status = item?.status ? ` · ${item.status}` : '';
    const inactive =
        item?.is_active === false
            ? ` · ${t('settings.form.starter_export_inactive', 'inactive')}`
            : '';

    return `${label || item?.id}${locale}${status}${inactive}`;
}

function starterModuleLabel(key) {
    const labels = {
        site: t('settings.form.starter_module_site', 'Site settings'),
        public_texts: t(
            'settings.form.starter_module_public_texts',
            'Public texts',
        ),
        layouts: t('settings.form.starter_module_layouts', 'Layouts'),
        templates: t('settings.form.starter_module_templates', 'Templates'),
        pages: t('settings.form.starter_module_pages', "Pagina's"),
        menus: t('settings.form.starter_module_menus', "Menu's"),
        media: t('settings.form.starter_module_media', 'Media'),
        downloads: t('settings.form.starter_module_downloads', 'Downloads'),
        themes: t('settings.form.starter_module_themes', 'Themes'),
        redirects: t('settings.form.starter_module_redirects', 'Redirects'),
        taxonomies: t('settings.form.starter_module_taxonomies', 'Taxonomies'),
        blogs: t('settings.form.starter_module_blogs', 'Blogs'),
        forms: t('settings.form.starter_module_forms', 'Forms'),
        docs: t('settings.form.starter_module_docs', 'Documentation'),
        homepage: t(
            'settings.form.site_package_activation_homepage',
            'Homepage',
        ),
        default_layouts: t(
            'settings.form.site_package_activation_default_layouts',
            'Default layouts',
        ),
        default_templates: t(
            'settings.form.site_package_activation_default_templates',
            'Default templates',
        ),
    };

    return labels[key] || key;
}

function moduleStatusLabel(cmsModule) {
    if (cmsModule?.installed) {
        return t('settings.form.module_status_installed', 'Installed');
    }

    return t('settings.form.module_status_not_installed', 'Not installed');
}

function moduleActionLabel(cmsModule) {
    if (cmsModule?.outdated) {
        return t('settings.form.module_update_button', 'Update module');
    }

    if (cmsModule?.installed) {
        return t('settings.form.module_sync_button', 'Synchronize module');
    }

    return t('settings.form.module_install_button', 'Install module');
}

function cmsModuleResultLabel(key) {
    const labels = {
        module: t('settings.form.module_result_module', 'Module'),
        permissions: t(
            'settings.form.module_result_permissions',
            'Permissions',
        ),
        templates: t('settings.form.module_result_templates', 'Templates'),
        collections: t(
            'settings.form.module_result_collections',
            'Collections',
        ),
        versions: t('settings.form.module_result_versions', 'Versions'),
        public_texts: t(
            'settings.form.module_result_public_texts',
            'Public texts',
        ),
        translations: t(
            'settings.form.module_result_translations',
            'Translations',
        ),
        pages: t('settings.form.module_result_pages', 'Pages'),
        blocks: t('settings.form.module_result_blocks', 'Blocks'),
        revisions: t('settings.form.module_result_revisions', 'Revisions'),
    };

    return labels[key] || key;
}

function clearFaviconSource() {
    if (faviconSourceUrl.value) {
        URL.revokeObjectURL(faviconSourceUrl.value);
    }

    faviconSourceUrl.value = '';
    faviconSourceImage.value = null;
}

function clearLogoSource() {
    if (logoSourceUrl.value) {
        URL.revokeObjectURL(logoSourceUrl.value);
    }

    logoSourceUrl.value = '';
    logoSourceImage.value = null;
}

function resetFaviconCrop(draw = true) {
    faviconCrop.value = {
        zoom: 1,
        offsetX: 0,
        offsetY: 0,
    };

    if (draw) {
        drawFaviconPreview();
    }
}

function resetLogoCrop(draw = true) {
    logoCrop.value = {
        zoom: 1,
        offsetX: 0,
        offsetY: 0,
    };

    if (draw) {
        drawLogoPreview();
    }
}

function drawFaviconPreview() {
    const canvas = faviconPreviewCanvas.value;

    if (!(canvas instanceof HTMLCanvasElement)) {
        return;
    }

    drawFaviconCanvas(canvas);
}

function drawFaviconCanvas(canvas) {
    const image = faviconSourceImage.value;
    const context = canvas.getContext('2d');

    if (!image || !context) {
        return;
    }

    const size = canvas.width;
    const scale =
        Math.max(size / image.naturalWidth, size / image.naturalHeight) *
        faviconCrop.value.zoom;
    const width = image.naturalWidth * scale;
    const height = image.naturalHeight * scale;
    const offsetScale = size / 192;
    const x = (size - width) / 2 + faviconCrop.value.offsetX * offsetScale;
    const y = (size - height) / 2 + faviconCrop.value.offsetY * offsetScale;

    context.clearRect(0, 0, size, size);
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, size, size);
    context.drawImage(image, x, y, width, height);
}

function drawLogoPreview() {
    const canvas = logoPreviewCanvas.value;

    if (!(canvas instanceof HTMLCanvasElement)) {
        return;
    }

    drawLogoCanvas(canvas);
}

function drawLogoCanvas(canvas) {
    const image = logoSourceImage.value;
    const context = canvas.getContext('2d');

    if (!image || !context) {
        return;
    }

    const scale =
        Math.max(
            canvas.width / image.naturalWidth,
            canvas.height / image.naturalHeight,
        ) * logoCrop.value.zoom;
    const width = image.naturalWidth * scale;
    const height = image.naturalHeight * scale;
    const offsetScaleX = canvas.width / 480;
    const offsetScaleY = canvas.height / 160;
    const x =
        (canvas.width - width) / 2 + logoCrop.value.offsetX * offsetScaleX;
    const y =
        (canvas.height - height) / 2 + logoCrop.value.offsetY * offsetScaleY;

    context.clearRect(0, 0, canvas.width, canvas.height);
    context.drawImage(image, x, y, width, height);
}

async function prepareFaviconFile() {
    if (!faviconSourceImage.value) {
        form.favicon_file = null;

        return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 512;
    drawFaviconCanvas(canvas);

    const blob = await new Promise((resolve) =>
        canvas.toBlob(resolve, 'image/png'),
    );

    if (!(blob instanceof Blob)) {
        form.setError(
            'favicon_file',
            t(
                'settings.form.favicon_prepare_error',
                'De favicon kon niet voorbereid worden.',
            ),
        );

        return;
    }

    form.favicon_file = new File([blob], 'favicon.png', { type: 'image/png' });
}

async function prepareLogoFile() {
    if (!logoSourceImage.value) {
        form.logo_file = null;

        return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = 960;
    canvas.height = 320;
    drawLogoCanvas(canvas);

    const blob = await new Promise((resolve) =>
        canvas.toBlob(resolve, 'image/png'),
    );

    if (!(blob instanceof Blob)) {
        form.setError(
            'logo_file',
            t(
                'settings.form.logo_prepare_error',
                'Het logo kon niet voorbereid worden.',
            ),
        );

        return;
    }

    form.logo_file = new File([blob], 'logo.png', { type: 'image/png' });
}

async function submit() {
    const submittedApiKey = String(form.translation_ai.api_key || '').trim();
    const clearApiKey = Boolean(form.translation_ai.clear_api_key);
    const submittedGeoApiKey = String(
        form.visitor_tracking.geo_api_key || '',
    ).trim();
    const clearGeoApiKey = Boolean(form.visitor_tracking.clear_geo_api_key);
    const submittedSearchConsoleClientSecret = String(
        form.search_console.oauth_client_secret || '',
    ).trim();
    const clearSearchConsoleClientSecret = Boolean(
        form.search_console.clear_oauth_client_secret,
    );
    const defaultSettings =
        form.setting_translations?.[form.default_locale] ?? {};

    await prepareFaviconFile();
    await prepareLogoFile();

    if (form.errors.favicon_file || form.errors.logo_file) {
        return;
    }

    form.homepage_id = form.homepage_id || null;
    form.site_name = defaultSettings.site_name || form.site_name;
    form.site_tagline = defaultSettings.site_tagline || null;
    form.seo_default_title = defaultSettings.seo_default_title || null;
    form.seo_default_description =
        defaultSettings.seo_default_description || null;
    form.robots_extra_rules = form.robots_extra_rules || null;
    form.auto_locale_country_map = form.auto_locale_country_map || null;
    form.visitor_tracking_excluded_paths =
        form.visitor_tracking_excluded_paths || null;
    form.visitor_tracking_geo_allowed_countries =
        form.visitor_tracking_geo_allowed_countries || null;
    form.post(route('admin.cms.settings.store'), {
        forceFormData: true,
        onSuccess: () => {
            form.translation_ai.api_key = '';
            form.translation_ai.clear_api_key = false;
            form.visitor_tracking.geo_api_key = '';
            form.visitor_tracking.clear_geo_api_key = false;
            form.search_console.oauth_client_secret = '';
            form.search_console.clear_oauth_client_secret = false;
            form.favicon_file = null;
            form.logo_file = null;

            if (faviconInput.value?.$el instanceof HTMLInputElement) {
                faviconInput.value.$el.value = '';
            } else if (faviconInput.value instanceof HTMLInputElement) {
                faviconInput.value.value = '';
            }

            clearFaviconSource();

            if (logoInput.value?.$el instanceof HTMLInputElement) {
                logoInput.value.$el.value = '';
            } else if (logoInput.value instanceof HTMLInputElement) {
                logoInput.value.value = '';
            }

            clearLogoSource();

            if (clearApiKey) {
                hasSavedAiApiKey.value = false;
            } else if (submittedApiKey !== '') {
                hasSavedAiApiKey.value = true;
            }

            if (clearGeoApiKey) {
                hasSavedGeoApiKey.value = false;
            } else if (submittedGeoApiKey !== '') {
                hasSavedGeoApiKey.value = true;
            }

            if (clearSearchConsoleClientSecret) {
                hasSavedSearchConsoleClientSecret.value = false;
            } else if (submittedSearchConsoleClientSecret !== '') {
                hasSavedSearchConsoleClientSecret.value = true;
            }
        },
    });
}

function localizedSettingError(field) {
    return (
        form.errors?.[`setting_translations.${form.default_locale}.${field}`] ??
        ''
    );
}

function formatRecordDate(value) {
    if (!value) {
        return '-';
    }

    const isoDateMatch = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (isoDateMatch) {
        return `${isoDateMatch[3]}/${isoDateMatch[2]}/${isoDateMatch[1]}`;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return [
        String(date.getDate()).padStart(2, '0'),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getFullYear()),
    ].join('/');
}

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return `${formatRecordDate(value)} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}
</script>
