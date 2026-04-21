<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { computed } from "vue";
import ApprovalHistoryPanel from "@/components/cumpu/approvals/ApprovalHistoryPanel.vue";
import ApprovalRequestBanner from "@/components/cumpu/approvals/ApprovalRequestBanner.vue";
import Heading from "@/components/Heading.vue";
import AppearancePreview from "@/components/tyanc/settings/AppearancePreview.vue";
import AppearanceSheet from "@/components/tyanc/settings/AppearanceSheet.vue";
import { Separator } from "@/components/ui/separator";
import { useAppNavigation } from "@/composables/useAppNavigation";
import AppLayout from "@/layouts/AppLayout.vue";
import TyancSettingsLayout from "@/layouts/tyanc/settings/Layout.vue";
import { useTranslations } from "@/lib/translations";
import { edit } from "@/routes/tyanc/settings/appearance";
import type { ApprovalContext } from "@/types/cumpu";

type Option = { value: string; label: string };
type FontFamily = { value: string; label: string; stack: string };
type SpacingDensity = { value: string; label: string; density: number };

type Settings = {
  primary_color: string;
  secondary_color: string;
  border_radius: string;
  spacing_density: string;
  spacing_density_value: number;
  font_family: string;
  font_family_stack: string;
  sidebar_variant: string;
};

type Props = {
  settings: Settings;
  fontFamilies: FontFamily[];
  sidebarVariants: Option[];
  spacingDensities: SpacingDensity[];
  approvalContext?: ApprovalContext | null;
};

const props = defineProps<Props>();

const { tyancSettingsBreadcrumbs } = useAppNavigation();
const breadcrumbItems = computed(() =>
  tyancSettingsBreadcrumbs("App Appearance", edit())
);
const { __ } = useTranslations();

const currentFontLabel = computed(
  () =>
    props.fontFamilies.find((f) => f.value === props.settings.font_family)
      ?.label ?? props.settings.font_family
);

const currentSpacingLabel = computed(
  () =>
    props.spacingDensities.find(
      (d) => d.value === props.settings.spacing_density
    )?.label ?? props.settings.spacing_density
);

const currentSidebarLabel = computed(
  () =>
    props.sidebarVariants.find(
      (v) => v.value === props.settings.sidebar_variant
    )?.label ?? props.settings.sidebar_variant
);
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head :title="__('App Appearance settings')" />

    <h1 class="sr-only">{{ __("App Appearance settings") }}</h1>

    <TyancSettingsLayout>
      <ApprovalRequestBanner
        v-if="props.approvalContext"
        :context="props.approvalContext"
      />
      <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
          <Heading
            variant="small"
            :title="__('App Appearance')"
            :description="
              __('Control the global look and feel of the application')
            "
          />

          <AppearanceSheet
            :settings="props.settings"
            :font-families="props.fontFamilies"
            :sidebar-variants="props.sidebarVariants"
            :spacing-densities="props.spacingDensities"
            :approval-context="props.approvalContext"
          />
        </div>

        <Separator />

        <!-- Live preview of current settings -->
        <div class="space-y-4">
          <Heading
            variant="small"
            :title="__('Current settings')"
            :description="
              __('A preview of the active appearance configuration')
            "
          />

          <AppearancePreview
            :primary-color="props.settings.primary_color"
            :secondary-color="props.settings.secondary_color"
            :border-radius="props.settings.border_radius"
            :font-family-stack="props.settings.font_family_stack"
            :font-family-label="currentFontLabel"
            :spacing-density-label="currentSpacingLabel"
            :sidebar-variant-label="currentSidebarLabel"
          />
        </div>

        <Separator />

        <!-- Detail table -->
        <div class="space-y-4">
          <Heading
            variant="small"
            :title="__('Details')"
            :description="__('All active appearance values')"
          />

          <dl class="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2 text-sm">
            <dt class="font-medium text-foreground">
              {{ __("Primary color") }}
            </dt>
            <dd class="flex items-center gap-2 text-muted-foreground">
              <span
                class="inline-block size-4 rounded-sm border"
                :style="{
                  background: props.settings.primary_color,
                }"
              />
              {{ props.settings.primary_color }}
            </dd>

            <dt class="font-medium text-foreground">
              {{ __("Secondary color") }}
            </dt>
            <dd class="flex items-center gap-2 text-muted-foreground">
              <span
                class="inline-block size-4 rounded-sm border"
                :style="{
                  background: props.settings.secondary_color,
                }"
              />
              {{ props.settings.secondary_color }}
            </dd>

            <dt class="font-medium text-foreground">
              {{ __("Border radius") }}
            </dt>
            <dd class="text-muted-foreground">
              {{ props.settings.border_radius }}
            </dd>

            <dt class="font-medium text-foreground">
              {{ __("Font family") }}
            </dt>
            <dd class="text-muted-foreground">
              {{ currentFontLabel }}
            </dd>

            <dt class="font-medium text-foreground">
              {{ __("Spacing density") }}
            </dt>
            <dd class="text-muted-foreground">
              {{ currentSpacingLabel }}
              <span class="ml-1 text-xs">
                (×{{ props.settings.spacing_density_value }})
              </span>
            </dd>

            <dt class="font-medium text-foreground">
              {{ __("Sidebar style") }}
            </dt>
            <dd class="text-muted-foreground">
              {{ currentSidebarLabel }}
            </dd>
          </dl>
        </div>
      </div>
      <ApprovalHistoryPanel
        v-if="props.approvalContext"
        :context="props.approvalContext"
      />
    </TyancSettingsLayout>
  </AppLayout>
</template>
