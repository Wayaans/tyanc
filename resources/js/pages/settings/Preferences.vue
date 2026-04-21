<script setup lang="ts">
import { Form, Head } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import UserPreferencesController from "@/actions/App/Http/Controllers/UserPreferencesController";
import FormFieldSupport from "@/components/FormFieldSupport.vue";
import Heading from "@/components/Heading.vue";
import TimezoneCombobox from "@/components/TimezoneCombobox.vue";
import SettingsFormFooter from "@/components/tyanc/settings/SettingsFormFooter.vue";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { updateTheme } from "@/composables/useAppearance";
import { useAppNavigation } from "@/composables/useAppNavigation";
import AppLayout from "@/layouts/AppLayout.vue";
import SettingsLayout from "@/layouts/settings/Layout.vue";
import { useTranslations } from "@/lib/translations";
import { edit } from "@/routes/settings/preferences";
import type { Appearance } from "@/types";

type Option = { value: string; label: string };
type SpacingDensity = { value: string; label: string; density: number };

type Preferences = {
  locale: string | null;
  timezone: string | null;
  appearance: string | null;
  sidebar_variant: string | null;
  spacing_density: string | null;
  resolved_locale: string;
  resolved_timezone: string;
  resolved_appearance: string;
  resolved_sidebar_variant: string;
  resolved_spacing_density: string;
  resolved_spacing_density_value: number;
};

type Props = {
  preferences: Preferences;
  appearances: Option[];
  sidebarVariants: Option[];
  spacingDensities: SpacingDensity[];
  locales: string[];
  timezones: string[];
};

const props = defineProps<Props>();

const { __ } = useTranslations();
const { settingsBreadcrumbs } = useAppNavigation();
const breadcrumbItems = computed(() =>
  settingsBreadcrumbs(__("Preferences"), edit())
);

/**
 * Empty string sentinel = "use system default" (field not sent to backend → null).
 */
const SYSTEM_DEFAULT = "";

const selectedAppearance = ref<string>(
  props.preferences.appearance ?? SYSTEM_DEFAULT
);
const selectedSidebarVariant = ref<string>(
  props.preferences.sidebar_variant ?? SYSTEM_DEFAULT
);
const selectedSpacingDensity = ref<string>(
  props.preferences.spacing_density ?? SYSTEM_DEFAULT
);
const selectedLocale = ref<string>(props.preferences.locale ?? SYSTEM_DEFAULT);
const selectedTimezone = ref<string>(
  props.preferences.timezone ?? SYSTEM_DEFAULT
);

watch(
  () => props.preferences,
  (preferences) => {
    selectedAppearance.value = preferences.appearance ?? SYSTEM_DEFAULT;
    selectedSidebarVariant.value =
      preferences.sidebar_variant ?? SYSTEM_DEFAULT;
    selectedSpacingDensity.value =
      preferences.spacing_density ?? SYSTEM_DEFAULT;
    selectedLocale.value = preferences.locale ?? SYSTEM_DEFAULT;
    selectedTimezone.value = preferences.timezone ?? SYSTEM_DEFAULT;
  }
);

/** Live-preview theme when the user picks a different appearance option. */
watch(selectedAppearance, (val) => {
  const theme = (
    val !== SYSTEM_DEFAULT ? val : props.preferences.resolved_appearance
  ) as Appearance;
  updateTheme(theme);
});

function clearField(
  field:
    | "appearance"
    | "sidebar_variant"
    | "spacing_density"
    | "locale"
    | "timezone"
) {
  switch (field) {
    case "appearance":
      selectedAppearance.value = SYSTEM_DEFAULT;
      break;
    case "sidebar_variant":
      selectedSidebarVariant.value = SYSTEM_DEFAULT;
      break;
    case "spacing_density":
      selectedSpacingDensity.value = SYSTEM_DEFAULT;
      break;
    case "locale":
      selectedLocale.value = SYSTEM_DEFAULT;
      break;
    case "timezone":
      selectedTimezone.value = SYSTEM_DEFAULT;
      break;
  }
}

const resolvedAppearanceLabel = computed(
  () =>
    props.appearances.find(
      (a) => a.value === props.preferences.resolved_appearance
    )?.label ?? props.preferences.resolved_appearance
);
const resolvedSidebarLabel = computed(
  () =>
    props.sidebarVariants.find(
      (v) => v.value === props.preferences.resolved_sidebar_variant
    )?.label ?? props.preferences.resolved_sidebar_variant
);
const resolvedSpacingLabel = computed(
  () =>
    props.spacingDensities.find(
      (d) => d.value === props.preferences.resolved_spacing_density
    )?.label ?? props.preferences.resolved_spacing_density
);
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head :title="__('Preferences')" />

    <h1 class="sr-only">{{ __("Preferences") }}</h1>

    <SettingsLayout>
      <div class="space-y-6">
        <Heading
          :title="__('Preferences')"
          :description="
            __(
              'Personalise your display — these settings override application defaults'
            )
          "
        />

        <Form
          v-bind="UserPreferencesController.update.form()"
          :options="{ preserveScroll: true }"
          class="space-y-6"
          v-slot="{ errors, processing, recentlySuccessful }"
        >
          <!-- Display preferences -->
          <div class="space-y-4">
            <Heading
              variant="small"
              :title="__('Display')"
              :description="
                __(
                  'Theme, sidebar style, and spacing — overrides system defaults'
                )
              "
            />

            <div class="grid gap-4 sm:grid-cols-2">
              <!-- Appearance -->
              <div class="grid gap-2">
                <Label for="appearance">{{ __("Theme") }}</Label>
                <Select v-model="selectedAppearance">
                  <SelectTrigger id="appearance" class="w-full">
                    <SelectValue
                      :placeholder="`${__('System default')} (${resolvedAppearanceLabel})`"
                    />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="opt in props.appearances"
                      :key="opt.value"
                      :value="opt.value"
                    >
                      {{ opt.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <input
                  v-if="selectedAppearance !== SYSTEM_DEFAULT"
                  type="hidden"
                  name="appearance"
                  :value="selectedAppearance"
                />
                <FormFieldSupport
                  :hint="
                    selectedAppearance === SYSTEM_DEFAULT
                      ? `${__('Using system default')}: ${resolvedAppearanceLabel}`
                      : undefined
                  "
                  :error="errors.appearance"
                >
                  <button
                    type="button"
                    class="text-xs text-muted-foreground underline-offset-2 hover:underline"
                    @click="clearField('appearance')"
                  >
                    {{ __("Use system default") }}
                  </button>
                </FormFieldSupport>
              </div>

              <!-- Sidebar variant -->
              <div class="grid gap-2">
                <Label for="sidebar_variant">{{ __("Sidebar style") }}</Label>
                <Select v-model="selectedSidebarVariant">
                  <SelectTrigger id="sidebar_variant" class="w-full">
                    <SelectValue
                      :placeholder="`${__('System default')} (${resolvedSidebarLabel})`"
                    />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="variant in props.sidebarVariants"
                      :key="variant.value"
                      :value="variant.value"
                    >
                      {{ variant.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <input
                  v-if="selectedSidebarVariant !== SYSTEM_DEFAULT"
                  type="hidden"
                  name="sidebar_variant"
                  :value="selectedSidebarVariant"
                />
                <FormFieldSupport
                  :hint="
                    selectedSidebarVariant === SYSTEM_DEFAULT
                      ? `${__('Using system default')}: ${resolvedSidebarLabel}`
                      : undefined
                  "
                  :error="errors.sidebar_variant"
                >
                  <button
                    type="button"
                    class="text-xs text-muted-foreground underline-offset-2 hover:underline"
                    @click="clearField('sidebar_variant')"
                  >
                    {{ __("Use system default") }}
                  </button>
                </FormFieldSupport>
              </div>

              <!-- Spacing density -->
              <div class="grid gap-2">
                <Label for="spacing_density">{{ __("Spacing density") }}</Label>
                <Select v-model="selectedSpacingDensity">
                  <SelectTrigger id="spacing_density" class="w-full">
                    <SelectValue
                      :placeholder="`${__('System default')} (${resolvedSpacingLabel})`"
                    />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="density in props.spacingDensities"
                      :key="density.value"
                      :value="density.value"
                    >
                      {{ density.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <input
                  v-if="selectedSpacingDensity !== SYSTEM_DEFAULT"
                  type="hidden"
                  name="spacing_density"
                  :value="selectedSpacingDensity"
                />
                <FormFieldSupport
                  :hint="
                    selectedSpacingDensity === SYSTEM_DEFAULT
                      ? `${__('Using system default')}: ${resolvedSpacingLabel} (×${props.preferences.resolved_spacing_density_value})`
                      : undefined
                  "
                  :error="errors.spacing_density"
                >
                  <button
                    type="button"
                    class="text-xs text-muted-foreground underline-offset-2 hover:underline"
                    @click="clearField('spacing_density')"
                  >
                    {{ __("Use system default") }}
                  </button>
                </FormFieldSupport>
              </div>
            </div>
          </div>

          <Separator />

          <!-- Locale & timezone preferences -->
          <div class="space-y-4">
            <Heading
              variant="small"
              :title="__('Language & time')"
              :description="__('Override your locale and timezone preferences')"
            />

            <div class="grid gap-4 sm:grid-cols-2">
              <!-- Locale -->
              <div class="grid gap-2">
                <Label for="pref_locale">{{ __("Language") }}</Label>
                <Select v-model="selectedLocale">
                  <SelectTrigger id="pref_locale" class="w-full">
                    <SelectValue
                      :placeholder="`${__('System default')} (${props.preferences.resolved_locale})`"
                    />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="loc in props.locales"
                      :key="loc"
                      :value="loc"
                    >
                      {{ loc.toUpperCase() }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <input
                  v-if="selectedLocale !== SYSTEM_DEFAULT"
                  type="hidden"
                  name="locale"
                  :value="selectedLocale"
                />
                <FormFieldSupport
                  :hint="
                    selectedLocale === SYSTEM_DEFAULT
                      ? `${__('Using system default')}: ${props.preferences.resolved_locale}`
                      : undefined
                  "
                  :error="errors.locale"
                >
                  <button
                    type="button"
                    class="text-xs text-muted-foreground underline-offset-2 hover:underline"
                    @click="clearField('locale')"
                  >
                    {{ __("Use system default") }}
                  </button>
                </FormFieldSupport>
              </div>

              <!-- Timezone -->
              <div class="grid gap-2">
                <Label for="pref_timezone">{{ __("Timezone") }}</Label>
                <TimezoneCombobox
                  id="pref_timezone"
                  v-model="selectedTimezone"
                  :timezones="props.timezones"
                />
                <input
                  v-if="selectedTimezone !== SYSTEM_DEFAULT"
                  type="hidden"
                  name="timezone"
                  :value="selectedTimezone"
                />
                <FormFieldSupport
                  :hint="
                    selectedTimezone === SYSTEM_DEFAULT
                      ? `${__('Using system default')}: ${props.preferences.resolved_timezone}`
                      : undefined
                  "
                  :error="errors.timezone"
                >
                  <button
                    type="button"
                    class="text-xs text-muted-foreground underline-offset-2 hover:underline"
                    @click="clearField('timezone')"
                  >
                    {{ __("Use system default") }}
                  </button>
                </FormFieldSupport>
              </div>
            </div>
          </div>

          <Separator />

          <SettingsFormFooter
            :processing="processing"
            :recently-successful="recentlySuccessful"
            :label="__('Save preferences')"
          />
        </Form>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
