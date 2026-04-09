import type {
    AppId,
    BrandProps,
    NotificationsPayload,
    SidebarNavigationData,
    ThemeProps,
} from '@/types';
import type { Auth } from '@/types/auth';

type TranslateFn = (
    key: string,
    replacements?: Record<string, string>,
) => string;

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentApp: AppId;
            sidebarNavigation: SidebarNavigationData;
            theme: ThemeProps;
            brand: BrandProps;
            /** Current active locale code (e.g. "en", "fr"). */
            locale: string;
            /** All locales the application supports. */
            availableLocales: string[];
            /** Flat JSON translation map for the current locale. */
            translations: Record<string, string>;
            /** Notification summary shared on every authenticated page. */
            notifications: NotificationsPayload | null;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
        /** Translate a key with optional :placeholder replacement. */
        $__: TranslateFn;
    }
}
