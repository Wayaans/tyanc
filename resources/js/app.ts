import { createInertiaApp, router } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import type { DefineComponent } from "vue";
import { createApp, h, Fragment } from "vue";
import NotificationOrchestrator from "@/components/NotificationOrchestrator.vue";
import Sonner from "@/components/ui/sonner/Sonner.vue";
import "vue-sonner/style.css";
import "../css/app.css";
import {
  initializeTheme,
  syncThemeFromPageProps,
} from "@/composables/useAppearance";
import { syncNotificationSettingsFromPageProps } from "@/lib/notify";
import { registerTranslations } from "@/lib/translations";
import type { NotificationSettingsProps, ThemeProps } from "@/types";

const appName = "Tyanc";

void createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  resolve: (name) =>
    resolvePageComponent(
      `./pages/${name}.vue`,
      import.meta.glob<DefineComponent>("./pages/**/*.vue")
    ),
  setup({ el, App, props, plugin }) {
    const initialTheme = props.initialPage.props.theme as
      | ThemeProps
      | undefined;
    const initialNotificationSettings = props.initialPage.props
      .notificationSettings as NotificationSettingsProps | undefined;

    if (initialTheme) {
      syncThemeFromPageProps(initialTheme);
    }

    syncNotificationSettingsFromPageProps(initialNotificationSettings);

    const vueApp = createApp({
      render: () =>
        h(Fragment, [h(App, props), h(Sonner), h(NotificationOrchestrator)]),
    });

    registerTranslations(
      vueApp,
      props.initialPage.props as Record<string, unknown>
    );

    vueApp.use(plugin).mount(el);
  },
  progress: {
    color: "#4B5563",
  },
});

router.on("navigate", (event) => {
  const theme = event.detail.page.props.theme as ThemeProps | undefined;
  const notificationSettings = event.detail.page.props.notificationSettings as
    | NotificationSettingsProps
    | undefined;

  if (theme) {
    syncThemeFromPageProps(theme);
  }

  syncNotificationSettingsFromPageProps(notificationSettings);
});

initializeTheme();
