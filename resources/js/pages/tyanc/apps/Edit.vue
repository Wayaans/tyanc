<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { Head } from "@inertiajs/vue3";
import { ArrowLeft, Clock, ExternalLink } from "lucide-vue-next";
import { computed, ref, watch } from "vue";
import ApprovalHistoryPanel from "@/components/cumpu/approvals/ApprovalHistoryPanel.vue";
import ApprovalReasonDialog from "@/components/cumpu/approvals/ApprovalReasonDialog.vue";
import ApprovalRequestBanner from "@/components/cumpu/approvals/ApprovalRequestBanner.vue";
import AppForm, {
  type AppFormFields,
  type AppPageForm,
} from "@/components/tyanc/apps/AppForm.vue";
import AppStatusBadge from "@/components/tyanc/apps/AppStatusBadge.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { Spinner } from "@/components/ui/spinner";
import { useAppNavigation } from "@/composables/useAppNavigation";
import AppLayout from "@/layouts/AppLayout.vue";
import { useTranslations } from "@/lib/translations";
import { index, update } from "@/routes/tyanc/apps";
import type { AppData } from "@/types";
import type { ApprovalContext, GovernedActionState } from "@/types/cumpu";

const props = defineProps<{
  app: AppData;
  approvalContext?: ApprovalContext | null;
}>();

const { __ } = useTranslations();
const { appsEditBreadcrumbs } = useAppNavigation();

const breadcrumbs = appsEditBreadcrumbs(props.app.label, props.app.key);

function fromAppData(app: AppData): AppFormFields {
  return {
    key: app.key,
    label: app.label,
    route_prefix: app.route_prefix,
    icon: app.icon,
    permission_namespace: app.permission_namespace,
    enabled: app.enabled,
    sort_order: app.sort_order,
    pages: app.pages.map(
      (page): AppPageForm => ({
        key: page.key,
        label: page.label,
        route_name: page.route_name ?? "",
        path: page.path ?? "",
        permission_name: page.permission_name ?? "",
        sort_order: page.sort_order,
        enabled: page.enabled,
        is_navigation: page.is_navigation,
        is_system: page.is_system,
      })
    ),
  };
}

const form = ref<AppFormFields>(fromAppData(props.app));
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);

// ── Approval dialog state ─────────────────────────────────────────────────────

const approvalDialogOpen = ref(false);
const approvalNote = ref("");

const updateActionState = computed<GovernedActionState | undefined>(
  () => props.approvalContext?.governed_actions?.["update"]
);

const updateNeedsApprovalDialog = computed<boolean>(() => {
  const s = updateActionState.value;
  if (!s) return false;
  return s.approval_enabled && !s.bypasses_for_actor && !s.has_usable_grant;
});

const updateBlockedByRequest = computed(() =>
  updateActionState.value?.has_blocking_request
    ? updateActionState.value.relevant_request
    : null
);

const submissionBlockedVisible = ref(false);

watch(approvalDialogOpen, (isOpen) => {
  if (!isOpen) {
    approvalNote.value = "";
    errors.value = {
      ...errors.value,
      request_note: undefined,
      approval: undefined,
    };
  }
});

// ── Navigation ────────────────────────────────────────────────────────────────

function goBack() {
  router.visit(index.url());
}

// ── Submit flow ───────────────────────────────────────────────────────────────

function handleSubmit() {
  submissionBlockedVisible.value = false;

  if (updateNeedsApprovalDialog.value) {
    if (updateBlockedByRequest.value) {
      submissionBlockedVisible.value = true;
      return;
    }
    approvalDialogOpen.value = true;
    return;
  }

  doSubmit("");
}

function onApprovalConfirm() {
  approvalDialogOpen.value = false;
  doSubmit(approvalNote.value);
}

function doSubmit(note: string) {
  processing.value = true;
  errors.value = {};

  router.patch(
    update.url({ app: props.app.key }),
    { ...form.value, request_note: note || undefined },
    {
      preserveScroll: true,
      onError: (responseErrors) => {
        errors.value = responseErrors as Partial<Record<string, string>>;
        if (responseErrors.request_note || responseErrors.approval) {
          approvalNote.value = note;
          approvalDialogOpen.value = true;
        }
      },
      onFinish: () => {
        processing.value = false;
      },
    }
  );
}
</script>

<template>
  <Head :title="__('Edit :label', { label: props.app.label })" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
      <!-- Page header -->
      <div class="flex items-center gap-4">
        <Button
          variant="ghost"
          size="icon"
          class="size-8 shrink-0"
          :aria-label="__('Back to apps')"
          @click="goBack"
        >
          <ArrowLeft class="size-4" />
        </Button>
        <div class="flex min-w-0 flex-1 items-center gap-3">
          <div class="min-w-0 space-y-0.5">
            <div class="flex items-center gap-2">
              <h1
                class="truncate text-xl font-semibold tracking-tight text-foreground"
              >
                {{
                  __("Edit :label", {
                    label: props.app.label,
                  })
                }}
              </h1>
              <Badge
                v-if="props.app.is_system"
                variant="outline"
                class="rounded-full text-xs text-muted-foreground"
              >
                {{ __("Protected") }}
              </Badge>
            </div>
            <div class="flex items-center gap-2">
              <p class="truncate font-mono text-xs text-muted-foreground">
                {{ props.app.key }}
              </p>
              <AppStatusBadge
                :enabled="props.app.enabled"
                :is-system="props.app.is_system"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Approval banner -->
      <ApprovalRequestBanner
        v-if="props.approvalContext"
        :context="props.approvalContext"
      />

      <!-- Form card -->
      <div
        class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-background/90"
      >
        <form class="space-y-6 p-6 md:p-8" @submit.prevent="handleSubmit">
          <AppForm
            v-model="form"
            :errors="errors"
            :is-system="props.app.is_system"
          />

          <Separator />

          <!-- Blocked submission callout -->
          <div
            v-if="submissionBlockedVisible && updateBlockedByRequest"
            class="flex items-start gap-3 rounded-xl border border-amber-200/60 bg-amber-50/50 px-4 py-3 dark:border-amber-500/20 dark:bg-amber-500/[0.07]"
          >
            <Clock
              class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
            />
            <div class="min-w-0 flex-1 space-y-1">
              <p class="text-sm font-medium text-amber-900 dark:text-amber-200">
                {{
                  __("An approval request for this action is already pending.")
                }}
              </p>
              <p class="text-xs text-amber-700/80 dark:text-amber-300/80">
                {{
                  __(
                    "You cannot submit a new request until the existing one is resolved."
                  )
                }}
              </p>
            </div>
            <a
              v-if="updateBlockedByRequest.detail_url"
              :href="updateBlockedByRequest.detail_url"
              target="_blank"
              rel="noopener noreferrer"
              class="flex shrink-0 items-center gap-1 rounded-lg border border-amber-200/80 bg-white/60 px-2.5 py-1.5 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-100/60 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20"
            >
              {{ __("View request") }}
              <ExternalLink class="size-3" />
            </a>
          </div>

          <div class="flex items-center justify-end gap-3">
            <Button
              type="button"
              variant="outline"
              :disabled="processing"
              @click="goBack"
            >
              {{ __("Cancel") }}
            </Button>
            <Button type="submit" :disabled="processing">
              <Spinner v-if="processing" />
              {{ __("Save changes") }}
            </Button>
          </div>
        </form>
      </div>

      <!-- Approval history -->
      <ApprovalHistoryPanel
        v-if="props.approvalContext"
        :context="props.approvalContext"
      />
    </div>
  </AppLayout>

  <ApprovalReasonDialog
    v-model:open="approvalDialogOpen"
    v-model:note="approvalNote"
    :title="__('Save changes')"
    :description="
      __(
        'This action requires approval. Explain why these changes should be approved.'
      )
    "
    :action-label="__('Submit for approval')"
    :loading="processing"
    :error="errors.request_note ?? errors.approval"
    :relevant-request="updateActionState?.relevant_request ?? null"
    @confirm="onApprovalConfirm"
    @cancel="approvalNote = ''"
  />
</template>
