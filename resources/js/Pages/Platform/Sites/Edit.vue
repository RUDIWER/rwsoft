<template>
    <Head :title="pageTitle" />

    <PlatformLayout :title="layoutTitle">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
            <Card class="border-slate-200 bg-white shadow-sm">
                <CardHeader>
                    <CardTitle>{{ layoutTitle }}</CardTitle>
                    <CardDescription>
                        {{
                            t(
                                'platform.sites.form.database_name_help',
                                'De tenant database naam wordt server-side gegenereerd.',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="grid gap-5" @submit.prevent="submitSite">
                        <div class="grid gap-2">
                            <Label for="name">{{
                                t('platform.columns.name', 'Naam')
                            }}</Label>
                            <Input id="name" v-model="siteForm.name" required />
                            <p
                                v-if="siteForm.errors.name"
                                class="text-sm text-red-600"
                            >
                                {{ siteForm.errors.name }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="slug">{{
                                t('platform.columns.slug', 'Slug')
                            }}</Label>
                            <Input id="slug" v-model="siteForm.slug" required />
                            <p
                                v-if="siteForm.errors.slug"
                                class="text-sm text-red-600"
                            >
                                {{ siteForm.errors.slug }}
                            </p>
                        </div>

                        <div v-if="!isEditMode" class="grid gap-2">
                            <Label for="primary_domain">{{
                                t(
                                    'platform.sites.form.primary_domain',
                                    'Primair domein',
                                )
                            }}</Label>
                            <Input
                                id="primary_domain"
                                v-model="siteForm.primary_domain"
                                :placeholder="
                                    t(
                                        'platform.sites.form.domain_placeholder',
                                        'voorbeeld.be',
                                    )
                                "
                            />
                            <p
                                v-if="siteForm.errors.primary_domain"
                                class="text-sm text-red-600"
                            >
                                {{ siteForm.errors.primary_domain }}
                            </p>
                        </div>

                        <div v-if="!isEditMode" class="grid gap-2">
                            <Label for="first_admin_email">{{
                                t(
                                    'platform.sites.form.first_admin',
                                    'Eerste beheerder',
                                )
                            }}</Label>
                            <Input
                                id="first_admin_email"
                                v-model="siteForm.first_admin_email"
                                type="email"
                                :placeholder="
                                    t(
                                        'platform.sites.form.first_admin_placeholder',
                                        'bestaande-gebruiker@example.com',
                                    )
                                "
                            />
                            <p class="text-xs text-slate-500">
                                {{
                                    t(
                                        'platform.sites.form.first_admin_help',
                                        'Alleen bestaande centrale gebruikers kunnen gekoppeld worden.',
                                    )
                                }}
                            </p>
                            <p
                                v-if="siteForm.errors.first_admin_email"
                                class="text-sm text-red-600"
                            >
                                {{ siteForm.errors.first_admin_email }}
                            </p>
                        </div>

                        <div
                            v-if="isEditMode"
                            class="rounded-lg border border-slate-200 p-3"
                        >
                            <div
                                class="text-xs font-medium uppercase text-slate-500"
                            >
                                {{
                                    t(
                                        'platform.columns.tenant_database',
                                        'Tenant database',
                                    )
                                }}
                            </div>
                            <div class="mt-1 font-mono text-sm text-slate-900">
                                {{ site.tenant_database }}
                            </div>
                            <div class="mt-2 text-xs text-slate-500">
                                {{ t('platform.columns.status', 'Status') }}:
                                {{ site.status }}
                            </div>
                            <div
                                v-if="site.provisioning_error"
                                class="mt-2 text-sm text-red-700"
                            >
                                {{ site.provisioning_error }}
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <Button as-child type="button" variant="outline">
                                <Link :href="route('platform.sites.index')">{{
                                    t('actions.back', 'Terug')
                                }}</Link>
                            </Button>
                            <Button
                                type="submit"
                                :disabled="siteForm.processing"
                            >
                                {{ t('actions.save', 'Bewaren') }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div v-if="isEditMode" class="space-y-6">
                <Card class="border-slate-200 bg-white shadow-sm">
                    <CardHeader>
                        <CardTitle>{{
                            t(
                                'platform.sites.provisioning.title',
                                'Provisioning',
                            )
                        }}</CardTitle>
                        <CardDescription>
                            {{ provisioningDescription }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <div
                            v-if="isProvisioned"
                            class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800"
                        >
                            {{
                                t(
                                    'platform.sites.provisioning.success',
                                    'Provisioning succesvol uitgevoerd. De tenant database is actief.',
                                )
                            }}
                        </div>
                        <div
                            v-else-if="site.provisioning_error"
                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
                        >
                            {{
                                t(
                                    'platform.sites.provisioning.failed',
                                    'Provisioning mislukt. Controleer de foutmelding en probeer opnieuw.',
                                )
                            }}
                        </div>
                        <div
                            class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-slate-600">{{
                                    t('platform.columns.status', 'Status')
                                }}</span>
                                <span :class="provisioningStatusClass">
                                    {{ provisioningStatusLabel }}
                                </span>
                            </div>
                            <div
                                v-if="site.provisioned_at"
                                class="mt-2 flex items-center justify-between gap-3"
                            >
                                <span class="text-slate-600">{{
                                    t(
                                        'platform.sites.provisioning.executed_at',
                                        'Uitgevoerd op',
                                    )
                                }}</span>
                                <span class="text-slate-900">{{
                                    site.provisioned_at
                                }}</span>
                            </div>
                        </div>
                        <Button
                            type="button"
                            :disabled="
                                provisionForm.processing || isProvisioned
                            "
                            @click="provisionSite"
                        >
                            {{
                                isProvisioned
                                    ? t(
                                          'platform.sites.provisioning.provisioned',
                                          'Database geprovisioned',
                                      )
                                    : t(
                                          'platform.sites.provisioning.provision',
                                          'Database provisionen',
                                      )
                            }}
                        </Button>
                    </CardContent>
                </Card>

                <Card class="border-slate-200 bg-white shadow-sm">
                    <CardHeader>
                        <CardTitle>{{
                            t('platform.sites.domains.title', 'Domeinen')
                        }}</CardTitle>
                        <CardDescription>
                            {{
                                t(
                                    'platform.sites.domains.description',
                                    'Domeinen worden centraal gekoppeld aan deze site.',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <form class="grid gap-3" @submit.prevent="submitDomain">
                            <div class="grid gap-2">
                                <Label for="host">{{
                                    t('platform.columns.host', 'Host')
                                }}</Label>
                                <Input
                                    id="host"
                                    v-model="domainForm.host"
                                    :placeholder="
                                        t(
                                            'platform.sites.form.domain_placeholder',
                                            'voorbeeld.be',
                                        )
                                    "
                                />
                                <p
                                    v-if="domainForm.errors.host"
                                    class="text-sm text-red-600"
                                >
                                    {{ domainForm.errors.host }}
                                </p>
                            </div>
                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="domainForm.is_primary"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                />
                                {{
                                    t(
                                        'platform.sites.form.primary_domain',
                                        'Primair domein',
                                    )
                                }}
                            </label>
                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="domainForm.force_https"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                />
                                {{
                                    t(
                                        'platform.sites.form.force_https',
                                        'Forceer HTTPS',
                                    )
                                }}
                            </label>
                            <Button
                                type="submit"
                                :disabled="domainForm.processing"
                            >
                                {{
                                    t(
                                        'platform.sites.domains.add',
                                        'Domein toevoegen',
                                    )
                                }}
                            </Button>
                        </form>

                        <div
                            class="overflow-hidden rounded-lg border border-slate-200"
                        >
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th
                                            class="px-3 py-2 text-left font-medium"
                                        >
                                            {{
                                                t(
                                                    'platform.columns.host',
                                                    'Host',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left font-medium"
                                        >
                                            {{
                                                t(
                                                    'platform.columns.primary',
                                                    'Primair',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left font-medium"
                                        >
                                            HTTPS
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="domain in site.domains"
                                        :key="domain.id"
                                        class="border-t border-slate-100"
                                    >
                                        <td class="px-3 py-2 text-slate-900">
                                            {{ domain.host }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{
                                                domain.is_primary
                                                    ? t('common.yes', 'Ja')
                                                    : t('common.no', 'Nee')
                                            }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{
                                                domain.force_https
                                                    ? t('common.yes', 'Ja')
                                                    : t('common.no', 'Nee')
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </PlatformLayout>
</template>

<script setup>
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
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
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    site: { type: Object, default: null },
});

const { t } = useAdminTranslations('admin_common_ui');

const isEditMode = computed(() => Boolean(props.site?.id));
const pageTitle = computed(() =>
    isEditMode.value
        ? t('platform.sites.form.edit_title', 'Site bewerken')
        : t('platform.sites.form.create_title', 'Site toevoegen'),
);
const layoutTitle = computed(() =>
    isEditMode.value
        ? t('platform.sites.form.edit_title', 'Site bewerken')
        : t('platform.actions.new_site', 'Nieuwe site'),
);
const isProvisioned = computed(
    () => props.site?.status === 'active' && !props.site?.provisioning_error,
);

const provisioningDescription = computed(() => {
    if (isProvisioned.value) {
        return t(
            'platform.sites.provisioning.ready_description',
            'De fysieke tenant database is aangemaakt en klaar voor gebruik.',
        );
    }

    return t(
        'platform.sites.provisioning.create_description',
        'Maak de fysieke tenant database aan en voer tenant migraties uit.',
    );
});

const provisioningStatusLabel = computed(() => {
    const labels = {
        active: t('platform.status.active', 'Actief'),
        draft: t('platform.status.draft', 'Concept'),
        failed: t('platform.status.failed', 'Mislukt'),
        provisioning: t('platform.status.provisioning', 'Bezig'),
    };

    return (
        labels[props.site?.status] ??
        props.site?.status ??
        t('platform.status.unknown', 'Onbekend')
    );
});

const provisioningStatusClass = computed(() => {
    if (isProvisioned.value) {
        return 'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700';
    }

    if (props.site?.status === 'failed' || props.site?.provisioning_error) {
        return 'rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700';
    }

    if (props.site?.status === 'provisioning') {
        return 'rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700';
    }

    return 'rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700';
});

const siteForm = useForm({
    name: props.site?.name ?? '',
    slug: props.site?.slug ?? '',
    primary_domain: '',
    first_admin_email: '',
});

const domainForm = useForm({
    host: '',
    is_primary: false,
    force_https: true,
});

const provisionForm = useForm({});

function submitSite() {
    siteForm.post(route('platform.sites.store', { id: props.site?.id ?? 0 }));
}

function submitDomain() {
    domainForm.post(
        route('platform.sites.domains.store', { site: props.site.id }),
        {
            onSuccess: () => domainForm.reset('host', 'is_primary'),
        },
    );
}

function provisionSite() {
    provisionForm.post(
        route('platform.sites.provision', { site: props.site.id }),
    );
}
</script>
