import { readonly, ref } from 'vue';
import type { ToastPayload } from '@/types';

const displayedFlashToastIds = ref<string[]>([]);

function hasDisplayedFlashToast(toast: ToastPayload): boolean {
    return displayedFlashToastIds.value.includes(toast.id);
}

function rememberFlashToast(toast: ToastPayload): void {
    if (hasDisplayedFlashToast(toast)) {
        return;
    }

    displayedFlashToastIds.value = [
        ...displayedFlashToastIds.value,
        toast.id,
    ].slice(-20);
}

export function useNotificationStore() {
    return {
        displayedFlashToastIds: readonly(displayedFlashToastIds),
        hasDisplayedFlashToast,
        rememberFlashToast,
    };
}
