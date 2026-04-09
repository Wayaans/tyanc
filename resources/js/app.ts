import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h, Fragment } from 'vue';
import Sonner from '@/components/ui/sonner/Sonner.vue';
import 'vue-sonner/style.css';
import '../css/app.css';
import {
    initializeTheme,
    syncThemeFromPageProps,
} from '@/composables/useAppearance';
import { registerTranslations } from '@/lib/translations';
import type { ThemeProps } from '@/types';

const appName = 'Tyanc';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const initialTheme = props.initialPage.props.theme as
            | ThemeProps
            | undefined;

        if (initialTheme) {
            syncThemeFromPageProps(initialTheme);
        }

        const vueApp = createApp({
            render: () => h(Fragment, [h(App, props), h(Sonner)]),
        });

        registerTranslations(
            vueApp,
            props.initialPage.props as Record<string, unknown>,
        );

        vueApp.use(plugin).mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// Re-apply theme on every Inertia navigation so settings changes take effect without a full reload...
router.on('navigate', (event) => {
    const theme = event.detail.page.props.theme as ThemeProps | undefined;

    if (theme) {
        syncThemeFromPageProps(theme);
    }
});

// This will set light / dark mode on page load...
initializeTheme();
