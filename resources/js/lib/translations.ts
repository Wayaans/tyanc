import { router } from '@inertiajs/vue3';
import { computed, reactive, type App } from 'vue';

interface I18nState {
    translations: Record<string, string>;
    locale: string;
    availableLocales: string[];
}

/**
 * Module-level reactive state. Updated on every Inertia navigation so
 * template expressions that call __() are automatically reactive.
 */
const i18nState = reactive<I18nState>({
    translations: {},
    locale: 'en',
    availableLocales: [],
});

function syncFromPageProps(props: Record<string, unknown>): void {
    if (props.translations) {
        i18nState.translations = props.translations as Record<string, string>;
    }
    if (props.locale) {
        i18nState.locale = props.locale as string;
    }
    if (props.availableLocales) {
        i18nState.availableLocales = props.availableLocales as string[];
    }
}

// Sync on every client-side navigation (no-op in SSR where window is absent).
if (typeof window !== 'undefined') {
    router.on('navigate', (event) => {
        syncFromPageProps(event.detail.page.props as Record<string, unknown>);
    });
}

/**
 * Translate a key, replacing :placeholder tokens with the supplied replacements.
 * Falls back to the raw key when no translation is found.
 *
 * Reads from the reactive i18nState so Vue templates re-render automatically
 * when translations change between pages.
 *
 * @example __('Welcome back')
 * @example __('Hello, :name!', { name: 'Jane' })
 */
export function __(
    key: string,
    replacements: Record<string, string> = {},
): string {
    let translated = i18nState.translations[key] ?? key;

    for (const [token, value] of Object.entries(replacements)) {
        translated = translated.replace(new RegExp(`:${token}`, 'g'), value);
    }

    return translated;
}

/**
 * Composable for <script setup> blocks.
 * Returns the translate function plus reactive locale / availableLocales.
 */
export function useTranslations() {
    return {
        __,
        locale: computed(() => i18nState.locale),
        availableLocales: computed(() => i18nState.availableLocales),
    };
}

/**
 * Register the $__ global property on a Vue app and seed initial translations
 * from the first Inertia page props.
 *
 * Call once inside the `setup` callback of createInertiaApp in both
 * app.ts (client) and ssr.ts (server).
 */
export function registerTranslations(
    app: App,
    initialProps?: Record<string, unknown>,
): void {
    if (initialProps) {
        syncFromPageProps(initialProps);
    }

    app.config.globalProperties.$__ = __;
}
