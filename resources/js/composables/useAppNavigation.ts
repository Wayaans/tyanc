import { router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { mapSidebarApps, mapSidebarItems } from '@/lib/sidebar-navigation';
import { useTranslations } from '@/lib/translations';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { dashboard as cumpuDashboardRoute } from '@/routes/cumpu';
import { index as cumpuApprovalRulesRoute } from '@/routes/cumpu/approval-rules';
import {
    index as cumpuApprovalsInboxRoute,
    myRequests as cumpuApprovalsMyRequestsRoute,
    show as cumpuApprovalsShowRoute,
    all as cumpuApprovalsAllRoute,
} from '@/routes/cumpu/approvals';
import { index as cumpuApprovalReportsRoute } from '@/routes/cumpu/approvals/reports';
import { dashboard as demoDashboard } from '@/routes/demo';
import { edit as editAccount } from '@/routes/settings/account';
import { index as accessMatrixRoute } from '@/routes/tyanc/access-matrix';
import { index as activityLogRoute } from '@/routes/tyanc/activity-log';
import {
    index as approvalsInboxRoute,
    myRequests as approvalsMyRequestsRoute,
} from '@/routes/tyanc/approvals';
import {
    create as appsCreate,
    edit as appsEdit,
    index as appsRoute,
} from '@/routes/tyanc/apps';
import { index as filesRoute } from '@/routes/tyanc/files';
import { index as messagesRoute } from '@/routes/tyanc/messages';
import { index as permissionsRoute } from '@/routes/tyanc/permissions';
import { index as rolesRoute } from '@/routes/tyanc/roles';
import { edit as editTyancApplication } from '@/routes/tyanc/settings/application';
import {
    create as usersCreate,
    edit as usersEdit,
    index as usersRoute,
    show as usersShow,
} from '@/routes/tyanc/users';
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
            id: 'cumpu',
            title: 'Cumpu',
            subtitle: translate('Approval workspace'),
            href: cumpuDashboardRoute(),
            icon: 'shield-check',
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
            title: translate('Files'),
            href: filesRoute(),
            icon: 'folder',
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
            title: translate('Messages'),
            href: messagesRoute(),
            icon: 'message-square',
            permission: null,
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
            href: editAccount(),
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

    const inboxBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        { title: __('Approvals inbox'), href: approvalsInboxRoute() },
    ]);

    const myRequestsBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        { title: __('My requests'), href: approvalsMyRequestsRoute() },
    ]);

    const messagesBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        { title: __('Messages'), href: messagesRoute() },
    ]);

    const filesBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        { title: __('Files'), href: filesRoute() },
    ]);

    const usersBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        { title: __('Users'), href: usersRoute() },
    ]);

    const appsBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        { title: __('Apps'), href: appsRoute() },
    ]);

    const appsCreateBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        rootBreadcrumb.value,
        { title: __('Apps'), href: appsRoute() },
        { title: __('New app'), href: appsCreate() },
    ]);

    const appsEditBreadcrumbs = (appLabel: string, appKey: string) =>
        computed<BreadcrumbItem[]>(() => [
            rootBreadcrumb.value,
            { title: __('Apps'), href: appsRoute() },
            {
                title: appLabel,
                href: appsEdit({ app: appKey }),
            },
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

    const cumpuRootBreadcrumb = computed<BreadcrumbItem>(() => ({
        title: __('Cumpu'),
        href: cumpuDashboardRoute(),
    }));

    const cumpuDashboardBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        cumpuRootBreadcrumb.value,
        { title: __('Dashboard'), href: cumpuDashboardRoute() },
    ]);

    const cumpuInboxBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        cumpuRootBreadcrumb.value,
        { title: __('Approvals inbox'), href: cumpuApprovalsInboxRoute() },
    ]);

    const cumpuMyRequestsBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        cumpuRootBreadcrumb.value,
        { title: __('My requests'), href: cumpuApprovalsMyRequestsRoute() },
    ]);

    const cumpuApprovalRulesBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        cumpuRootBreadcrumb.value,
        { title: __('Approval rules'), href: cumpuApprovalRulesRoute() },
    ]);

    const cumpuAllApprovalsBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        cumpuRootBreadcrumb.value,
        { title: __('All approvals'), href: cumpuApprovalsAllRoute() },
    ]);

    const cumpuApprovalReportsBreadcrumbs = computed<BreadcrumbItem[]>(() => [
        cumpuRootBreadcrumb.value,
        { title: __('Approval reports'), href: cumpuApprovalReportsRoute() },
    ]);

    const cumpuApprovalShowBreadcrumbs = (
        backLabel: string,
        backHref: string,
        subjectName: string,
        requestId: string,
    ) =>
        computed<BreadcrumbItem[]>(() => [
            cumpuRootBreadcrumb.value,
            {
                title: backLabel,
                href: backHref,
            },
            {
                title: subjectName,
                href: cumpuApprovalsShowRoute({ approvalRequest: requestId }),
            },
        ]);

    return {
        activeApp,
        activeAppId,
        apps,
        appsBreadcrumbs,
        appsCreateBreadcrumbs,
        appsEditBreadcrumbs,
        accessMatrixBreadcrumbs,
        dashboardBreadcrumbs,
        filesBreadcrumbs,
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
        messagesBreadcrumbs,
        activityLogBreadcrumbs: computed<BreadcrumbItem[]>(() => [
            rootBreadcrumb.value,
            { title: __('Activity log'), href: activityLogRoute() },
        ]),
        inboxBreadcrumbs,
        myRequestsBreadcrumbs,
        cumpuDashboardBreadcrumbs,
        cumpuInboxBreadcrumbs,
        cumpuMyRequestsBreadcrumbs,
        cumpuApprovalRulesBreadcrumbs,
        cumpuAllApprovalsBreadcrumbs,
        cumpuApprovalReportsBreadcrumbs,
        cumpuApprovalShowBreadcrumbs,
    };
}
