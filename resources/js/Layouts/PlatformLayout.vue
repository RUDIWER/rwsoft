<template>
    <div class="min-h-screen bg-slate-100 text-slate-900">
        <aside
            class="fixed inset-y-0 left-0 hidden w-72 flex-col border-r border-slate-200 bg-white lg:flex"
        >
            <div class="border-b border-slate-200 px-5 py-5">
                <div
                    class="text-lg font-semibold tracking-tight text-slate-950"
                >
                    {{ appName }} Platform
                </div>
                <div class="text-xs text-slate-500">
                    {{
                        t(
                            'app.platform_subtitle',
                            'Platform management for sites, domains and tenant databases',
                        )
                    }}
                </div>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-4">
                <Link
                    v-for="item in navigationItems"
                    :key="item.route"
                    :href="route(item.route)"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                    :class="
                        isActive(item.route)
                            ? 'bg-blue-50 text-blue-700'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'
                    "
                >
                    <span :class="['mdi text-lg', item.icon]"></span>
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div
                class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500"
            >
                {{ t('app.version_label', 'Version') }} {{ appVersionLabel }}
            </div>
        </aside>

        <div class="lg:pl-72">
            <header
                class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur"
            >
                <div
                    class="flex min-h-14 items-center justify-between gap-3 px-4 sm:px-6"
                >
                    <div>
                        <div class="text-sm font-semibold text-slate-950">
                            {{ props.title }}
                        </div>
                        <div class="text-xs text-slate-500">
                            {{
                                t(
                                    'app.platform_subtitle',
                                    'Platformbeheer voor sites, domeinen en tenant databases',
                                )
                            }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="sr-only" for="platform-locale-select">
                            {{
                                t(
                                    'locale.platform_language',
                                    'Platform language',
                                )
                            }}
                        </label>
                        <select
                            id="platform-locale-select"
                            :value="currentLocale"
                            class="h-9 rounded-md border border-slate-300 bg-white px-2 text-xs text-slate-700 hover:bg-slate-50"
                            @change="changePlatformLocale"
                        >
                            <option
                                v-for="locale in localeOptions"
                                :key="locale.value"
                                :value="locale.value"
                            >
                                {{ locale.label }}
                            </option>
                        </select>
                        <span class="hidden text-sm text-slate-600 sm:inline">
                            {{ userName }}
                        </span>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            {{ t('actions.logout', 'Sign out') }}
                        </Link>
                    </div>
                </div>

                <nav
                    class="flex gap-1 overflow-x-auto border-t border-slate-200 px-3 py-2 lg:hidden"
                >
                    <Link
                        v-for="item in navigationItems"
                        :key="`mobile-${item.route}`"
                        :href="route(item.route)"
                        class="whitespace-nowrap rounded-md px-3 py-2 text-sm"
                        :class="
                            isActive(item.route)
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-slate-600 hover:bg-slate-100'
                        "
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </header>

            <main class="px-4 py-5 sm:px-6">
                <RwFlashMessage
                    v-if="platformFlash.message"
                    class="mb-4"
                    :type="platformFlash.type"
                    :message="platformFlash.message"
                />
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: 'Platform' },
});

const page = usePage();
const { t } = useAdminTranslations('admin_common_ui');

const navigationItems = computed(() => [
    {
        label: t('navigation.dashboard', 'Dashboard'),
        route: 'platform.dashboard',
        icon: 'mdi-view-dashboard',
    },
    {
        label: t('navigation.sites', 'Sites'),
        route: 'platform.sites.index',
        icon: 'mdi-web',
    },
    {
        label: t('navigation.mail_transport', 'Mail delivery'),
        route: 'platform.mail-transport.edit',
        icon: 'mdi-email-fast',
    },
    {
        label: t('navigation.translations', 'Translations'),
        route: 'platform.translations.index',
        icon: 'mdi-translate',
    },
]);

const userName = computed(
    () =>
        page.props?.auth?.user?.name ||
        t('app.platform_user_fallback', 'Platform administrator'),
);
const appName = computed(() => page.props?.app?.name || 'Application');
const appVersionLabel = computed(
    () => page.props?.app?.version_label || 'v0.0.0',
);
const currentLocale = computed(() => page.props?.app?.locale || '');
const localeOptions = computed(() => {
    const values = page.props?.app?.locale_options;

    return Array.isArray(values) ? values : [];
});

function changePlatformLocale(event) {
    const locale = String(event?.target?.value || '').trim();

    if (!locale || locale === currentLocale.value) {
        return;
    }

    router.post(
        route('locale.update'),
        { locale },
        {
            preserveScroll: true,
        },
    );
}

const platformFlash = computed(() => {
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

function isActive(routeName) {
    if (typeof route !== 'function' || typeof route().current !== 'function') {
        return false;
    }

    return route().current(routeName) || route().current(`${routeName}*`);
}
</script>
