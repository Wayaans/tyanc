<script setup lang="ts">
import { Head, usePage } from "@inertiajs/vue3";
import { MessageSquare, PanelLeft } from "lucide-vue-next";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import ConversationList from "@/components/tyanc/messages/ConversationList.vue";
import MessageThread from "@/components/tyanc/messages/MessageThread.vue";
import NewConversationDialog from "@/components/tyanc/messages/NewConversationDialog.vue";
import { Button } from "@/components/ui/button";
import { useAppNavigation } from "@/composables/useAppNavigation";
import { useMessagesPolling } from "@/composables/useMessagesPolling";
import AppLayout from "@/layouts/AppLayout.vue";
import { conversationChannelName, currentSocketId, getEcho } from "@/lib/echo";
import { jsonRequestHeaders } from "@/lib/http";
import { notify } from "@/lib/notify";
import { useTranslations } from "@/lib/translations";
import {
  archive as messagesArchive,
  destroy as messagesDestroy,
  index as messagesRoute,
  store as messagesStore,
} from "@/routes/tyanc/messages";
import type {
  ConversationRow,
  MessageRow,
  MessageSentEventPayload,
  MessagesPageProps,
} from "@/types";

const props = defineProps<MessagesPageProps>();

const page = usePage();
const { __ } = useTranslations();
const { messagesBreadcrumbs } = useAppNavigation();

const conversations = ref<ConversationRow[]>(props.conversations);
const selectedConversation = ref<ConversationRow | null>(
  props.selectedConversation
);
const selectedConversationId = ref<string | null>(props.selectedConversationId);
const unreadCount = ref(props.unreadCount);
const contacts = ref(props.contacts ?? []);
const createConversationAbility = ref<boolean | null>(
  props.abilities?.createConversation ?? null
);
const archiveConversationAbility = ref<boolean | null>(
  props.abilities?.archiveConversation ?? null
);
const deleteConversationAbility = ref<boolean | null>(
  props.abilities?.deleteConversation ?? null
);
const viewMode = ref<"active" | "archived">(props.viewMode ?? "active");
const archivedConversationCount = ref<number>(
  props.archivedConversationCount ?? 0
);
const loadingConversation = ref(false);
const silentConversationRefreshInFlight = ref(false);
const sending = ref(false);
const composeError = ref<string | null>(null);
const listOpen = ref(props.selectedConversationId === null);
const newConversationOpen = ref(false);
const typingUsers = ref<Record<string, string>>({});

const canCreateConversation = computed(
  () => createConversationAbility.value ?? false
);
const canArchiveConversation = computed(
  () => archiveConversationAbility.value ?? false
);
const canDeleteConversation = computed(
  () => deleteConversationAbility.value ?? false
);

const authUser = computed(
  () =>
    (page.props.auth as { user?: { id: string; name: string } } | undefined)
      ?.user
);
const authUserId = computed(() => authUser.value?.id ?? "");
const typingNames = computed(() => Object.values(typingUsers.value));

watch(
  () => props.conversations,
  (value) => {
    conversations.value = value;
  }
);

watch(
  () => props.selectedConversation,
  (value) => {
    selectedConversation.value = value;
  }
);

watch(
  () => props.selectedConversationId,
  (value) => {
    selectedConversationId.value = value;
  }
);

watch(
  () => props.unreadCount,
  (value) => {
    unreadCount.value = value;
  }
);

watch(
  () => props.contacts,
  (value) => {
    contacts.value = value ?? [];
  }
);

watch(
  () => props.abilities,
  (value) => {
    createConversationAbility.value = value?.createConversation ?? null;
    archiveConversationAbility.value = value?.archiveConversation ?? null;
    deleteConversationAbility.value = value?.deleteConversation ?? null;
  }
);

watch(
  () => props.viewMode,
  (value) => {
    viewMode.value = value ?? "active";
  }
);

watch(
  () => props.archivedConversationCount,
  (value) => {
    archivedConversationCount.value = value ?? 0;
  }
);

watch(
  unreadCount,
  () => {
    syncUnreadCountToShell();
  },
  { immediate: true }
);

watch(
  conversations,
  () => {
    syncUnreadCountToShell();
  },
  { deep: true, immediate: true }
);

const hasSelectedConversation = computed(
  () =>
    selectedConversationId.value !== null && selectedConversation.value !== null
);

function requestHeaders(): HeadersInit {
  return jsonRequestHeaders(
    currentSocketId() ? { "X-Socket-ID": currentSocketId() ?? "" } : {}
  );
}

function buildWorkspaceUrl(opts?: {
  conversationId?: string | null;
  overrideView?: "active" | "archived";
}): string {
  const query: Record<string, string> = {};
  const mode = opts?.overrideView ?? viewMode.value;
  const cid =
    opts?.conversationId !== undefined
      ? opts.conversationId
      : selectedConversationId.value;

  if (mode !== "active") {
    query.view = mode;
  }

  if (cid) {
    query.conversation = cid;
  }

  return messagesRoute.url(
    Object.keys(query).length > 0 ? { query } : undefined
  );
}

async function refreshWorkspaceState() {
  const response = await fetch(buildWorkspaceUrl(), {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  });

  if (!response.ok) {
    throw new Error("Unable to refresh messages workspace.");
  }

  const payload = (await response.json()) as MessagesPageProps;
  mergeWorkspace(payload);
}

async function openNewConversationDialog() {
  if (createConversationAbility.value === null || contacts.value.length === 0) {
    try {
      await refreshWorkspaceState();
    } catch {
      notify.error(__("Unable to load the messaging workspace."));
      return;
    }
  }

  newConversationOpen.value = true;
}

function syncUnreadCountToShell() {
  if (typeof window === "undefined" || viewMode.value === "archived") {
    return;
  }

  window.dispatchEvent(
    new CustomEvent("messages-unread-count:update", {
      detail: {
        unreadCount: unreadCount.value,
        recent: conversations.value.slice(0, 6),
      },
    })
  );
}

onMounted(() => {
  if (
    createConversationAbility.value === null ||
    props.contacts === undefined
  ) {
    void refreshWorkspaceState();
  }
});

// Poll for new messages as a fallback safety net. This keeps the thread fresh
// in local dev without Reverb and also recovers if a websocket connection goes
// stale while the page stays open.
useMessagesPolling({
  refreshFn: async () => {
    if (
      sending.value ||
      loadingConversation.value ||
      silentConversationRefreshInFlight.value ||
      newConversationOpen.value
    ) {
      return;
    }

    await refreshWorkspaceState();
  },
});

function mergeWorkspace(payload: MessagesPageProps) {
  conversations.value = payload.conversations;
  selectedConversation.value = payload.selectedConversation;
  selectedConversationId.value = payload.selectedConversationId;
  unreadCount.value = payload.unreadCount;
  contacts.value = payload.contacts ?? contacts.value;
  createConversationAbility.value =
    payload.abilities?.createConversation ?? createConversationAbility.value;
  archiveConversationAbility.value =
    payload.abilities?.archiveConversation ?? archiveConversationAbility.value;
  deleteConversationAbility.value =
    payload.abilities?.deleteConversation ?? deleteConversationAbility.value;
  viewMode.value = payload.viewMode ?? viewMode.value;
  archivedConversationCount.value =
    payload.archivedConversationCount ?? archivedConversationCount.value;
  composeError.value = null;
  syncUnreadCountToShell();
}

async function loadConversation(
  conversationId: string,
  replaceHistory = true,
  // When true the function refreshes server state without touching
  // loadingConversation, so MessageThread never flips into the skeleton
  // state and the scroll position is preserved.
  silent = false
) {
  if (
    loadingConversation.value ||
    (silent && silentConversationRefreshInFlight.value)
  ) {
    return;
  }

  if (silent) {
    silentConversationRefreshInFlight.value = true;
  } else {
    loadingConversation.value = true;
  }

  try {
    const url = buildWorkspaceUrl({ conversationId });
    const response = await fetch(url, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    if (!response.ok) {
      throw new Error("Unable to load conversation.");
    }

    const payload = (await response.json()) as MessagesPageProps;
    mergeWorkspace(payload);

    if (replaceHistory && typeof window !== "undefined") {
      window.history.replaceState(window.history.state, "", url);
    }

    if (!silent) {
      listOpen.value = false;
    }
  } catch {
    if (!silent) {
      notify.error(__("Unable to load the selected conversation."));
    }
  } finally {
    if (silent) {
      silentConversationRefreshInFlight.value = false;
    } else {
      loadingConversation.value = false;
    }
  }
}

async function selectConversation(conversationId: string) {
  if (conversationId === selectedConversationId.value) {
    listOpen.value = false;
    return;
  }

  await loadConversation(conversationId);
}

function updateConversationSummary(
  conversationId: string,
  message: MessageRow,
  incrementUnread: boolean
) {
  const next = conversations.value.map((conversation) => {
    if (conversation.id !== conversationId) {
      return conversation;
    }

    return {
      ...conversation,
      last_message_preview: message.body,
      last_message_at: message.created_at,
      last_sender_name: message.sender_name,
      unread_count: incrementUnread
        ? conversation.unread_count + 1
        : conversation.unread_count,
      message_count: conversation.message_count + 1,
    } satisfies ConversationRow;
  });

  conversations.value = next.sort((left, right) => {
    const leftTimestamp = left.last_message_at
      ? new Date(left.last_message_at).getTime()
      : 0;
    const rightTimestamp = right.last_message_at
      ? new Date(right.last_message_at).getTime()
      : 0;

    return rightTimestamp - leftTimestamp;
  });
}

async function sendMessage(body: string) {
  if (selectedConversationId.value === null || sending.value) {
    return;
  }

  const optimisticMessage: MessageRow = {
    id: `temp-${Date.now()}`,
    conversation_id: selectedConversationId.value,
    sender_id: authUserId.value,
    sender_name: authUser.value?.name ?? __("You"),
    sender_avatar: null,
    body,
    is_mine: true,
    created_at: new Date().toISOString(),
  };
  const previousConversations = conversations.value.map((conversation) => ({
    ...conversation,
    participants: [...conversation.participants],
    messages: [...conversation.messages],
  }));
  const previousSelectedConversation = selectedConversation.value
    ? {
        ...selectedConversation.value,
        participants: [...selectedConversation.value.participants],
        messages: [...selectedConversation.value.messages],
      }
    : null;

  sending.value = true;
  composeError.value = null;

  if (selectedConversation.value) {
    selectedConversation.value = {
      ...selectedConversation.value,
      messages: [...selectedConversation.value.messages, optimisticMessage],
    };
  }

  updateConversationSummary(
    selectedConversationId.value,
    optimisticMessage,
    false
  );

  try {
    const response = await fetch(
      messagesStore.url({ conversation: selectedConversationId.value }),
      {
        method: "POST",
        headers: requestHeaders(),
        body: JSON.stringify({ body }),
      }
    );

    if (!response.ok) {
      const payload = (await response.json().catch(() => null)) as {
        errors?: Record<string, string[]>;
        message?: string;
      } | null;

      composeError.value =
        response.status === 422
          ? (payload?.errors?.body?.[0] ??
            payload?.message ??
            __("Unable to send your message right now."))
          : (payload?.message ?? __("Unable to send your message right now."));

      throw new Error("Unable to send message.");
    }

    const payload = (await response.json()) as MessagesPageProps & {
      message: MessageRow;
    };

    mergeWorkspace(payload);
  } catch {
    conversations.value = previousConversations;
    selectedConversation.value = previousSelectedConversation;
    composeError.value ??= __("Unable to send your message right now.");
    syncUnreadCountToShell();
  } finally {
    sending.value = false;
  }
}

type TypingWhisperPayload = {
  userId: string;
  name: string;
};

type EchoConversationChannel = {
  listen: (
    event: string,
    callback: (payload: MessageSentEventPayload) => void
  ) => EchoConversationChannel;
  listenForWhisper: (
    event: string,
    callback: (payload: TypingWhisperPayload) => void
  ) => EchoConversationChannel;
  whisper: (event: string, payload: TypingWhisperPayload) => void;
};

const typingTimers = new Map<string, ReturnType<typeof setTimeout>>();
const channelSubscriptions = new Map<string, EchoConversationChannel>();
let whisperDebounce: ReturnType<typeof setTimeout> | null = null;

function clearTypingUser(userId: string) {
  const next = { ...typingUsers.value };
  delete next[userId];
  typingUsers.value = next;
}

function handleIncomingMessage(payload: MessageSentEventPayload) {
  const normalizedMessage: MessageRow = {
    ...payload.message,
    is_mine: payload.message.sender_id === authUserId.value,
  };
  const isSelected =
    normalizedMessage.conversation_id === selectedConversationId.value;
  const isOwnMessage = normalizedMessage.sender_id === authUserId.value;

  updateConversationSummary(
    normalizedMessage.conversation_id,
    normalizedMessage,
    !isSelected && !isOwnMessage
  );

  if (!isSelected) {
    if (!isOwnMessage) {
      unreadCount.value += 1;
      syncUnreadCountToShell();
    }

    return;
  }

  if (selectedConversation.value === null) {
    return;
  }

  const exists = selectedConversation.value.messages.some(
    (message) => message.id === normalizedMessage.id
  );

  if (!exists) {
    selectedConversation.value = {
      ...selectedConversation.value,
      unread_count: 0,
      messages: [...selectedConversation.value.messages, normalizedMessage],
    };
    syncUnreadCountToShell();
  }

  if (!isOwnMessage) {
    // Silent=true: we already appended the message optimistically above.
    // A full refresh is still needed to sync server state (read receipts,
    // confirmed IDs, etc.), but we must not flip loadingConversation because
    // that would show the skeleton, collapse scrollHeight, and reset the
    // user's scroll position to the top.
    void loadConversation(normalizedMessage.conversation_id, false, true);
  }
}

function syncChannelSubscriptions() {
  const echo = getEcho();

  if (echo === null) {
    return;
  }

  const activeConversationIds = new Set(
    conversations.value.map((conversation) => conversation.id)
  );

  for (const [conversationId] of channelSubscriptions) {
    if (activeConversationIds.has(conversationId)) {
      continue;
    }

    echo.leave(conversationChannelName(conversationId));
    channelSubscriptions.delete(conversationId);
  }

  for (const conversationId of activeConversationIds) {
    if (channelSubscriptions.has(conversationId)) {
      continue;
    }

    const channel = echo.private(conversationChannelName(conversationId));

    channel.listen(".message.sent", (payload: MessageSentEventPayload) => {
      handleIncomingMessage(payload);
    });

    channel.listenForWhisper("typing", (payload: TypingWhisperPayload) => {
      if (conversationId !== selectedConversationId.value) {
        return;
      }

      if (payload.userId === authUserId.value) {
        return;
      }

      typingUsers.value = {
        ...typingUsers.value,
        [payload.userId]: payload.name,
      };

      const existingTimer = typingTimers.get(payload.userId);

      if (existingTimer) {
        clearTimeout(existingTimer);
      }

      typingTimers.set(
        payload.userId,
        setTimeout(() => {
          clearTypingUser(payload.userId);
          typingTimers.delete(payload.userId);
        }, 3000)
      );
    });

    channelSubscriptions.set(conversationId, channel);
  }
}

watch(conversations, syncChannelSubscriptions, { deep: true, immediate: true });

function handleTyping() {
  composeError.value = null;

  if (selectedConversationId.value === null || whisperDebounce !== null) {
    return;
  }

  const channel = channelSubscriptions.get(selectedConversationId.value);

  if (!channel) {
    return;
  }

  channel.whisper("typing", {
    userId: authUserId.value,
    name: authUser.value?.name ?? __("You"),
  });

  whisperDebounce = setTimeout(() => {
    whisperDebounce = null;
  }, 1500);
}

async function toggleViewMode() {
  const previousViewMode = viewMode.value;
  const previousSelectedConversationId = selectedConversationId.value;
  const previousSelectedConversation = selectedConversation.value
    ? {
        ...selectedConversation.value,
        participants: [...selectedConversation.value.participants],
        messages: [...selectedConversation.value.messages],
      }
    : null;
  const previousListOpen = listOpen.value;
  const newMode: "active" | "archived" =
    viewMode.value === "active" ? "archived" : "active";

  selectedConversationId.value = null;
  selectedConversation.value = null;
  viewMode.value = newMode;
  listOpen.value = true;

  try {
    const url = buildWorkspaceUrl({
      conversationId: null,
      overrideView: newMode,
    });

    const response = await fetch(url, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    if (!response.ok) {
      throw new Error("Unable to switch view.");
    }

    const payload = (await response.json()) as MessagesPageProps;
    mergeWorkspace(payload);

    if (typeof window !== "undefined") {
      window.history.replaceState(window.history.state, "", url);
    }
  } catch {
    notify.error(__("Unable to switch conversation view."));
    viewMode.value = previousViewMode;
    selectedConversationId.value = previousSelectedConversationId;
    selectedConversation.value = previousSelectedConversation;
    listOpen.value = previousListOpen;
  }
}

async function archiveConversation(conversationId: string) {
  const archiving = viewMode.value === "active";

  try {
    const response = await fetch(
      messagesArchive.url(
        { conversation: conversationId },
        viewMode.value === "archived"
          ? { query: { view: "archived" } }
          : undefined
      ),
      {
        method: "PATCH",
        headers: requestHeaders(),
        body: JSON.stringify({ archived: archiving }),
      }
    );

    if (!response.ok) {
      throw new Error("Unable to archive conversation.");
    }

    const payload = (await response.json()) as MessagesPageProps;
    mergeWorkspace(payload);
    listOpen.value = true;

    if (typeof window !== "undefined") {
      window.history.replaceState(
        window.history.state,
        "",
        buildWorkspaceUrl()
      );
    }

    notify.success(
      archiving ? __("Conversation archived.") : __("Conversation unarchived.")
    );
  } catch {
    notify.error(__("Unable to archive this conversation."));
  }
}

async function deleteConversation(conversationId: string) {
  try {
    const response = await fetch(
      messagesDestroy.url(
        { conversation: conversationId },
        viewMode.value === "archived"
          ? { query: { view: "archived" } }
          : undefined
      ),
      {
        method: "DELETE",
        headers: requestHeaders(),
      }
    );

    if (!response.ok) {
      throw new Error("Unable to delete conversation.");
    }

    const payload = (await response.json()) as MessagesPageProps;
    mergeWorkspace(payload);
    listOpen.value = true;

    if (typeof window !== "undefined") {
      window.history.replaceState(
        window.history.state,
        "",
        buildWorkspaceUrl()
      );
    }

    notify.success(__("Conversation deleted."));
  } catch {
    notify.error(__("Unable to delete this conversation."));
  }
}

onBeforeUnmount(() => {
  const echo = getEcho();

  if (echo) {
    for (const [conversationId] of channelSubscriptions) {
      echo.leave(conversationChannelName(conversationId));
    }
  }

  for (const timer of typingTimers.values()) {
    clearTimeout(timer);
  }

  if (whisperDebounce !== null) {
    clearTimeout(whisperDebounce);
  }
});
</script>

<template>
  <Head :title="__('Messages')" />

  <AppLayout :breadcrumbs="messagesBreadcrumbs">
    <div
      class="flex h-[calc(100svh-var(--app-sidebar-header-height,4rem))] min-h-0 flex-1 overflow-hidden"
    >
      <aside
        class="min-h-0 w-full shrink-0 border-b border-border bg-sidebar/30 sm:w-80 sm:border-r sm:border-b-0"
        :class="{
          'hidden sm:block': !listOpen && hasSelectedConversation,
          block: listOpen || !hasSelectedConversation,
        }"
      >
        <ConversationList
          :conversations="conversations"
          :selected-id="selectedConversationId"
          :can-create-conversation="canCreateConversation"
          :view-mode="viewMode"
          :archived-conversation-count="archivedConversationCount"
          @select="(id) => void selectConversation(id)"
          @new-conversation="openNewConversationDialog"
          @toggle-view="() => void toggleViewMode()"
        />
      </aside>

      <main class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
        <div
          v-if="hasSelectedConversation && !listOpen"
          class="flex shrink-0 items-center gap-2 border-b border-border px-4 py-2 sm:hidden"
        >
          <Button
            variant="ghost"
            size="sm"
            class="gap-1.5 text-xs"
            @click="listOpen = true"
          >
            <PanelLeft class="size-3.5" />
            {{ __("All conversations") }}
          </Button>
        </div>

        <div
          v-if="!hasSelectedConversation"
          class="flex min-h-0 flex-1 flex-col items-center justify-center gap-2 text-center"
        >
          <div
            class="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
          >
            <MessageSquare class="size-6" />
          </div>
          <p class="text-sm font-medium text-foreground">
            {{ __("Select a conversation") }}
          </p>
          <p class="max-w-sm text-xs text-muted-foreground">
            {{
              __(
                "Choose a conversation from the left to start reading and replying."
              )
            }}
          </p>
        </div>

        <MessageThread
          v-else-if="selectedConversation"
          :key="selectedConversation.id"
          :conversation="selectedConversation"
          :messages="selectedConversation.messages"
          :typing-names="typingNames"
          :sending="sending"
          :loading="loadingConversation"
          :compose-error="composeError"
          :can-archive="canArchiveConversation"
          :can-delete="canDeleteConversation"
          :is-archived="viewMode === 'archived'"
          @send="(body) => void sendMessage(body)"
          @typing="handleTyping"
          @archive="() => void archiveConversation(selectedConversation!.id)"
          @delete-conversation="
            () => void deleteConversation(selectedConversation!.id)
          "
        />
      </main>
    </div>

    <NewConversationDialog
      :open="newConversationOpen"
      :contacts="contacts"
      :can-create-conversation="canCreateConversation"
      @update:open="newConversationOpen = $event"
      @created="(id) => void loadConversation(id)"
    />
  </AppLayout>
</template>
