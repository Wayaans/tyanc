<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Heading from "@/components/Heading.vue";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useCurrentUrl } from "@/composables/useCurrentUrl";
import { useTranslations } from "@/lib/translations";
import { toUrl } from "@/lib/utils";
import { edit as editAppearance } from "@/routes/tyanc/settings/appearance";
import { edit as editApplication } from "@/routes/tyanc/settings/application";
import { edit as editNotifications } from "@/routes/tyanc/settings/notifications";
import { edit as editSecurity } from "@/routes/tyanc/settings/security";
import { edit as editUserDefaults } from "@/routes/tyanc/settings/user-defaults";
import type { NavLinkItem } from "@/types";

const { __ } = useTranslations();

const navItems: NavLinkItem[] = [
  { title: "Application", href: editApplication() },
  { title: "App Appearance", href: editAppearance() },
  { title: "Notification Settings", href: editNotifications() },
  { title: "Security", href: editSecurity() },
  { title: "Defaults for New Users", href: editUserDefaults() },
];

const { isCurrentOrParentUrl } = useCurrentUrl();

const activeTab = (): string => {
  for (const item of navItems) {
    if (isCurrentOrParentUrl(item.href)) {
      return toUrl(item.href);
    }
  }
  return toUrl(navItems[0].href);
};
</script>

<template>
  <div class="px-4 py-6">
    <Heading
      :title="__('App Settings')"
      :description="__('Configure global application settings and defaults')"
    />

    <!-- Top tabs navigation -->
    <Tabs :model-value="activeTab()" class="mt-6">
      <TabsList class="mb-6 w-full justify-start overflow-x-auto">
        <TabsTrigger
          v-for="item in navItems"
          :key="toUrl(item.href)"
          :value="toUrl(item.href)"
          as-child
        >
          <Link :href="toUrl(item.href)">
            {{ __(item.title) }}
          </Link>
        </TabsTrigger>
      </TabsList>
    </Tabs>

    <!-- Page content -->
    <div class="max-w-2xl">
      <section class="space-y-12">
        <slot />
      </section>
    </div>
  </div>
</template>
