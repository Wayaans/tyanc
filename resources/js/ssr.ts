import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createSSRApp, Fragment, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import Sonner from '@/components/ui/sonner/Sonner.vue';
import { registerTranslations } from '@/lib/translations';

const appName = 'Tyanc';

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: renderToString,
            title: (title) => (title ? `${title} - ${appName}` : appName),
            resolve: (name) =>
                resolvePageComponent(
                    `./pages/${name}.vue`,
                    import.meta.glob<DefineComponent>('./pages/**/*.vue'),
                ),
            setup: ({ App, props, plugin }) => {
                const vueApp = createSSRApp({
                    render: () => h(Fragment, [h(App, props), h(Sonner)]),
                });
                registerTranslations(
                    vueApp,
                    props.initialPage.props as Record<string, unknown>,
                );
                return vueApp.use(plugin);
            },
        }),
    { cluster: true },
);
