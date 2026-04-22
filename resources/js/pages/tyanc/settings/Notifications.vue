<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { BellRing, Clock, ExternalLink, Mail, Radio } from "lucide-vue-next";
import { computed, ref, watch } from "vue";
import ApprovalHistoryPanel from "@/components/cumpu/approvals/ApprovalHistoryPanel.vue";
import ApprovalReasonDialog from "@/components/cumpu/approvals/ApprovalReasonDialog.vue";
import ApprovalRequestBanner from "@/components/cumpu/approvals/ApprovalRequestBanner.vue";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import SettingsFormFooter from "@/components/tyanc/settings/SettingsFormFooter.vue";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { useAppNavigation } from "@/composables/useAppNavigation";
import AppLayout from "@/layouts/AppLayout.vue";
import TyancSettingsLayout from "@/layouts/tyanc/settings/Layout.vue";
import { useTranslations } from "@/lib/translations";
import {
  edit,
  test as triggerTestRoute,
  update,
} from "@/routes/tyanc/settings/notifications";
import type { ApprovalContext, GovernedActionState } from "@/types/cumpu";

type ChannelKey = "sonner" | "email" | "reverb";

type Settings = {
  sonner_enabled: boolean;
  email_enabled: boolean;
  reverb_enabled: boolean;
};

type Props = {
  settings: Settings;
  approvalContext?: ApprovalContext | null;
};

const props = defineProps<Props>();

const { tyancSettingsBreadcrumbs } = useAppNavigation();
const breadcrumbItems = computed(() =>
  tyancSettingsBreadcrumbs("Notification Settings", edit())
);
const { __ } = useTranslations();

const sonnerEnabled = ref(props.settings.sonner_enabled);
const emailEnabled = ref(props.settings.email_enabled);
const reverbEnabled = ref(props.settings.reverb_enabled);
const errors = ref<Partial<Record<string, string>>>({});
const testErrors = ref<Partial<Record<ChannelKey, string>>>({});
const processing = ref(false);
const recentlySuccessful = ref(false);
const testingChannel = ref<ChannelKey | null>(null);
const lastTriggeredChannel = ref<ChannelKey | null>(null);

const hasUnsavedChanges = computed(
  () =>
    sonnerEnabled.value !== props.settings.sonner_enabled ||
    emailEnabled.value !== props.settings.email_enabled ||
    reverbEnabled.value !== props.settings.reverb_enabled
);

const channelCards = computed(() => [
  {
    key: "sonner" as const,
    title: __("Sonner"),
    description: __(
      "Controls shared toast feedback for form saves, errors, and flash messages."
    ),
    testLabel: __("Trigger test toast"),
    hint: __("This test uses the same shared toaster mounted across the app."),
    icon: BellRing,
    enabled: sonnerEnabled.value,
    error: errors.value.sonner_enabled,
    testError: testErrors.value.sonner,
  },
  {
    key: "email" as const,
    title: __("Email notifications"),
    description: __(
      "Allows the platform to send notification emails to the signed-in user."
    ),
    testLabel: __("Send test email"),
    hint: __("The test email is sent to your current account address."),
    icon: Mail,
    enabled: emailEnabled.value,
    error: errors.value.email_enabled,
    testError: testErrors.value.email,
  },
  {
    key: "reverb" as const,
    title: __("Reverb live notifications"),
    description: __(
      "Broadcasts real-time notifications into the notifications menu without a page refresh."
    ),
    testLabel: __("Send live test notification"),
    hint: __(
      "The test notification should appear in the notifications menu immediately."
    ),
    icon: Radio,
    enabled: reverbEnabled.value,
    error: errors.value.reverb_enabled,
    testError: testErrors.value.reverb,
  },
]);

const approvalDialogOpen = ref(false);
const approvalNote = ref("");

const updateActionState = computed<GovernedActionState | undefined>(
  () => props.approvalContext?.governed_actions?.["update"]
);

const updateNeedsApprovalDialog = computed<boolean>(() => {
  const state = updateActionState.value;

  if (!state) {
    return false;
  }

  return (
    state.approval_enabled &&
    !state.bypasses_for_actor &&
    !state.has_usable_grant
  );
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

watch(
  () => [sonnerEnabled.value, emailEnabled.value, reverbEnabled.value],
  () => {
    testErrors.value = {};
    lastTriggeredChannel.value = null;
  }
);

function channelEnabled(channel: ChannelKey): boolean {
  switch (channel) {
    case "email":
      return emailEnabled.value;
    case "reverb":
      return reverbEnabled.value;
    case "sonner":
    default:
      return sonnerEnabled.value;
  }
}

function setChannelEnabled(channel: ChannelKey, value: boolean): void {
  testErrors.value = {
    ...testErrors.value,
    [channel]: undefined,
  };

  switch (channel) {
    case "email":
      emailEnabled.value = value;
      return;
    case "reverb":
      reverbEnabled.value = value;
      return;
    case "sonner":
    default:
      sonnerEnabled.value = value;
  }
}

function handleSubmit(): void {
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

function onApprovalConfirm(): void {
  approvalDialogOpen.value = false;
  doSubmit(approvalNote.value);
}

function doSubmit(note: string): void {
  processing.value = true;
  errors.value = {};
  testErrors.value = {};
  recentlySuccessful.value = false;

  router.patch(
    update.url(),
    {
      sonner_enabled: sonnerEnabled.value,
      email_enabled: emailEnabled.value,
      reverb_enabled: reverbEnabled.value,
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

function triggerTest(channel: ChannelKey): void {
  if (
    testingChannel.value ||
    !channelEnabled(channel) ||
    hasUnsavedChanges.value
  ) {
    return;
  }

  testingChannel.value = channel;
  lastTriggeredChannel.value = null;
  testErrors.value = {
    ...testErrors.value,
    [channel]: undefined,
  };

  router.post(
    triggerTestRoute.url(),
    { channel },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        lastTriggeredChannel.value = channel;

        setTimeout(() => {
          if (lastTriggeredChannel.value === channel) {
            lastTriggeredChannel.value = null;
          }
        }, 4000);
      },
      onError: (responseErrors) => {
        testErrors.value = {
          ...testErrors.value,
          [channel]:
            typeof responseErrors.channel === "string"
              ? responseErrors.channel
              : __("Unable to send the test notification."),
        };
      },
      onFinish: () => {
        testingChannel.value = null;
      },
    }
  );
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head :title="__('Notification settings')" />

    <h1 class="sr-only">{{ __("Notification settings") }}</h1>

    <TyancSettingsLayout>
      <ApprovalRequestBanner
        v-if="props.approvalContext"
        :context="props.approvalContext"
      />

      <form class="space-y-6" @submit.prevent="handleSubmit">
        <div class="space-y-4">
          <Heading
            variant="small"
            :title="__('Channels')"
            :description="
              __(
                'Enable or disable notification delivery channels and verify each one with a test action.'
              )
            "
          />

          <div class="grid gap-4">
            <Card v-for="channel in channelCards" :key="channel.key">
              <CardHeader class="gap-3">
                <div class="flex items-start gap-3">
                  <div
                    class="flex size-10 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                  >
                    <component :is="channel.icon" class="size-5" />
                  </div>

                  <div class="min-w-0 flex-1 space-y-1">
                    <CardTitle class="text-base">
                      {{ channel.title }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                      {{ channel.description }}
                    </p>
                  </div>

                  <div class="flex items-center gap-3">
                    <Label
                      :for="`notification-channel-${channel.key}`"
                      class="text-sm font-medium"
                    >
                      {{ channel.enabled ? __("Enabled") : __("Disabled") }}
                    </Label>
                    <Checkbox
                      :id="`notification-channel-${channel.key}`"
                      :checked="channel.enabled"
                      @update:checked="
                        setChannelEnabled(channel.key, Boolean($event))
                      "
                    />
                  </div>
                </div>
              </CardHeader>

              <CardContent class="pt-0">
                <p class="text-xs text-muted-foreground">
                  {{ channel.hint }}
                </p>
                <InputError
                  :message="channel.error ?? channel.testError"
                  class="mt-2"
                />
              </CardContent>

              <CardFooter class="flex items-center justify-between gap-3">
                <p
                  v-if="lastTriggeredChannel === channel.key"
                  class="text-xs text-muted-foreground"
                >
                  {{ __("Test sent just now.") }}
                </p>
                <span v-else class="text-xs text-muted-foreground">
                  {{
                    hasUnsavedChanges
                      ? __("Save changes before running tests.")
                      : channel.enabled
                        ? __("Ready to test.")
                        : __("Enable this channel to run its test.")
                  }}
                </span>

                <Button
                  type="button"
                  variant="outline"
                  :disabled="
                    processing ||
                    testingChannel !== null ||
                    !channel.enabled ||
                    hasUnsavedChanges
                  "
                  @click="triggerTest(channel.key)"
                >
                  {{
                    testingChannel === channel.key
                      ? __("Sending…")
                      : channel.testLabel
                  }}
                </Button>
              </CardFooter>
            </Card>
          </div>
        </div>

        <Separator />

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
    :title="__('Save notification settings')"
    :description="
      __(
        'This action requires approval. Explain why these notification changes should be approved.'
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
