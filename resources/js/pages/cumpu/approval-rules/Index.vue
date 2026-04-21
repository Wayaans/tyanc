<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import ApprovalRuleCapabilityTable from "@/components/cumpu/approval-rules/ApprovalRuleCapabilityTable.vue";
import ApprovalRuleFilterBar from "@/components/cumpu/approval-rules/ApprovalRuleFilterBar.vue";
import type { ApprovalRuleFilters } from "@/components/cumpu/approval-rules/ApprovalRuleFilterBar.vue";
import ApprovalRuleManagedEditDialog from "@/components/cumpu/approval-rules/ApprovalRuleManagedEditDialog.vue";
import ApprovalRuleSyncStatusCard from "@/components/cumpu/approval-rules/ApprovalRuleSyncStatusCard.vue";
import { useAppNavigation } from "@/composables/useAppNavigation";
import AppLayout from "@/layouts/AppLayout.vue";
import { useTranslations } from "@/lib/translations";
import type { ManagedApprovalRule, RoleOption } from "@/types/cumpu";

type CapabilityOption = {
  source_key: string;
  app_key: string;
  app_label: string;
  resource_key: string;
  resource_label: string;
  action_key: string;
  action_label: string;
  permission_name: string;
};

type FilterOption = {
  value: string;
  label: string;
};

const props = defineProps<{
  rules: ManagedApprovalRule[];
  capabilityOptions: CapabilityOption[];
  roles: RoleOption[];
  abilities: { manage: boolean };
}>();

const { __ } = useTranslations();
const { cumpuApprovalRulesBreadcrumbs } = useAppNavigation();

const editDialogOpen = ref(false);
const editingRuleSourceKey = ref<string | null>(null);

const editingRule = computed<ManagedApprovalRule | null>(
  () =>
    props.rules.find(
      (rule) => rule.source_key === editingRuleSourceKey.value
    ) ?? null
);

const filters = ref<ApprovalRuleFilters>({
  search: "",
  app: "",
  resource: "",
  action: "",
});

function buildFilterOptions(
  rules: ManagedApprovalRule[],
  getValue: (rule: ManagedApprovalRule) => string,
  getLabel: (rule: ManagedApprovalRule) => string
): FilterOption[] {
  const options = new Map<string, string>();

  for (const rule of rules) {
    const value = getValue(rule);

    if (!options.has(value)) {
      options.set(value, getLabel(rule));
    }
  }

  return Array.from(options.entries())
    .map(([value, label]) => ({ value, label }))
    .sort((left, right) => left.label.localeCompare(right.label));
}

const appOptions = computed(() =>
  buildFilterOptions(
    props.rules,
    (rule) => rule.app_key,
    (rule) => rule.app_label
  )
);

const appScopedRules = computed(() =>
  filters.value.app === ""
    ? props.rules
    : props.rules.filter((rule) => rule.app_key === filters.value.app)
);

const resourceOptions = computed(() =>
  buildFilterOptions(
    appScopedRules.value,
    (rule) => rule.resource_key,
    (rule) => rule.resource_label
  )
);

const resourceScopedRules = computed(() =>
  filters.value.resource === ""
    ? appScopedRules.value
    : appScopedRules.value.filter(
        (rule) => rule.resource_key === filters.value.resource
      )
);

const actionOptions = computed(() =>
  buildFilterOptions(
    resourceScopedRules.value,
    (rule) => rule.action_key,
    (rule) => rule.action_label
  )
);

const hasActiveFilters = computed(
  () =>
    filters.value.search.trim() !== "" ||
    filters.value.app !== "" ||
    filters.value.resource !== "" ||
    filters.value.action !== ""
);

const filteredRules = computed(() => {
  const { search, app, resource, action } = filters.value;
  const q = search.trim().toLowerCase();

  return props.rules.filter((rule) => {
    if (app !== "" && rule.app_key !== app) {
      return false;
    }

    if (resource !== "" && rule.resource_key !== resource) {
      return false;
    }

    if (action !== "" && rule.action_key !== action) {
      return false;
    }

    if (q === "") {
      return true;
    }

    return [
      rule.action_label,
      rule.resource_label,
      rule.app_label,
      rule.permission_name,
    ]
      .join(" ")
      .toLowerCase()
      .includes(q);
  });
});

watch(resourceOptions, (options) => {
  if (
    filters.value.resource !== "" &&
    !options.some((option) => option.value === filters.value.resource)
  ) {
    filters.value = { ...filters.value, resource: "", action: "" };
  }
});

watch(actionOptions, (options) => {
  if (
    filters.value.action !== "" &&
    !options.some((option) => option.value === filters.value.action)
  ) {
    filters.value = { ...filters.value, action: "" };
  }
});

function openEditDialog(rule: ManagedApprovalRule) {
  editingRuleSourceKey.value = rule.source_key;
  editDialogOpen.value = true;
}

function handleEditDialogClose(value: boolean) {
  editDialogOpen.value = value;

  if (!value) {
    editingRuleSourceKey.value = null;
  }
}
</script>

<template>
  <Head :title="__('Approval rules')" />

  <AppLayout :breadcrumbs="cumpuApprovalRulesBreadcrumbs">
    <div class="flex flex-col gap-5 p-4 md:gap-6">
      <!-- Header -->
      <div class="space-y-1">
        <h1 class="text-xl font-semibold tracking-tight text-foreground">
          {{ __("Approval rules") }}
        </h1>
        <p class="text-sm text-muted-foreground">
          {{
            __(
              "Capabilities and approval modes are defined in config. Sync them here, then manage reviewers, workflow, and timing settings from the UI."
            )
          }}
        </p>
      </div>

      <!-- Sync status card -->
      <ApprovalRuleSyncStatusCard
        :rules="props.rules"
        :can-manage="props.abilities.manage"
      />

      <!-- Filter bar -->
      <ApprovalRuleFilterBar
        v-model="filters"
        :app-options="appOptions"
        :resource-options="resourceOptions"
        :action-options="actionOptions"
      />

      <!-- Capability table -->
      <ApprovalRuleCapabilityTable
        :rules="filteredRules"
        :can-manage="props.abilities.manage"
        :is-filtered="hasActiveFilters"
        @edit-rule="openEditDialog"
      />
    </div>
  </AppLayout>

  <!-- Managed workflow edit dialog -->
  <ApprovalRuleManagedEditDialog
    :open="editDialogOpen"
    :rule="editingRule"
    :roles="props.roles"
    @update:open="handleEditDialogClose"
  />
</template>
