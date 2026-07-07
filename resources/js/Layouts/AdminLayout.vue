<template>
    <div class="min-h-screen bg-slate-100 text-slate-900">
        <header
            class="fixed inset-x-0 top-0 z-40 border-b border-slate-700 bg-slate-900 text-white shadow-sm"
        >
            <div
                class="flex h-14 items-center justify-between gap-3 px-3 sm:px-5"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-100 transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-300"
                        :aria-label="t('navigation.toggle_menu', 'Toggle menu')"
                        :title="sidebarToggleTitle"
                        @click="cycleSidebarMode"
                    >
                        <span :class="['mdi text-2xl', sidebarToggleIcon]" />
                    </button>

                    <Link
                        :href="route('admin')"
                        class="flex items-center gap-2 truncate text-sm font-semibold text-white"
                    >
                        <span
                            class="mdi mdi-view-dashboard text-lg text-blue-200"
                        />
                        <span>{{ appName }}</span>
                    </Link>
                </div>

                <div class="flex min-w-0 items-center gap-2">
                    <label class="sr-only" for="admin-locale-select">
                        {{ t('locale.admin_language', 'Admin language') }}
                    </label>
                    <select
                        id="admin-locale-select"
                        name="admin_locale"
                        :value="currentLocale"
                        class="h-9 max-w-32 rounded-md border border-slate-600 bg-slate-800 px-2 text-xs text-slate-100 hover:bg-slate-700 focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-300"
                        @change="changeAdminLocale"
                    >
                        <option
                            v-for="locale in localeOptions"
                            :key="locale.value"
                            :value="locale.value"
                        >
                            {{ locale.label }}
                        </option>
                    </select>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-100 transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-300"
                                :aria-label="
                                    t('navigation.user_menu', 'User menu')
                                "
                            >
                                <span class="mdi mdi-cog text-xl" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-64">
                            <DropdownMenuLabel class="flex items-center gap-2">
                                <UserAvatar />
                                <span class="min-w-0 leading-tight">
                                    <span class="block truncate text-sm">
                                        {{ userName }}
                                    </span>
                                    <span
                                        class="block truncate text-xs font-normal text-slate-500"
                                    >
                                        {{ userEmail }}
                                    </span>
                                </span>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />

                            <DropdownMenuItem as-child>
                                <Link
                                    :href="route('profile.edit')"
                                    class="w-full"
                                >
                                    <span
                                        class="mdi mdi-account-circle-outline text-base"
                                    />
                                    {{ t('navigation.profile', 'Profile') }}
                                </Link>
                            </DropdownMenuItem>

                            <DropdownMenuSub v-if="userManagementItems.length">
                                <DropdownMenuSubTrigger>
                                    <span
                                        class="mdi mdi-account-multiple text-base"
                                    />
                                    {{
                                        t(
                                            'navigation.user_management',
                                            'User management',
                                        )
                                    }}
                                </DropdownMenuSubTrigger>
                                <DropdownMenuSubContent class="w-48">
                                    <DropdownMenuItem
                                        v-for="item in userManagementItems"
                                        :key="item.route"
                                        as-child
                                    >
                                        <Link
                                            :href="route(item.route)"
                                            class="w-full"
                                        >
                                            <span
                                                :class="[
                                                    'mdi text-base',
                                                    item.icon,
                                                ]"
                                            />
                                            {{ item.label }}
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuSubContent>
                            </DropdownMenuSub>

                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                class="text-red-700 focus:text-red-700"
                                @select.prevent="logout"
                            >
                                <span class="mdi mdi-logout text-base" />
                                {{ t('actions.logout', 'Sign out') }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </header>

        <div
            v-if="sidebarMode !== 'hidden'"
            class="fixed inset-0 top-14 z-20 bg-slate-950/30 lg:hidden"
            @click="sidebarMode = 'hidden'"
        />

        <aside
            class="fixed bottom-0 left-0 top-14 z-30 flex flex-col overflow-hidden border-r border-slate-200 bg-white shadow-sm transition-all duration-200"
            :class="sidebarClass"
        >
            <div class="border-b border-slate-200 p-3">
                <div
                    class="flex items-center gap-3"
                    :class="{ 'justify-center': sidebarMode === 'icons' }"
                >
                    <UserAvatar />
                    <div
                        v-if="sidebarMode === 'open'"
                        class="min-w-0 leading-tight"
                    >
                        <div
                            class="truncate text-sm font-semibold text-slate-900"
                        >
                            {{ userName }}
                        </div>
                        <div class="truncate text-xs text-slate-500">
                            {{ userEmail }}
                        </div>
                    </div>
                </div>
            </div>

            <nav class="min-h-0 flex-1 overflow-y-auto p-2">
                <div class="grid gap-1">
                    <div v-for="item in mainNavigationItems" :key="item.route">
                        <button
                            v-if="item.children?.length"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium transition"
                            :class="navigationItemClass(item)"
                            :title="item.label"
                            :aria-expanded="isGroupExpanded(item)"
                            @click="toggleGroup(item)"
                        >
                            <span :class="['mdi text-lg', item.icon]" />
                            <span
                                v-if="sidebarMode === 'open'"
                                class="min-w-0 flex-1 truncate"
                            >
                                {{ item.label }}
                            </span>
                            <span
                                v-if="sidebarMode === 'open'"
                                class="mdi text-lg text-slate-400"
                                :class="
                                    isGroupExpanded(item)
                                        ? 'mdi-chevron-up'
                                        : 'mdi-chevron-down'
                                "
                            />
                        </button>

                        <Link
                            v-else
                            :href="route(item.route)"
                            class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="navigationItemClass(item)"
                            :title="item.label"
                            @click="closeMobileSidebar"
                        >
                            <span :class="['mdi text-lg', item.icon]" />
                            <span
                                v-if="sidebarMode === 'open'"
                                class="truncate"
                            >
                                {{ item.label }}
                            </span>
                        </Link>

                        <div
                            v-if="
                                item.children?.length &&
                                sidebarMode === 'open' &&
                                isGroupExpanded(item)
                            "
                            class="ml-5 mt-1 grid gap-1 border-l border-slate-200 pl-4"
                        >
                            <Link
                                v-for="child in item.children"
                                :key="child.route"
                                :href="route(child.route)"
                                class="rounded-md px-3 py-1.5 text-sm transition"
                                :class="
                                    isActive(child)
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'
                                "
                                @click="closeMobileSidebar"
                            >
                                {{ child.label }}
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="border-t border-slate-200 p-2">
                <Link
                    v-if="canAccess(cmsSettingsItem.route)"
                    :href="route(cmsSettingsItem.route)"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                    :class="navigationItemClass(cmsSettingsItem)"
                    :title="cmsSettingsItem.label"
                    @click="closeMobileSidebar"
                >
                    <span :class="['mdi text-lg', cmsSettingsItem.icon]" />
                    <span v-if="sidebarMode === 'open'" class="truncate">
                        {{ cmsSettingsItem.label }}
                    </span>
                </Link>
                <div
                    class="mt-2 rounded-md px-3 py-1.5 text-[11px] font-medium text-slate-500"
                    :title="versionTitle"
                >
                    <span v-if="sidebarMode === 'open'">
                        {{ t('app.version_label', 'Version') }}
                        {{ appVersionLabel }}
                    </span>
                    <span v-else class="mdi mdi-tag-outline text-base" />
                </div>
            </div>
        </aside>

        <div class="pt-14 transition-all duration-200" :class="contentClass">
            <main class="px-4 py-5 sm:px-6">
                <RwFlashMessage
                    v-if="!props.suppressFlash && adminFlash.message"
                    class="mb-4"
                    :type="adminFlash.type"
                    :message="adminFlash.message"
                    :details="adminFlash.details"
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { computed, defineComponent, h, onMounted, ref, watch } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    suppressFlash: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useAdminTranslations('admin_common_ui');
const sidebarStorageKey = 'rwsoft:admin-sidebar-mode';
const sidebarModes = ['open', 'icons', 'hidden'];
const sidebarMode = ref('open');
const expandedGroups = ref(['admin_menu', 'cms']);

const UserAvatar = defineComponent({
    name: 'UserAvatar',
    setup() {
        return () =>
            h(
                'div',
                {
                    class: 'flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold uppercase text-blue-800 ring-1 ring-blue-200',
                },
                userInitials.value,
            );
    },
});

const navigationItems = computed(() => [
    {
        label: t('navigation.dashboard', 'Dashboard'),
        route: 'admin',
        icon: 'mdi-view-dashboard',
    },
    {
        label: t('navigation.admin_menu', 'Admin'),
        route: 'admin.db-diagram',
        key: 'admin_menu',
        active: [
            'admin.db-diagram*',
            'admin.queries.*',
            'admin.translations.*',
            'admin.cms.redirects.*',
            'admin.cms.languages.*',
        ],
        icon: 'mdi-cog-outline',
        children: [
            {
                label: t('navigation.db_diagram', 'DbDiagram'),
                route: 'admin.db-diagram',
            },
            {
                label: t('navigation.query_builder', 'Query & Reports'),
                route: 'admin.queries.builder.index',
            },
            {
                label: t('navigation.cms_languages', 'Talen'),
                route: 'admin.cms.languages.index',
            },
            {
                label: t('navigation.translations', 'Vertalingen'),
                route: 'admin.translations.index',
            },
            {
                label: t('navigation.cms_redirects', 'Redirects'),
                route: 'admin.cms.redirects.index',
            },
        ],
    },
    {
        label: t('navigation.cms', 'CMS'),
        route: 'admin.cms.pages.index',
        key: 'cms',
        active: 'admin.cms.*',
        icon: 'mdi-web',
        children: [
            {
                label: t('navigation.cms_health', 'Quality'),
                route: 'admin.cms.health.index',
            },
            {
                label: t('navigation.cms_themes', 'Themes'),
                route: 'admin.cms.themes.index',
            },
            {
                label: t('navigation.cms_layouts', 'Layouts'),
                route: 'admin.cms.layouts.index',
            },
            {
                label: t('navigation.cms_blocks', 'Blokken'),
                route: 'admin.cms.blocks.index',
            },
            {
                label: t('navigation.cms_templates', 'Templates'),
                route: 'admin.cms.templates.index',
            },
            {
                label: t('navigation.cms_menus', 'Menus'),
                route: 'admin.cms.menus.index',
            },
            {
                label: t('navigation.cms_pages', 'Pages'),
                route: 'admin.cms.pages.index',
            },
            {
                label: t('navigation.cms_docs', 'Documentation'),
                route: 'admin.cms.docs.index',
                requiresModule: 'docs',
            },
            {
                label: t('navigation.cms_posts', 'Posts'),
                route: 'admin.cms.posts.index',
            },
            {
                label: t('navigation.cms_taxonomy', 'Categories and tags'),
                route: 'admin.cms.taxonomy.index',
            },
            {
                label: t('navigation.cms_media', 'Media'),
                route: 'admin.cms.media.index',
            },
            {
                label: t('navigation.cms_downloads', 'Downloads'),
                route: 'admin.cms.downloads.index',
            },
            {
                label: t('navigation.cms_forms', 'Forms'),
                route: 'admin.cms.forms.index',
            },
            {
                label: t('navigation.cms_form_submissions', 'Form submissions'),
                route: 'admin.cms.form-submissions.index',
            },
            {
                label: t('navigation.cms_mail_templates', 'Mail templates'),
                route: 'admin.cms.mail-templates.index',
            },
            {
                label: t('navigation.cms_emails', 'Emails'),
                route: 'admin.cms.emails.index',
            },
            {
                label: t('navigation.cms_site_users', 'Website accounts'),
                route: 'admin.cms.site-users.index',
            },
        ],
    },
]);

const userManagementNavigationItems = computed(() => [
    {
        label: t('navigation.users', 'Users'),
        route: 'admin.users',
        icon: 'mdi-account-multiple',
    },
    {
        label: t('navigation.roles', 'Roles'),
        route: 'admin.roles',
        icon: 'mdi-shield-account',
    },
    {
        label: t('navigation.permissions', 'Rights'),
        route: 'admin.permissions',
        icon: 'mdi-lock-check',
    },
]);

const cmsSettingsItem = computed(() => ({
    label: t('navigation.cms_settings', 'CMS settings'),
    route: 'admin.cms.settings.edit',
    icon: 'mdi-wrench-cog-outline',
}));

const userName = computed(
    () => page.props?.auth?.user?.name || t('app.user_fallback', 'User'),
);
const userEmail = computed(() => page.props?.auth?.user?.email || '');
const appName = computed(() => page.props?.app?.name || 'Application');
const appVersionLabel = computed(
    () => page.props?.app?.version_label || 'v0.0.0',
);
const versionTitle = computed(
    () => `${t('app.version_label', 'Version')} ${appVersionLabel.value}`,
);
const userInitials = computed(() => {
    const parts = String(userName.value || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (parts.length === 0) {
        return 'U';
    }

    return parts
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('');
});
const currentLocale = computed(() => page.props?.app?.locale || '');
const localeOptions = computed(() => {
    const values = page.props?.app?.locale_options;

    return Array.isArray(values) ? values : [];
});
const sidebarClass = computed(() => {
    if (sidebarMode.value === 'hidden') {
        return 'w-0 -translate-x-full lg:translate-x-0';
    }

    if (sidebarMode.value === 'icons') {
        return 'w-16 translate-x-0';
    }

    return 'w-64 translate-x-0';
});
const contentClass = computed(() => {
    if (sidebarMode.value === 'hidden') {
        return 'lg:pl-0';
    }

    if (sidebarMode.value === 'icons') {
        return 'lg:pl-16';
    }

    return 'lg:pl-64';
});
const sidebarToggleTitle = computed(() => {
    if (sidebarMode.value === 'open') {
        return t('navigation.sidebar_next_icons', 'Show icons only');
    }

    if (sidebarMode.value === 'icons') {
        return t('navigation.sidebar_next_hidden', 'Hide menu');
    }

    return t('navigation.sidebar_next_open', 'Show full menu');
});
const sidebarToggleIcon = computed(() => {
    if (sidebarMode.value === 'open') {
        return 'mdi-menu-open';
    }

    if (sidebarMode.value === 'icons') {
        return 'mdi-menu';
    }

    return 'mdi-menu-close';
});

onMounted(() => {
    const storedMode = window.localStorage.getItem(sidebarStorageKey);

    if (sidebarModes.includes(storedMode)) {
        sidebarMode.value = storedMode;
        return;
    }

    if (window.matchMedia('(max-width: 1023px)').matches) {
        sidebarMode.value = 'hidden';
    }
});

watch(sidebarMode, (mode) => {
    if (sidebarModes.includes(mode)) {
        window.localStorage.setItem(sidebarStorageKey, mode);
    }
});

function changeAdminLocale(event) {
    const locale = String(event?.target?.value || '').trim();

    if (!locale || locale === currentLocale.value) {
        return;
    }

    router.post(
        route('admin.locale.update'),
        { locale },
        {
            preserveScroll: true,
        },
    );
}

function logout() {
    router.post(route('logout'));
}

function cycleSidebarMode() {
    const currentIndex = sidebarModes.indexOf(sidebarMode.value);
    const nextIndex = currentIndex >= 0 ? currentIndex + 1 : 0;

    sidebarMode.value = sidebarModes[nextIndex % sidebarModes.length];
}

function closeMobileSidebar() {
    if (window.matchMedia('(max-width: 1023px)').matches) {
        sidebarMode.value = 'hidden';
    }
}

function groupKey(item) {
    return item.key || item.route;
}

function isGroupExpanded(item) {
    return expandedGroups.value.includes(groupKey(item));
}

function toggleGroup(item) {
    if (sidebarMode.value === 'icons') {
        sidebarMode.value = 'open';
        ensureGroupExpanded(item);
        return;
    }

    const key = groupKey(item);

    if (expandedGroups.value.includes(key)) {
        expandedGroups.value = expandedGroups.value.filter(
            (group) => group !== key,
        );
        return;
    }

    expandedGroups.value = [...expandedGroups.value, key];
}

function ensureGroupExpanded(item) {
    const key = groupKey(item);

    if (!expandedGroups.value.includes(key)) {
        expandedGroups.value = [...expandedGroups.value, key];
    }
}

const adminFlash = computed(() => {
    const flash = page.props?.flash || {};

    if (flash.error) {
        return {
            type: 'danger',
            message: flash.error,
            details: flash.details || [],
        };
    }

    if (flash.warning) {
        return {
            type: 'warning',
            message: flash.warning,
            details: flash.details || [],
        };
    }

    if (flash.status) {
        return {
            type: 'success',
            message: flash.status,
            details: flash.details || [],
        };
    }

    return { type: 'info', message: '', details: [] };
});

const mainNavigationItems = computed(() =>
    navigationItems.value
        .map((item) => ({
            ...item,
            children: Array.isArray(item.children)
                ? item.children.filter(
                      (child) =>
                          canAccess(child.route) && moduleIsAvailable(child),
                  )
                : [],
        }))
        .filter(
            (item) =>
                (canAccess(item.route) && moduleIsAvailable(item)) ||
                item.children.length > 0,
        ),
);

const userManagementItems = computed(() =>
    userManagementNavigationItems.value.filter((item) => canAccess(item.route)),
);

function canAccess(routeName) {
    if (routeName === 'admin') {
        return true;
    }

    const acl = page.props?.acl || {};

    if (acl.is_super_admin) {
        return true;
    }

    return Array.isArray(acl.allowed_routes)
        ? acl.allowed_routes.includes(routeName)
        : false;
}

function moduleIsAvailable(item) {
    const moduleKey = item?.requiresModule;

    if (!moduleKey) {
        return true;
    }

    const installedModules = page.props?.cms_modules?.installed;

    return Array.isArray(installedModules)
        ? installedModules.includes(moduleKey)
        : false;
}

function isActive(item) {
    if (typeof route !== 'function' || typeof route().current !== 'function') {
        return false;
    }

    if (item.route === 'admin') {
        return route().current('admin');
    }

    if (Array.isArray(item.active)) {
        return (
            item.active.some((pattern) => route().current(pattern)) ||
            route().current(`${item.route}*`)
        );
    }

    return (
        route().current(item.active ?? item.route) ||
        route().current(`${item.route}*`)
    );
}

function navigationItemClass(item) {
    if (isActive(item)) {
        return 'bg-blue-50 text-blue-700';
    }

    return 'text-slate-700 hover:bg-slate-100 hover:text-slate-950';
}
</script>
