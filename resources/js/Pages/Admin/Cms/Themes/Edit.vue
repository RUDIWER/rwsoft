<template>
    <Head :title="pageTitle" />

    <AdminLayout :title="pageTitle">
        <form class="grid gap-5" @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <CardTitle>{{
                                themeItem
                                    ? t('themes.edit_title', 'Theme bewerken')
                                    : t('themes.new', 'Nieuw theme')
                            }}</CardTitle>
                            <CardDescription>
                                {{
                                    t(
                                        'themes.developer_css_description',
                                        'Developer CSS wordt gecombineerd met gegenereerde CSS en daarna geminified.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <AdminFormBackButton
                                :href="route('admin.cms.themes.index')"
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="t('actions.back', 'Terug')"
                                @save="submit"
                            />
                            <Button
                                v-if="themeItem?.preview_url"
                                as-child
                                type="button"
                                variant="outline"
                            >
                                <a
                                    :href="themeItem.preview_url"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    {{ t('themes.preview', 'Preview') }}
                                </a>
                            </Button>
                            <Button
                                v-if="themeItem"
                                as-child
                                type="button"
                                variant="outline"
                            >
                                <a
                                    :href="
                                        route('admin.cms.themes.download', {
                                            theme: themeItem.id,
                                        })
                                    "
                                >
                                    {{
                                        t(
                                            'themes.download_zip',
                                            'ZIP downloaden',
                                        )
                                    }}
                                </a>
                            </Button>
                            <AdminFormSaveButton
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="t('actions.save', 'Bewaren')"
                            />
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="name">{{
                                t('themes.name', 'Naam')
                            }}</Label>
                            <Input id="name" v-model="form.name" required />
                            <p
                                v-if="form.errors.name"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="key">{{
                                t('themes.key', 'Theme key')
                            }}</Label>
                            <Input id="key" v-model="form.key" required />
                            <p class="text-xs text-slate-500">
                                {{
                                    t(
                                        'themes.key_help',
                                        'Kleine letters, cijfers en streepjes. Wordt gebruikt voor opslag/export.',
                                    )
                                }}
                            </p>
                            <p
                                v-if="form.errors.key"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.key }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="author">{{
                                t('themes.author', 'Auteur')
                            }}</Label>
                            <Input id="author" v-model="form.author" />
                            <p
                                v-if="form.errors.author"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.author }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label for="version">{{
                                t('themes.version', 'Versie')
                            }}</Label>
                            <Input
                                id="version"
                                v-model="form.version"
                                required
                            />
                            <p
                                v-if="form.errors.version"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.version }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">{{
                            t('common.columns.description', 'Omschrijving')
                        }}</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        ></textarea>
                        <p
                            v-if="form.errors.description"
                            class="text-sm text-red-600"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{
                        t('themes.basic_settings', 'Basisinstellingen')
                    }}</CardTitle>
                    <CardDescription>
                        {{
                            t(
                                'themes.basic_settings_description',
                                'Deze velden genereren CSS in generated.css. Ingevulde velden overschrijven de developer CSS; lege velden laten de theme-basis staan.',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-5">
                    <div
                        class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
                    >
                        {{
                            t(
                                'themes.layer_order_help',
                                'Laagvolgorde: developer.css eerst, generated.css daarna. Admin instellingen winnen alleen wanneer het veld ingevuld is.',
                            )
                        }}
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div
                            v-for="field in settingsFields"
                            :key="field.key"
                            class="grid gap-2"
                        >
                            <Label :for="`theme_setting_${field.key}`">
                                {{ field.label }}
                            </Label>
                            <div
                                v-if="field.type === 'color'"
                                class="flex gap-2"
                            >
                                <input
                                    :id="`theme_setting_${field.key}`"
                                    v-model="form.theme_settings[field.key]"
                                    type="color"
                                    class="h-10 w-14 rounded border border-slate-300 bg-white p-1"
                                />
                                <Input
                                    v-model="form.theme_settings[field.key]"
                                    class="font-mono"
                                    :placeholder="field.default"
                                />
                            </div>
                            <Input
                                v-else
                                :id="`theme_setting_${field.key}`"
                                v-model="form.theme_settings[field.key]"
                                :placeholder="field.default"
                                :style="themeSettingPreviewStyle(field)"
                            />
                            <p class="text-xs text-slate-500">
                                {{
                                    field.css_variable ||
                                    `${field.selector} { ${field.property} }`
                                }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{
                        t('themes.developer_css', 'Developer CSS')
                    }}</CardTitle>
                    <CardDescription>
                        {{
                            t(
                                'themes.developer_css_editor_description',
                                'Deze CSS vormt de theme-basis. Admin basisinstellingen worden daarna geplaatst en kunnen gerichte eigenschappen overschrijven.',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-3">
                    <div
                        v-if="form.errors.developer_css"
                        class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
                    >
                        {{ form.errors.developer_css }}
                    </div>
                    <RwCodeEditor
                        v-model="form.developer_css"
                        language="css"
                        height="560px"
                        :line-wrapping="true"
                        :placeholder="
                            t(
                                'themes.developer_css_placeholder',
                                '/* Schrijf hier theme CSS overrides */',
                            )
                        "
                    />
                </CardContent>
            </Card>
        </form>

        <Card v-if="themeItem" class="mt-5">
            <CardHeader>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle>{{
                            t('content_form.publication', 'Publicatie')
                        }}</CardTitle>
                        <CardDescription>
                            {{
                                t(
                                    'themes.publication_description',
                                    'Publiceren activeert dit theme voor deze website.',
                                )
                            }}
                        </CardDescription>
                    </div>
                    <Button
                        type="button"
                        :disabled="publishProcessing || versions.length === 0"
                        @click="publishTheme"
                    >
                        {{
                            t(
                                'themes.publish_activate',
                                'Publiceren en activeren',
                            )
                        }}
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-slate-600">
                    {{ t('themes.status', 'Status') }}:
                    <strong>{{
                        themeItem.is_active
                            ? t('common.columns.active', 'Actief')
                            : themeItem.status
                    }}</strong>
                </p>
            </CardContent>
        </Card>

        <Card v-if="themeItem" class="mt-5">
            <CardHeader>
                <CardTitle>{{ t('themes.versions', 'Versies') }}</CardTitle>
                <CardDescription>
                    {{
                        t(
                            'themes.versions_description',
                            'Elke save maakt een nieuwe CSS versie. Je kan een eerdere versie herstellen.',
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3">
                    <div
                        v-for="version in versions"
                        :key="version.id"
                        class="rounded-md border border-slate-200 p-3"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div class="grid gap-1 text-sm">
                                <div class="font-mono text-xs text-slate-500">
                                    {{ version.version_hash }}
                                </div>
                                <div>
                                    {{ version.file_size_kb }} KB ·
                                    {{ version.created_at }}
                                </div>
                                <div
                                    v-if="version.external_assets.length"
                                    class="text-amber-700"
                                >
                                    {{
                                        t(
                                            'themes.external_assets',
                                            'Externe assets',
                                        )
                                    }}: {{ version.external_assets.length }}
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-if="version.is_active"
                                    class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700"
                                >
                                    {{
                                        t(
                                            'themes.active_version',
                                            'Actieve versie',
                                        )
                                    }}
                                </span>
                                <Button
                                    v-else
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="restoreVersion(version)"
                                >
                                    {{ t('themes.restore', 'Herstellen') }}
                                </Button>
                            </div>
                        </div>
                    </div>
                    <p
                        v-if="versions.length === 0"
                        class="text-sm text-slate-500"
                    >
                        {{
                            t(
                                'themes.no_versions',
                                'Nog geen versies. Bewaar eerst het theme.',
                            )
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import RwCodeEditor from '@/Components/RwCodeEditor.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
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
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');

const props = defineProps({
    themeItem: { type: Object, default: null },
    developerCss: { type: String, required: true },
    themeSettings: { type: Object, default: () => ({}) },
    settingsFields: { type: Array, required: true },
    versions: { type: Array, required: true },
});

const publishProcessing = ref(false);
const pageTitle = computed(() =>
    props.themeItem
        ? `Theme ${props.themeItem.name}`
        : t('themes.new', 'Nieuw theme'),
);

const form = useForm({
    name: props.themeItem?.name ?? '',
    key: props.themeItem?.key ?? '',
    description: props.themeItem?.description ?? '',
    author: props.themeItem?.author ?? '',
    version: props.themeItem?.version ?? '1.0.0',
    theme_settings: initialThemeSettings(),
    developer_css: props.developerCss ?? '',
});

function initialThemeSettings() {
    return props.settingsFields.reduce((settings, field) => {
        settings[field.key] =
            props.themeSettings?.[field.key] ?? field.default ?? '';

        return settings;
    }, {});
}

function themeSettingPreviewStyle(field) {
    if (!isFontFamilySetting(field)) {
        return null;
    }

    const fontFamily = String(
        form.theme_settings[field.key] || field.default || '',
    ).trim();

    return fontFamily === '' ? null : { fontFamily };
}

function isFontFamilySetting(field) {
    return (
        field?.type === 'text' &&
        typeof field?.key === 'string' &&
        field.key.endsWith('_font_family')
    );
}

function submit() {
    const target = props.themeItem
        ? route('admin.cms.themes.store', { theme: props.themeItem.id })
        : route('admin.cms.themes.store-new');

    form.post(target);
}

function publishTheme() {
    if (!props.themeItem) {
        return;
    }

    publishProcessing.value = true;
    router.post(
        route('admin.cms.themes.publish', { theme: props.themeItem.id }),
        {},
        {
            onFinish: () => {
                publishProcessing.value = false;
            },
        },
    );
}

function restoreVersion(version) {
    router.post(
        route('admin.cms.themes.restore-version', {
            theme: props.themeItem.id,
            version: version.id,
        }),
    );
}
</script>
