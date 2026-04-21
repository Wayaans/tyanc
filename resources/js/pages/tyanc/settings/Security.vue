<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { Head } from "@inertiajs/vue3";
import { Clock, ExternalLink } from "lucide-vue-next";
import { computed, ref, watch } from "vue";
import ApprovalHistoryPanel from "@/components/cumpu/approvals/ApprovalHistoryPanel.vue";
import ApprovalReasonDialog from "@/components/cumpu/approvals/ApprovalReasonDialog.vue";
import ApprovalRequestBanner from "@/components/cumpu/approvals/ApprovalRequestBanner.vue";
import FormFieldSupport from "@/components/FormFieldSupport.vue";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import SettingsFormFooter from "@/components/tyanc/settings/SettingsFormFooter.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { useAppNavigation } from "@/composables/useAppNavigation";
import AppLayout from "@/layouts/AppLayout.vue";
import TyancSettingsLayout from "@/layouts/tyanc/settings/Layout.vue";
import { useTranslations } from "@/lib/translations";
import { edit, update } from "@/routes/tyanc/settings/security";
import type { ApprovalContext, GovernedActionState } from "@/types/cumpu";

type Settings = {
  enforce_2fa: boolean;
  session_timeout: number;
};

type Props = {
  settings: Settings;
  approvalContext?: ApprovalContext | null;
};

const props = defineProps<Props>();

const { tyancSettingsBreadcrumbs } = useAppNavigation();
const breadcrumbItems = computed(() =>
  tyancSettingsBreadcrumbs("Security", edit())
);
const { __ } = useTranslations();

const enforce2fa = ref(props.settings.enforce_2fa);
const sessionTimeout = ref(props.settings.session_timeout);
const errors = ref<Partial<Record<string, string>>>({});
const processing = ref(false);
const recentlySuccessful = ref(false);

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
  recentlySuccessful.value = false;

  router.patch(
    update.url(),
    {
      enforce_2fa: enforce2fa.value,
      session_timeout: sessionTimeout.value,
      request_note: note || undefined,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        if (updateNeedsApprovalDialog.value) {
          recentlySuccessful.value = false;
          return;
        }

        recentlySuccessful.value = true;
        setTimeout(() => {
          recentlySuccessful.value = false;
        }, 2000);
      },
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
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head :title="__('Security settings')" />

    <h1 class="sr-only">{{ __("Security settings") }}</h1>

    <TyancSettingsLayout>
      <ApprovalRequestBanner
        v-if="props.approvalContext"
        :context="props.approvalContext"
      />

      <form class="space-y-6" @submit.prevent="handleSubmit">
        <!-- Authentication -->
        <div class="space-y-4">
          <Heading
            variant="small"
            :title="__('Authentication')"
            :description="
              __('Two-factor authentication enforcement for all users')
            "
          />

          <div class="space-y-3">
            <div class="flex items-start gap-3">
              <Checkbox
                id="enforce_2fa"
                :checked="enforce2fa"
                @update:checked="enforce2fa = Boolean($event)"
              />
              <div class="grid gap-1">
                <Label for="enforce_2fa" class="font-medium">
                  {{ __("Require two-factor authentication") }}
                </Label>
                <p class="text-sm text-muted-foreground">
                  {{
                    __(
                      "All users must set up 2FA before accessing the application. Users without 2FA will be redirected to the setup screen on login."
                    )
                  }}
                </p>
              </div>
            </div>
            <InputError :message="errors.enforce_2fa" />
          </div>
        </div>

        <Separator />

        <!-- Sessions -->
        <div class="space-y-4">
          <Heading
            variant="small"
            :title="__('Sessions')"
            :description="__('Idle session timeout configuration')"
          />

          <div class="grid max-w-xs gap-2">
            <Label for="session_timeout">
              {{ __("Session timeout (minutes)") }}
            </Label>
            <Input
              id="session_timeout"
              v-model.number="sessionTimeout"
              type="number"
              min="5"
              max="10080"
              step="5"
              placeholder="120"
            />
            <FormFieldSupport
              :hint="
                __(
                  'Users are logged out after this many minutes of inactivity. Min 5, max 10080 (1 week).'
                )
              "
              :error="errors.session_timeout"
            />
          </div>
        </div>

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

        <SettingsFormFooter
          :processing="processing"
          :recently-successful="recentlySuccessful"
        />
      </form>

      <ApprovalHistoryPanel
        v-if="props.approvalContext"
        :context="props.approvalContext"
      />
    </TyancSettingsLayout>
  </AppLayout>

  <ApprovalReasonDialog
    v-model:open="approvalDialogOpen"
    v-model:note="approvalNote"
    :title="__('Save security settings')"
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
