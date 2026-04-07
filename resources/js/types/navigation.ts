import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { Component } from 'vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type SidebarIconName =
    | 'app-logo'
    | 'flask-conical'
    | 'key-round'
    | 'layout-grid'
    | 'palette'
    | 'settings'
    | 'shield-check'
    | 'user';

export type SidebarNavigationItemData = {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
    icon?: SidebarIconName;
    permission?: string | null;
    children?: SidebarNavigationItemData[];
};

export type SidebarNavigationAppData = {
    id: AppId;
    title: string;
    subtitle: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: SidebarIconName;
};

export type SidebarNavigationData = {
    apps: SidebarNavigationAppData[];
    menu: SidebarNavigationItemData[];
};

export type NavItem = {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
    icon?: Component;
    permission?: string | null;
    isActive?: boolean;
    children?: NavItem[];
};

/**
 * A NavItem that is guaranteed to carry a href.
 * Use this type for header / footer link lists where every item must link somewhere.
 */
export type NavLinkItem = NavItem & {
    href: NonNullable<NavItem['href']>;
};

export type AppId = 'tyanc' | 'demo';
