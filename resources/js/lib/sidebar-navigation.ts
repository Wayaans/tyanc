import {
    FlaskConical,
    Folder,
    KeyRound,
    LayoutGrid,
    Palette,
    Settings,
    ShieldCheck,
    User,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type {
    NavItem,
    SidebarIconName,
    SidebarNavigationAppData,
    SidebarNavigationItemData,
} from '@/types';

export type SidebarApp = {
    id: SidebarNavigationAppData['id'];
    title: string;
    subtitle: string;
    href: SidebarNavigationAppData['href'];
    icon: Component;
};

const iconMap: Record<SidebarIconName, Component> = {
    'app-logo': AppLogoIcon,
    'flask-conical': FlaskConical,
    folder: Folder,
    'key-round': KeyRound,
    'layout-grid': LayoutGrid,
    palette: Palette,
    settings: Settings,
    'shield-check': ShieldCheck,
    user: User,
};

export const resolveSidebarIcon = (icon: SidebarIconName): Component =>
    iconMap[icon];

export const mapSidebarApps = (
    apps: SidebarNavigationAppData[],
): SidebarApp[] =>
    apps.map((app) => ({
        ...app,
        icon: resolveSidebarIcon(app.icon),
    }));

export const mapSidebarItems = (
    items: SidebarNavigationItemData[],
): NavItem[] =>
    items.map((item) => ({
        title: item.title,
        href: item.href,
        icon: item.icon ? resolveSidebarIcon(item.icon) : undefined,
        permission: item.permission,
        children: item.children ? mapSidebarItems(item.children) : undefined,
    }));
