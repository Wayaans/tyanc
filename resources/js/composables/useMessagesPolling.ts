import {
    useDocumentVisibility,
    useIntervalFn,
    useWindowFocus,
} from '@vueuse/core';
import { watch } from 'vue';
import { isEchoConnected } from '@/lib/echo';

type UseMessagesPollingOptions = {
    /**
     * Called on every poll tick. Should refresh the messages workspace state.
     * Errors are swallowed silently so they never spam the user with toasts.
     */
    refreshFn: () => Promise<void>;

    /**
     * How often to poll in milliseconds when Reverb is unavailable.
     * Defaults to 5 seconds.
     */
    intervalMs?: number;
};

/**
 * Lightweight polling fallback for the messages page.
 *
 * Polls `refreshFn` every `intervalMs` while the tab is visible and focused,
 * but only when the realtime socket is not connected. Failures are swallowed
 * so a dead server never triggers repeated error toasts.
 */
export function useMessagesPolling({
    refreshFn,
    intervalMs = 5_000,
}: UseMessagesPollingOptions): void {
    const visibility = useDocumentVisibility();
    const isWindowFocused = useWindowFocus();

    async function tick(): Promise<void> {
        if (visibility.value !== 'visible' || !isWindowFocused.value) {
            return;
        }

        if (isEchoConnected()) {
            return;
        }

        try {
            await refreshFn();
        } catch {
            // Polling is best-effort. Never surface errors to the user.
        }
    }

    const { pause, resume } = useIntervalFn(
        () => {
            void tick();
        },
        intervalMs,
        { immediate: false, immediateCallback: false },
    );

    watch(
        [visibility, isWindowFocused],
        ([nextVisibility, nextWindowFocus]) => {
            if (nextVisibility === 'visible' && nextWindowFocus) {
                resume();
                void tick();

                return;
            }

            pause();
        },
        { immediate: true },
    );
}
