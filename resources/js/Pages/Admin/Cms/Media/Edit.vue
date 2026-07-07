<template>
    <Head :title="t('media.edit_title', 'Media bewerken')" />

    <AdminLayout :title="t('media.edit_title', 'Media bewerken')">
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_420px]">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('media.preview', 'Voorbeeld') }}</CardTitle>
                    <CardDescription>{{
                        asset.original_filename || asset.filename
                    }}</CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div
                        class="overflow-hidden rounded-lg border border-slate-200 bg-slate-100"
                    >
                        <img
                            :src="asset.url"
                            :alt="
                                asset.alt_text ||
                                asset.original_filename ||
                                asset.filename
                            "
                            class="max-h-[70vh] w-full object-contain"
                        />
                    </div>
                    <div
                        class="grid grid-cols-1 gap-3 text-sm text-slate-600 md:grid-cols-2"
                    >
                        <div><strong>ID:</strong> {{ asset.id }}</div>
                        <div><strong>MIME:</strong> {{ asset.mime_type }}</div>
                        <div>
                            <strong
                                >{{
                                    t('media.dimensions', 'Afmetingen')
                                }}:</strong
                            >
                            {{ asset.width || '?' }} x {{ asset.height || '?' }}
                        </div>
                        <div>
                            <strong>{{ t('media.size', 'Grootte') }}:</strong>
                            {{ asset.size_kb }} KB
                        </div>
                        <div class="md:col-span-2">
                            <strong>{{ t('media.path', 'Pad') }}:</strong>
                            {{ asset.path }}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('media.metadata', 'Metadata') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-5" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="folder_id">{{
                                t('media.folder', 'Map')
                            }}</Label>
                            <select
                                id="folder_id"
                                v-model="form.folder_id"
                                class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="">
                                    {{ t('media.no_folder', 'Geen map') }}
                                </option>
                                <option
                                    v-for="folder in folders"
                                    :key="folder.id"
                                    :value="folder.id"
                                >
                                    {{ folder.name }}
                                </option>
                            </select>
                            <p
                                v-if="form.errors.folder_id"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.folder_id }}
                            </p>
                        </div>

                        <LocalizedFieldTabs
                            v-model="form.translations"
                            field="alt_text"
                            :label="t('media.columns.alt_text', 'Alt tekst')"
                            input-id="alt_text"
                            :languages="activeLanguages"
                            :default-locale="defaultLocale"
                            :required-default="false"
                            :error="
                                localizedError('alt_text') ||
                                form.errors.alt_text
                            "
                        />

                        <LocalizedFieldTabs
                            v-model="form.translations"
                            field="caption"
                            :label="t('media.caption', 'Bijschrift')"
                            input-id="caption"
                            type="textarea"
                            :languages="activeLanguages"
                            :default-locale="defaultLocale"
                            :required-default="false"
                            :error="
                                localizedError('caption') || form.errors.caption
                            "
                        />

                        <div class="grid gap-2">
                            <Label for="sort_order">{{
                                t('content_form.sort_order', 'Volgorde')
                            }}</Label>
                            <Input
                                id="sort_order"
                                v-model="form.sort_order"
                                type="number"
                                min="0"
                            />
                            <p
                                v-if="form.errors.sort_order"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.sort_order }}
                            </p>
                        </div>

                        <div class="flex flex-wrap justify-between gap-2">
                            <AdminFormBackButton
                                :href="route('admin.cms.media.index')"
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="t('actions.back', 'Terug')"
                                @save="submit"
                            />
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="destructive"
                                    @click="deleteAsset"
                                >
                                    {{ t('actions.delete', 'Verwijderen') }}
                                </Button>
                                <AdminFormSaveButton
                                    :dirty="form.isDirty"
                                    :processing="form.processing"
                                    :label="t('actions.save', 'Bewaren')"
                                />
                            </div>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
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
import { Head, router, useForm } from '@inertiajs/vue3';

const { t } = useAdminTranslations('cms_admin_ui');

const props = defineProps({
    asset: { type: Object, required: true },
    activeLanguages: { type: Array, default: () => [] },
    defaultLocale: { type: String, required: true },
    folders: { type: Array, required: true },
});

const activeLanguages = props.activeLanguages;
const defaultLocale = props.defaultLocale;

const form = useForm({
    folder_id: props.asset.folder_id ?? '',
    alt_text: props.asset.alt_text ?? '',
    caption: props.asset.caption ?? '',
    translations: normalizeTranslations(),
    sort_order: props.asset.sort_order ?? 0,
});

function normalizeTranslations() {
    const existing = props.asset.translations ?? {};

    return Object.fromEntries(
        props.activeLanguages.map((language) => {
            const translation = existing[language.locale] ?? {};
            const isDefaultLocale = language.locale === props.defaultLocale;

            return [
                language.locale,
                {
                    alt_text:
                        translation.alt_text ??
                        (isDefaultLocale ? props.asset.alt_text : '') ??
                        '',
                    caption:
                        translation.caption ??
                        (isDefaultLocale ? props.asset.caption : '') ??
                        '',
                },
            ];
        }),
    );
}

function submit() {
    const defaultTranslation = form.translations?.[props.defaultLocale] ?? {};
    form.alt_text = defaultTranslation.alt_text || null;
    form.caption = defaultTranslation.caption || null;
    form.post(route('admin.cms.media.update', { id: props.asset.id }));
}

function localizedError(field) {
    return form.errors?.[`translations.${props.defaultLocale}.${field}`] ?? '';
}

function deleteAsset() {
    if (!window.confirm(t('media.delete_confirm', 'Deze media verwijderen?'))) {
        return;
    }

    router.delete(route('admin.cms.media.destroy', { id: props.asset.id }));
}
</script>
