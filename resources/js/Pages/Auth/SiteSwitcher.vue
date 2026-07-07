<template>
    <Head title="Site kiezen" />

    <div class="min-h-screen bg-slate-100 px-4 py-10 text-slate-900 sm:px-6">
        <div class="mx-auto grid max-w-5xl gap-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-blue-700">RwSoft</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
                        Kies je site
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">
                        Je centrale login is actief. Open een site waarvoor je toegang hebt,
                        of ga naar platformbeheer als je platformbeheerder bent.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="isPlatformAdmin"
                        :href="route('platform.dashboard')"
                        class="inline-flex h-9 items-center rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                    >
                        Platformbeheer
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="inline-flex h-9 items-center rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                    >
                        Afmelden
                    </Link>
                </div>
            </div>

            <div
                v-if="$page.props.flash?.status"
                class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800"
            >
                {{ $page.props.flash.status }}
            </div>

            <div
                v-if="$page.props.flash?.warning"
                class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900"
            >
                {{ $page.props.flash.warning }}
            </div>

            <div
                v-if="$page.props.flash?.error"
                class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-800"
            >
                {{ $page.props.flash.error }}
            </div>

            <div v-if="memberships.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="membership in memberships"
                    :key="membership.id"
                    class="border-slate-200 bg-white text-slate-950 shadow-sm"
                >
                    <CardHeader>
                        <CardTitle>{{ membership.site.name }}</CardTitle>
                        <CardDescription>
                            {{ membership.site.primary_domain || 'Geen primair domein' }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <div class="rounded-md bg-slate-100 px-3 py-2 font-mono text-xs text-slate-600">
                            {{ membership.site.slug }}
                        </div>
                        <div class="grid gap-2">
                            <Button
                                type="button"
                                :disabled="switchingSiteId === membership.site.id || !membership.site.primary_domain"
                                @click="switchSite(membership.site.id)"
                            >
                                <span
                                    v-if="switchingSiteId === membership.site.id"
                                    class="mdi mdi-loading mdi-spin mr-2"
                                ></span>
                                {{ labels.open_admin }}
                            </Button>
                            <a
                                :href="membership.site.public_url || '#'"
                                class="inline-flex h-9 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50"
                                :class="{
                                    'pointer-events-none opacity-50': !membership.site.public_url,
                                }"
                            >
                                {{ labels.open_public }}
                            </a>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card v-else class="border-slate-200 bg-white text-slate-950 shadow-sm">
                <CardHeader>
                    <CardTitle>Geen sites beschikbaar</CardTitle>
                    <CardDescription>
                        Je account heeft nog geen actieve site-membership. Vraag een
                        platformbeheerder om je aan een site te koppelen.
                    </CardDescription>
                </CardHeader>
            </Card>
        </div>
    </div>
</template>

<script setup>
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    memberships: { type: Array, required: true },
    isPlatformAdmin: { type: Boolean, default: false },
    labels: {
        type: Object,
        default: () => ({
            open_admin: 'Open site admin',
            open_public: 'Open public site',
        }),
    },
});

const switchingSiteId = ref(null);

function switchSite(siteId) {
    switchingSiteId.value = siteId;
    router.post(route('site-switcher.switch', { site: siteId }), {}, {
        onFinish: () => {
            switchingSiteId.value = null;
        },
    });
}
</script>
