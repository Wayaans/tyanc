import { router } from "@inertiajs/vue3";
import { toast } from "vue-sonner";
import type { ToastPayload, ToastVariant } from "@/types";

type NotifyAction = {
  label: string;
  onClick: () => void;
};

type NotifyOptions = {
  description?: string | null;
  action?: NotifyAction;
};

const variantDurations: Record<ToastVariant, number> = {
  success: 3000,
  info: 4000,
  warning: 5000,
  error: 6000,
};

function notifyByVariant(
  variant: ToastVariant,
  message: string,
  options: NotifyOptions = {}
) {
  const description = options.description ?? undefined;
  const duration = variantDurations[variant];
  const toastOptions = {
    description,
    duration,
    action: options.action,
  };

  switch (variant) {
    case "success":
      return toast.success(message, toastOptions);
    case "warning":
      return toast.warning(message, toastOptions);
    case "error":
      return toast.error(message, toastOptions);
    case "info":
    default:
      return toast(message, toastOptions);
  }
}

export const notify = {
  success(message: string, options: NotifyOptions = {}) {
    return notifyByVariant("success", message, options);
  },
  info(message: string, options: NotifyOptions = {}) {
    return notifyByVariant("info", message, options);
  },
  warning(message: string, options: NotifyOptions = {}) {
    return notifyByVariant("warning", message, options);
  },
  error(message: string, options: NotifyOptions = {}) {
    return notifyByVariant("error", message, options);
  },
  show(payload: ToastPayload) {
    return notifyByVariant(payload.variant, payload.message, {
      description: payload.description,
    });
  },
  visit(url: string) {
    router.visit(url);
  },
};
