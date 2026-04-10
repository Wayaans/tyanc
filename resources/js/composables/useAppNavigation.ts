import { router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { mapSidebarApps, mapSidebarItems } from '@/lib/sidebar-navigation';
import { useTranslations } from '@/lib/translations';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { dashboard as demoDashboard } from '@/routes/demo';
import { index as accessMatrixRoute } from '@/routes/tyanc/access-matrix';
import { index as activityLogRoute } from '@/routes/tyanc/activity-log';
import { index as appsRoute } from '@/routes/tyanc/apps';
import { index as permissionsRoute } from '@/routes/tyanc/permissions';
import { index as rolesRoute } from '@/routes/tyanc/roles';
import { edit as editTyancApplication } from '@/routes/tyanc/settings/application';
import {
    create as usersCreate,
    edit as usersEdit,
    index as usersRoute,
    show as usersShow,
} from '@/routes/tyanc/users';
import { edit as editProfile } from '@/routes/user-profile';
import type { BreadcrumbItem, NavItem, SidebarNavigationData } from '@/types';

const resolveFallbackSidebarNavigation = (
    appName: string,
    translate: (key: string) => string,
): SidebarNavigationData => ({
    apps: [
        {
            id: 'tyanc',
            title: appName,
            subtitle: translate('Admin panel'),
            href: dashboard(),
            icon: 'app-logo',
        },
        {
            id: 'demo',
            title: 'Demo',
            subtitle: translate('Sandbox'),
            href: demoDashboard(),
            icon: 'flask-conical',
        },
    ],
    menu: [
        {
            title: translate('Dashboard'),
            href: dashboard(),
            icon: 'layout-grid',
            permission: null,
        },
        {
            title: translate('Users'),
            href: usersRoute(),
            icon: 'user',
            permission: null,
        },
        {
            title: translate('Activity log'),
            href: activityLogRoute(),
            icon: 'shield-check',
            permission: null,
        },
        {
            title: translate('Governance'),
            icon: 'key-round',
            permission: null,
            children: [
                {
                    title: translate('Apps'),
                    href: appsRoute(),
                    icon: 'layout-grid',
                    permission: null,
                },
                {
                    title: translate('Roles'),
                    href: rolesRoute(),
                    icon: 'shield-check',
                    permission: null,
                },
                {
                    title: translate('Permissions'),
                    href: permissionsRoute(),
                    icon: 'key-round',
                    permission: null,
                },
                {
                    title: translate('Access matrix'),
                    href: accessMatrixRoute(),
                    icon: 'shield-check',
                    permission: null,
                },
            ],
        },
        {
            title: translate('App Settings'),
            href: editTyancApplication(),
            icon: 'settings',
            permission: null,
        },
    ],
});

const persistCurrentApp = (appId: string) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = 60 * 60 * 24 * 365;

    document.cookie = `current_app=${appId};path=/;max-age=${maxAge};SameSite=Lax`;
};

export function useAppNavigation() {
    const page = usePage();
    const { __ } = useTranslations();

    const activeAppId = computed<string>(
        () => page.props.currentApp ?? 'tyanc',
    );

    const sidebarNavigation = computed<SidebarNavigationData>(() => {
        if (page.props.sidebarNavigation) {
            return page.props.sidebarNavigation;
        }

        return resolveFallbackSidebarNavigation(
            page.props.brand?.app_name ?? 'Tyanc',
            __,
        );
    });
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

    const usersBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        { title: __('Users'), href: usersRoute() },
    ]);

    const appsBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        { title: __('Apps'), href: appsRoute() },
    ]);

    const rolesBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        { title: __('Roles'), href: rolesRoute() },
    ]);

    const permissionsBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        { title: __('Permissions'), href: permissionsRoute() },
    ]);

    const accessMatrixBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        { title: __('Access matrix'), href: accessMatrixRoute() },
    ]);

    const usersCreateBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        { title: __('Users'), href: usersRoute() },
        { title: __('New user'), href: usersCreate() },
    ]);

    const usersShowBreadcrumbs = (userName: string, userId: string) =>
        computed<BreadcrumbItem[]>(() => [
            rootBreadcrumb.value,
            { title: __('Users'), href: usersRoute() },
            { title: userName, href: usersShow({ user: userId }) },
        ]);

    const usersEditBreadcrumbs = (userName: string, userId: string) =>
        computed<BreadcrumbItem[]>(() => [
            rootBreadcrumb.value,
            { title: __('Users'), href: usersRoute() },
            { title: userName, href: usersShow({ user: userId }) },
            { title: __('Edit'), href: usersEdit({ user: userId }) },
        ]);

    return {
        activeApp,
        activeAppId,
        apps,
        appsBreadcrumbs,
        accessMatrixBreadcrumbs,
        dashboardBreadcrumbs,
        mainNavItems,
        permissionsBreadcrumbs,
        rolesBreadcrumbs,
        rootBreadcrumb,
        settingsBreadcrumbs,
        switchApp,
        tyancSettingsBreadcrumbs,
        usersBreadcrumbs,
        usersCreateBreadcrumbs,
        usersShowBreadcrumbs,
        usersEditBreadcrumbs,
        activityLogBreadcrumbs: computed<BreadcrumbItem[]>(() => [
            rootBreadcrumb.value,
            { title: __('Activity log'), href: activityLogRoute() },
        ]),
    };
}
