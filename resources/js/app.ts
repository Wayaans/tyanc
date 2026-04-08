import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import {
    initializeTheme,
    syncThemeFromPageProps,
} from '@/composables/useAppearance';
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

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
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
