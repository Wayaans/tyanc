import { router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { mapSidebarApps, mapSidebarItems } from '@/lib/sidebar-navigation';
import { useTranslations } from '@/lib/translations';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { dashboard as demoDashboard } from '@/routes/demo';
import { edit as editTyancApplication } from '@/routes/tyanc/settings/application';
import { edit as editProfile } from '@/routes/user-profile';
import type {
    AppId,
    BreadcrumbItem,
    NavItem,
    SidebarNavigationData,
} from '@/types';

const fallbackSidebarNavigation: SidebarNavigationData = {
    apps: [
        {
            id: 'tyanc',
            title: 'Tyanc',
            subtitle: 'Admin panel',
            href: dashboard(),
            icon: 'app-logo',
        },
        {
            id: 'demo',
            title: 'Demo',
            subtitle: 'Sandbox',
            href: demoDashboard(),
            icon: 'flask-conical',
        },
    ],
    menu: [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: 'layout-grid',
            permission: null,
        },
        {
            title: 'User',
            icon: 'user',
            permission: null,
        },
        {
            title: 'Role & Permission',
            icon: 'key-round',
            permission: null,
            children: [
                {
                    title: 'Role',
                    icon: 'key-round',
                    permission: null,
                },
                {
                    title: 'Permissions',
                    icon: 'key-round',
                    permission: null,
                },
                {
                    title: 'Level',
                    icon: 'key-round',
                    permission: null,
                },
                {
                    title: 'Group',
                    icon: 'key-round',
                    permission: null,
                },
            ],
        },
        {
            title: 'App Settings',
            href: '/tyanc/settings',
            icon: 'settings',
            permission: null,
        },
    ],
};

const persistCurrentApp = (appId: AppId) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = 60 * 60 * 24 * 365;

    document.cookie = `current_app=${appId};path=/;max-age=${maxAge};SameSite=Lax`;
};

export function useAppNavigation() {
    const page = usePage();
    const { __ } = useTranslations();

    const activeAppId = computed<AppId>(() =>
        page.props.currentApp === 'demo' ? 'demo' : 'tyanc',
    );

    const sidebarNavigation = computed<SidebarNavigationData>(
        () => page.props.sidebarNavigation ?? fallbackSidebarNavigation,
    );
    const apps = computed(() =>
        mapSidebarApps(sidebarNavigation.value.apps).map((app) =>
            app.id === 'tyanc'
                ? {
                      ...app,
                      title: page.props.brand?.app_name ?? app.title,
                  }
                : app,
        ),
    );
    const activeApp = computed(
        () =>
            apps.value.find((app) => app.id === activeAppId.value) ??
            apps.value[0],
    );
    const mainNavItems = computed<NavItem[]>(() =>
        mapSidebarItems(sidebarNavigation.value.menu),
    );
    const rootBreadcrumb = computed<BreadcrumbItem>(() => ({
        title: activeApp.value.title,
        href: activeApp.value.href,
    }));
    const dashboardBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        {
            title: __('Dashboard'),
            href: activeApp.value.href,
        },
    ]);

    watch(activeAppId, persistCurrentApp, { immediate: true });

    const switchApp = (appId: string) => {
        if (appId !== 'tyanc' && appId !== 'demo') {
            return;
        }

        if (appId === activeAppId.value) {
            return;
        }

        const selectedApp = apps.value.find((app) => app.id === appId);

        if (!selectedApp) {
            return;
        }

        router.visit(toUrl(selectedApp.href));
    };

    const settingsBreadcrumbs = (
        title: string,
        href: NonNullable<NavItem['href']>,
    ): BreadcrumbItem[] => [
        rootBreadcrumb.value,
        {
            title: __('Account'),
            href: editProfile(),
        },
        {
            title: __(title),
            href,
        },
    ];

    const tyancSettingsBreadcrumbs = (
        title: string,
        href: NonNullable<NavItem['href']>,
    ): BreadcrumbItem[] => [
        rootBreadcrumb.value,
        {
            title: __('App Settings'),
            href: editTyancApplication(),
        },
        {
            title: __(title),
            href,
        },
    ];

    return {
        activeApp,
        activeAppId,
        apps,
        dashboardBreadcrumbs,
        mainNavItems,
        rootBreadcrumb,
        settingsBreadcrumbs,
        switchApp,
        tyancSettingsBreadcrumbs,
    };
}
